<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Test\Unit\ViewModel;

use ArrayIterator;
use CopeX\WarrantyLabel\Api\Data\GaranLabelDataInterface;
use CopeX\WarrantyLabel\Api\GaranLabelResolverInterface;
use CopeX\WarrantyLabel\Model\Config;
use CopeX\WarrantyLabel\Model\Garan\Attributes;
use CopeX\WarrantyLabel\Model\Render\GaranPngRenderer;
use CopeX\WarrantyLabel\Model\Source\DisplayMode;
use CopeX\WarrantyLabel\ViewModel\GaranLabelPresenter;
use CopeX\WarrantyLabel\ViewModel\GaranProduct;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type\Simple;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable\Product\Collection;
use Magento\Framework\DataObject;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class GaranProductTest extends TestCase
{
    private const STORE_ID = 1;

    private Config&MockObject $config;
    private GaranLabelResolverInterface&MockObject $resolver;
    private LoggerInterface&MockObject $logger;
    private GaranProduct $viewModel;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->resolver = $this->createMock(GaranLabelResolverInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $pngRenderer = $this->createMock(GaranPngRenderer::class);
        $pngRenderer->method('getUrl')->willReturnCallback(
            static fn (GaranLabelDataInterface $label, string $variant = 'full', ?int $storeId = null): ?string
                => sprintf('https://shop.test/media/garan/%s-%s-%d.png', $label->getModelIdentifier(), $variant, $storeId)
        );

        $this->viewModel = new GaranProduct(
            $this->config,
            $this->resolver,
            new GaranLabelPresenter($pngRenderer),
            new Json(),
            $this->logger
        );
    }

    public function testModeOffRendersNothing(): void
    {
        $this->givenMode(DisplayMode::OFF);
        $this->resolver->expects($this->never())->method('forProduct');
        $product = $this->createConfigurable([], []);

        $this->assertNull($this->viewModel->getLabel($product));
        $this->assertNull($this->viewModel->getVariants($product));
    }

    public function testSimpleProductInDirectMode(): void
    {
        $this->givenMode(DisplayMode::DIRECT);
        $product = $this->createSimple();
        $this->resolver->expects($this->once())
            ->method('forProduct')
            ->with($product, self::STORE_ID)
            ->willReturn($this->createLabel('Kettle', 'K1', '5'));

        $this->assertSame([
            'productName' => 'Kettle',
            'brand' => 'Brand',
            'model' => 'K1',
            'years' => '5',
            'termsUrl' => 'https://brand.test/terms',
            'alt' => 'GARAN – producer guarantee 5 years, Brand K1',
            'pngFull' => 'https://shop.test/media/garan/K1-full-1.png',
            'pngNested' => '',
        ], $this->viewModel->getLabel($product));
        $this->assertFalse($this->viewModel->isNested());
        $this->assertNull($this->viewModel->getVariants($product));
    }

    public function testDialogOnlyKeepsTheDialogAndDropsTheTrigger(): void
    {
        $this->givenMode(DisplayMode::DIALOG_ONLY);

        $this->assertTrue($this->viewModel->isNested());
        $this->assertFalse($this->viewModel->hasTrigger());
    }

    public function testNestedModeCarriesItsOwnTrigger(): void
    {
        $this->givenMode(DisplayMode::NESTED);

        $this->assertTrue($this->viewModel->hasTrigger());
    }

    public function testSimpleProductInNestedModeAddsNestedImage(): void
    {
        $this->givenMode(DisplayMode::NESTED);
        $this->resolver->method('forProduct')->willReturn($this->createLabel('Kettle', 'K1', '4,5'));

        $label = $this->viewModel->getLabel($this->createSimple());

        $this->assertSame('https://shop.test/media/garan/K1-nested-1.png', $label['pngNested']);
        $this->assertTrue($this->viewModel->isNested());
    }

    public function testProductWithoutLabelRendersNothing(): void
    {
        $this->givenMode(DisplayMode::NESTED);
        $this->resolver->method('forProduct')->willReturn(null);

        $this->assertNull($this->viewModel->getLabel($this->createSimple()));
    }

    public function testConfigurableMapContainsOnlyQualifyingChildren(): void
    {
        $this->givenMode(DisplayMode::NESTED);
        $qualifying = $this->createChild(29245, ['color' => '12', 'size' => '30']);
        $withoutLabel = $this->createChild(29246, ['color' => '13', 'size' => '30']);
        $withoutOption = $this->createChild(29247, ['color' => '14']);
        $product = $this->createConfigurable(
            [93 => 'color', 144 => 'size'],
            [$qualifying, $withoutLabel, $withoutOption],
            static fn (array $codes): bool => array_diff([...Attributes::ALL, 'name', 'sku', 'color', 'size'], $codes) === [],
            true
        );
        $qualifying->method('hasData')->willReturnCallback(static fn ($key = ''): bool => $key === Attributes::BRAND);
        $filled = [];
        $qualifying->expects($this->exactly(3))
            ->method('setData')
            ->willReturnCallback(static function ($key, $value = null) use (&$filled, $qualifying) {
                $filled[$key] = $value;

                return $qualifying;
            });
        $withoutOption->expects($this->never())->method('setData');
        $this->resolver->method('forProduct')->willReturnCallback(
            fn (ProductInterface $child, ?int $storeId = null): ?GaranLabelDataInterface => $child === $qualifying
                && $storeId === self::STORE_ID
                ? $this->createLabel('Kettle red', 'K1 red', '10')
                : null
        );

        $variants = $this->viewModel->getVariants($product);

        $this->assertSame([
            'children' => [
                29245 => [
                    'attributes' => [93 => '12', 144 => '30'],
                    'label' => [
                        'alt' => 'GARAN – producer guarantee 10 years, Brand K1 red',
                        'pngFull' => 'https://shop.test/media/garan/K1 red-full-1.png',
                        'pngNested' => 'https://shop.test/media/garan/K1 red-nested-1.png',
                        'termsUrl' => 'https://brand.test/terms',
                    ],
                ],
            ],
        ], $variants);

        $this->assertSame(
            [Attributes::MODEL_IDENTIFIER => null, Attributes::DURATION_YEARS => null, Attributes::TERMS_URL => null],
            $filled
        );

        $mageInit = json_decode($this->viewModel->getMageInit($variants), true);
        $this->assertSame(
            ['93' => '12', '144' => '30'],
            $mageInit['CopeX_WarrantyLabel/js/garan-variant']['children']['29245']['attributes']
        );
    }

    public function testConfigurableWithoutQualifyingChildrenRendersNothing(): void
    {
        $this->givenMode(DisplayMode::DIRECT);
        $product = $this->createConfigurable([93 => 'color'], [$this->createChild(301, ['color' => '12'])]);
        $this->resolver->method('forProduct')->willReturn(null);

        $this->assertNull($this->viewModel->getVariants($product));
    }

    public function testFailuresAreLoggedAndRenderNothing(): void
    {
        $this->givenMode(DisplayMode::DIRECT);
        $this->resolver->method('forProduct')->willThrowException(new RuntimeException('font missing'));
        $this->logger->expects($this->exactly(2))->method('error');

        $this->assertNull($this->viewModel->getLabel($this->createSimple()));
        $this->assertNull(
            $this->viewModel->getVariants(
                $this->createConfigurable([93 => 'color'], [$this->createChild(401, ['color' => '12'])])
            )
        );
    }

    public function testMageInitForSimpleProductOnlyInitialisesDialog(): void
    {
        $this->assertSame('{"CopeX_WarrantyLabel\/js\/dialog":{}}', $this->viewModel->getMageInit(null));
    }

    private function givenMode(string $mode): void
    {
        $this->config->method('getGaranMode')->willReturn($mode);
    }

    private function createSimple(): Product&MockObject
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('54025');
        $product->method('getStoreId')->willReturn((string) self::STORE_ID);
        $product->method('getTypeInstance')->willReturn($this->createMock(Simple::class));

        return $product;
    }

    /**
     * @param array<int, string> $attributeCodes attribute id => code
     * @param list<Product> $children
     * @param callable|null $selectAssertion
     * @param bool $expectDurationFilter
     */
    private function createConfigurable(
        array $attributeCodes,
        array $children,
        ?callable $selectAssertion = null,
        bool $expectDurationFilter = false
    ): Product&MockObject {
        $attributes = [new DataObject()];
        foreach ($attributeCodes as $id => $code) {
            $attributes[] = new DataObject([
                'product_attribute' => new DataObject(['id' => $id, 'attribute_code' => $code]),
            ]);
        }

        $collection = $this->createMock(Collection::class);
        $collection->method('getIterator')->willReturn(new ArrayIterator($children));
        $select = $collection->method('addAttributeToSelect');
        if ($selectAssertion !== null) {
            $select->with($this->callback($selectAssertion));
        }
        $select->willReturnSelf();
        $collection->expects($expectDurationFilter ? $this->once() : $this->any())
            ->method('addAttributeToFilter')
            ->with(Attributes::DURATION_YEARS, ['notnull' => true])
            ->willReturnSelf();

        $product = $this->createMock(Product::class);
        $typeInstance = $this->createMock(Configurable::class);
        $typeInstance->method('getConfigurableAttributes')->with($product)->willReturn($attributes);
        $typeInstance->method('getUsedProductCollection')->with($product)->willReturn($collection);

        $product->method('getId')->willReturn('29244');
        $product->method('getStoreId')->willReturn((string) self::STORE_ID);
        $product->method('getTypeInstance')->willReturn($typeInstance);

        return $product;
    }

    /**
     * @param array<string, string> $values
     */
    private function createChild(int $id, array $values): Product&MockObject
    {
        $child = $this->createMock(Product::class);
        $child->method('getId')->willReturn((string) $id);
        $child->method('getData')->willReturnCallback(
            static fn ($key = '', $index = null) => $values[$key] ?? null
        );

        return $child;
    }

    private function createLabel(string $productName, string $model, string $years): GaranLabelDataInterface&MockObject
    {
        $label = $this->createMock(GaranLabelDataInterface::class);
        $label->method('getProductName')->willReturn($productName);
        $label->method('getBrand')->willReturn('Brand');
        $label->method('getModelIdentifier')->willReturn($model);
        $label->method('getFormattedDuration')->willReturn($years);
        $label->method('getTermsUrl')->willReturn('https://brand.test/terms');

        return $label;
    }
}
