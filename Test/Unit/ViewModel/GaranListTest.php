<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Test\Unit\ViewModel;

use CopeX\WarrantyLabel\Api\Data\GaranLabelDataInterface;
use CopeX\WarrantyLabel\Api\GaranLabelResolverInterface;
use CopeX\WarrantyLabel\Model\Config;
use CopeX\WarrantyLabel\Model\Language\LanguageRegistry;
use CopeX\WarrantyLabel\Model\Render\GaranPngRenderer;
use CopeX\WarrantyLabel\Model\Source\DisplayMode;
use CopeX\WarrantyLabel\ViewModel\GaranLabelPresenter;
use CopeX\WarrantyLabel\ViewModel\GaranList;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class GaranListTest extends TestCase
{
    private const STORE_ID = 2;
    private const ACCESSIBLE_LABEL = 'GARAN – producer guarantee 4,5 years, Demo Brand Model XYZ';

    private Config&MockObject $config;
    private CheckoutSession&MockObject $checkoutSession;
    private GaranLabelResolverInterface&MockObject $resolver;
    private LoggerInterface&MockObject $logger;
    private GaranList $viewModel;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->resolver = $this->createMock(GaranLabelResolverInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $pngRenderer = $this->createMock(GaranPngRenderer::class);
        $pngRenderer->method('getUrl')->willReturnCallback(
            static fn (GaranLabelDataInterface $label, string $variant = 'full', ?int $storeId = null): ?string
                => $label->getProductName() === 'Broken'
                    ? null
                    : sprintf('https://shop.test/media/garan/%s-%s-%d.png', $label->getProductName(), $variant, $storeId)
        );

        $this->viewModel = new GaranList(
            $this->config,
            $this->checkoutSession,
            $this->resolver,
            new GaranLabelPresenter($pngRenderer),
            $this->logger
        );
    }

    public function testModeOffYieldsNoEntries(): void
    {
        $this->givenGaranMode(DisplayMode::OFF);
        $this->resolver->expects($this->never())->method('forOrderItem');

        $this->assertSame([], $this->viewModel->getEntriesForOrder($this->createOrder([$this->createItem(1)])));
    }

    public function testOrderWithoutIdYieldsNoEntries(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(null);
        $this->config->expects($this->never())->method('getGaranMode');

        $this->assertSame([], $this->viewModel->getEntriesForOrder($order));
    }

    public function testDirectModeProvidesFullImageOnlyAndSkipsItemsWithoutLabels(): void
    {
        $this->givenGaranMode(DisplayMode::DIRECT);
        $withLabel = $this->createItem(11, 'Espresso machine');
        $withoutLabel = $this->createItem(12, 'Wine');
        $this->resolver->method('forOrderItem')->willReturnMap([
            [$withLabel, [$this->createLabel('Espresso machine', 'https://brand.test/terms')]],
            [$withoutLabel, []],
        ]);

        $entries = $this->viewModel->getEntriesForOrder($this->createOrder([$withLabel, $withoutLabel]));

        $this->assertSame([
            [
                'name' => 'Espresso machine',
                'labels' => [
                    [
                        'productName' => 'Espresso machine',
                        'brand' => 'Demo Brand',
                        'model' => 'Model XYZ',
                        'years' => '4,5',
                        'termsUrl' => 'https://brand.test/terms',
                        'alt' => self::ACCESSIBLE_LABEL,
                        'pngFull' => 'https://shop.test/media/garan/Espresso machine-full-2.png',
                        'pngNested' => '',
                        'dialogId' => 'copex-wl-garan-11-0',
                    ],
                ],
            ],
        ], $entries);
    }

    public function testNestedModeProvidesNestedImagePerBundleChild(): void
    {
        $this->givenGaranMode(DisplayMode::NESTED);
        $this->resolver->method('forOrderItem')->willReturn([
            $this->createLabel('Kettle', ''),
            $this->createLabel('Broken', ''),
        ]);

        $entries = $this->viewModel->getEntriesForOrder($this->createOrder([$this->createItem(20, 'Kitchen bundle')]));

        $this->assertCount(1, $entries);
        $this->assertCount(2, $entries[0]['labels']);
        $this->assertSame('https://shop.test/media/garan/Kettle-nested-2.png', $entries[0]['labels'][0]['pngNested']);
        $this->assertSame('', $entries[0]['labels'][1]['pngFull']);
        $this->assertSame('', $entries[0]['labels'][1]['pngNested']);
        $this->assertSame('copex-wl-garan-20-1', $entries[0]['labels'][1]['dialogId']);
    }

    public function testResolverFailureIsLoggedAndYieldsNoEntries(): void
    {
        $this->givenGaranMode(DisplayMode::DIRECT);
        $this->resolver->method('forOrderItem')->willThrowException(new RuntimeException('broken snapshot'));
        $this->logger->expects($this->once())->method('error');

        $this->assertSame([], $this->viewModel->getEntriesForOrder($this->createOrder([$this->createItem(1)])));
    }

    public function testEntriesUseLastRealOrderOnce(): void
    {
        $this->givenGaranMode(DisplayMode::DIRECT);
        $this->resolver->method('forOrderItem')->willReturn([]);
        $this->checkoutSession->expects($this->once())
            ->method('getLastRealOrder')
            ->willReturn($this->createOrder([$this->createItem(1)]));

        $this->assertSame([], $this->viewModel->getEntries());
        $this->assertSame([], $this->viewModel->getEntries());
    }

    public function testModeInfoUrlNestedFlagAndAccessibleLabel(): void
    {
        $this->config->method('getGaranMode')->with(Config::PLACEMENT_SUCCESS, null)->willReturn(DisplayMode::NESTED);

        $this->assertSame(DisplayMode::NESTED, $this->viewModel->getMode());
        $this->assertTrue($this->viewModel->isNested());
        $this->assertSame(LanguageRegistry::GARAN_INFO_URL, $this->viewModel->getInfoUrl());
        $this->assertSame(
            self::ACCESSIBLE_LABEL,
            $this->viewModel->getAccessibleLabel($this->createLabel('Kettle', ''))
        );
    }

    private function givenGaranMode(string $mode): void
    {
        $this->config->method('getGaranMode')->with(Config::PLACEMENT_SUCCESS, self::STORE_ID)->willReturn($mode);
    }

    /**
     * @param list<OrderItem> $items
     */
    private function createOrder(array $items): Order&MockObject
    {
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn('100');
        $order->method('getStoreId')->willReturn((string) self::STORE_ID);
        $order->method('getAllVisibleItems')->willReturn($items);

        return $order;
    }

    private function createItem(int $id, string $name = 'Item'): OrderItem&MockObject
    {
        $item = $this->createMock(OrderItem::class);
        $item->method('getId')->willReturn((string) $id);
        $item->method('getName')->willReturn($name);

        return $item;
    }

    private function createLabel(string $productName, string $termsUrl): GaranLabelDataInterface&MockObject
    {
        $label = $this->createMock(GaranLabelDataInterface::class);
        $label->method('getProductName')->willReturn($productName);
        $label->method('getFormattedDuration')->willReturn('4,5');
        $label->method('getBrand')->willReturn('Demo Brand');
        $label->method('getModelIdentifier')->willReturn('Model XYZ');
        $label->method('getTermsUrl')->willReturn($termsUrl);

        return $label;
    }
}
