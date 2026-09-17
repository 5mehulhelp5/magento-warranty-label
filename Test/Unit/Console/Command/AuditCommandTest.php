<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Test\Unit\Console\Command;

use ArrayIterator;
use CopeX\WarrantyLabel\Console\Command\AuditCommand;
use CopeX\WarrantyLabel\Model\Garan\Attributes;
use CopeX\WarrantyLabel\Model\Garan\DurationParser;
use CopeX\WarrantyLabel\Model\Garan\FieldFitChecker;
use CopeX\WarrantyLabel\Model\Garan\LabelValidator;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class AuditCommandTest extends TestCase
{
    private const TERMS_URL = 'https://example.com/terms';

    private CollectionFactory&MockObject $collectionFactory;
    private StoreManagerInterface&MockObject $storeManager;
    private FieldFitChecker&MockObject $fieldFitChecker;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->collectionFactory = $this->getMockBuilder(CollectionFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->fieldFitChecker = $this->createMock(FieldFitChecker::class);
        $this->fieldFitChecker->method('fitsBrand')->willReturnCallback(
            static fn (string $brand): bool => strlen($brand) <= 20
        );
        $this->fieldFitChecker->method('fitsModelIdentifier')->willReturn(true);
        $this->fieldFitChecker->method('fitsBrandAndModel')->willReturn(true);
        $this->fieldFitChecker->method('fitsDuration')->willReturnCallback(
            static fn (string $duration): bool => !str_contains($duration, ',') || $duration === '7,5'
        );

        $appState = $this->createMock(State::class);
        $appState->method('emulateAreaCode')->willReturnCallback(
            static function (string $areaCode, callable $callback): mixed {
                self::assertSame(Area::AREA_ADMINHTML, $areaCode);
                return $callback();
            }
        );

        $command = new AuditCommand(
            $this->collectionFactory,
            new LabelValidator(new DurationParser(), $this->fieldFitChecker),
            $this->storeManager,
            $appState
        );
        $this->tester = new CommandTester($command);
    }

    public function testReportsIncompleteAndInvalidProducts(): void
    {
        $this->givenStore(0, 0);
        $this->givenPages([[
            $this->product(1, 'VALID', 'Brand', 'Model', '5.000000', self::TERMS_URL),
            $this->product(2, 'PARTIAL', 'Brand', null, null, self::TERMS_URL),
            $this->product(3, 'DB-WRITE', 'Brand', 'Model', '4.200000', 'terms.pdf'),
            $this->product(4, 'LONG', 'A brand name far too long', 'Model', '3.000000', self::TERMS_URL),
            $this->product(5, 'EMPTY-STRINGS', '', ' ', null, ''),
            $this->product(6, 'HALF-YEAR', 'Brand', 'Model', '4.500000', self::TERMS_URL),
            $this->product(7, 'SEVEN-AND-A-HALF', 'Brand', 'Model', '7.500000', self::TERMS_URL),
        ]]);

        $exitCode = $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringNotContainsString('VALID', $output);
        $this->assertStringNotContainsString('EMPTY-STRINGS', $output);
        $this->assertMatchesRegularExpression(
            '/PARTIAL\s*\|\s*2\s*\|\s*missing_model_identifier, missing_duration/',
            $output
        );
        $this->assertMatchesRegularExpression(
            '/DB-WRITE\s*\|\s*3\s*\|\s*invalid_duration, invalid_terms_url/',
            $output
        );
        $this->assertMatchesRegularExpression('/LONG\s*\|\s*4\s*\|\s*too_long/', $output);
        $this->assertMatchesRegularExpression('/HALF-YEAR\s*\|\s*6\s*\|\s*duration_does_not_fit/', $output);
        $this->assertStringNotContainsString('SEVEN-AND-A-HALF', $output);
        $this->assertStringContainsString(
            '6 product(s) with GARAN data checked in store 0, 4 with incomplete or invalid data.',
            $output
        );
    }

    public function testPaginatesUntilPageIsNotFull(): void
    {
        $this->givenStore('default_store', 2);
        $firstPage = [];
        for ($id = 1; $id <= 500; $id++) {
            $firstPage[] = $this->product($id, 'OK-' . $id, 'Brand', 'Model', '5', self::TERMS_URL);
        }
        $this->givenPages([
            $firstPage,
            [$this->product(501, 'LAST', 'Brand', 'Model', '2', self::TERMS_URL)],
        ]);

        $exitCode = $this->tester->execute(['--store' => 'default_store']);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertMatchesRegularExpression('/LAST\s*\|\s*501\s*\|\s*invalid_duration/', $this->tester->getDisplay());
        $this->assertStringContainsString(
            '501 product(s) with GARAN data checked in store 2, 1 with',
            $this->tester->getDisplay()
        );
    }

    public function testStopsAtLimit(): void
    {
        $this->givenStore(0, 0);
        $this->givenPages([[
            $this->product(1, 'FIRST', 'Brand', null, '5', self::TERMS_URL),
            $this->product(2, 'SECOND', 'Brand', null, '5', self::TERMS_URL),
        ]]);

        $exitCode = $this->tester->execute(['--limit' => '1']);

        $this->assertSame(Cli::RETURN_SUCCESS, $exitCode);
        $this->assertStringContainsString('FIRST', $this->tester->getDisplay());
        $this->assertStringNotContainsString('SECOND', $this->tester->getDisplay());
        $this->assertStringContainsString('Stopped after the limit of 1', $this->tester->getDisplay());
    }

    public function testPrintsSummaryWhenNothingIsWrong(): void
    {
        $this->givenStore(0, 0);
        $this->givenPages([[]]);

        $this->assertSame(Cli::RETURN_SUCCESS, $this->tester->execute([]));
        $this->assertStringContainsString('0 product(s) with GARAN data checked', $this->tester->getDisplay());
    }

    public function testRejectsInvalidLimit(): void
    {
        $this->collectionFactory->expects($this->never())->method('create');

        $this->assertSame(Cli::RETURN_FAILURE, $this->tester->execute(['--limit' => '-1']));
    }

    public function testRejectsUnknownStore(): void
    {
        $this->storeManager->method('getStore')->willThrowException(new NoSuchEntityException());
        $this->collectionFactory->expects($this->never())->method('create');

        $this->assertSame(Cli::RETURN_FAILURE, $this->tester->execute(['--store' => 'nope']));
        $this->assertStringContainsString('Store "nope" does not exist.', $this->tester->getDisplay());
    }

    private function givenStore(int|string $option, int $storeId): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);
        $this->storeManager->method('getStore')->with($option)->willReturn($store);
    }

    /**
     * @param list<list<DataObject>> $pages
     */
    private function givenPages(array $pages): void
    {
        $collections = [];
        $fluentMethods = [
            'setStoreId',
            'addAttributeToSelect',
            'addAttributeToFilter',
            'setOrder',
            'setPageSize',
            'setCurPage',
        ];
        foreach ($pages as $page) {
            $collection = $this->createMock(Collection::class);
            foreach ($fluentMethods as $method) {
                $collection->method($method)->willReturnSelf();
            }
            $collection->method('getIterator')->willReturn(new ArrayIterator($page));
            $collections[] = $collection;
        }
        $this->collectionFactory->expects($this->exactly(count($pages)))
            ->method('create')
            ->willReturnOnConsecutiveCalls(...$collections);
    }

    private function product(
        int $id,
        string $sku,
        ?string $brand,
        ?string $model,
        ?string $duration,
        ?string $termsUrl
    ): DataObject {
        return new DataObject([
            'id' => $id,
            'sku' => $sku,
            Attributes::BRAND => $brand,
            Attributes::MODEL_IDENTIFIER => $model,
            Attributes::DURATION_YEARS => $duration,
            Attributes::TERMS_URL => $termsUrl,
        ]);
    }
}
