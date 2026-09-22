<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Test\Unit\Model\Source;

use CopeX\WarrantyLabel\Model\Language\LanguageRegistry;
use CopeX\WarrantyLabel\Model\Source\BrandSource;
use CopeX\WarrantyLabel\Model\Source\DisplayMode;
use CopeX\WarrantyLabel\Model\Source\ModelIdentifierSource;
use CopeX\WarrantyLabel\Model\Source\ProductTextAttribute;
use CopeX\WarrantyLabel\Model\Source\Language;
use CopeX\WarrantyLabel\Model\Source\ProductType;
use Magento\Catalog\Model\Product\Type;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Collection;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\CollectionFactory;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;

class SourceModelsTest extends TestCase
{
    public function testDisplayModeOffersAllModes(): void
    {
        $values = array_column((new DisplayMode())->toOptionArray(), 'value');

        self::assertSame(DisplayMode::MODES, $values);
    }

    public function testLanguageOffersAutomaticPlusTwentyFourLanguages(): void
    {
        $options = (new Language(new LanguageRegistry()))->toOptionArray();

        self::assertCount(25, $options);
        self::assertSame('', $options[0]['value']);
        self::assertContains(['value' => 'de', 'label' => 'German (DE)'], $options);
    }

    public function testProductTypeMapsMagentoTypes(): void
    {
        $type = $this->createMock(Type::class);
        $type->method('getOptionArray')->willReturn(['simple' => 'Simple Product', 'virtual' => 'Virtual Product']);

        self::assertSame(
            [
                ['value' => 'simple', 'label' => 'Simple Product'],
                ['value' => 'virtual', 'label' => 'Virtual Product'],
            ],
            (new ProductType($type))->toOptionArray()
        );
    }

    public function testBrandSourceOffersAllSources(): void
    {
        $values = array_column((new BrandSource())->toOptionArray(), 'value');

        self::assertSame(BrandSource::SOURCES, $values);
    }

    public function testModelIdentifierSourceOffersAllSources(): void
    {
        $values = array_column((new ModelIdentifierSource())->toOptionArray(), 'value');

        self::assertSame(ModelIdentifierSource::SOURCES, $values);
    }

    public function testProductTextAttributeListsAttributesWithTheirLabel(): void
    {
        $attributes = [
            new DataObject(['attribute_code' => 'manufacturer', 'default_frontend_label' => 'Hersteller']),
            new DataObject(['attribute_code' => 'brand_name', 'default_frontend_label' => '  ']),
        ];

        $source = new ProductTextAttribute($this->createCollectionFactory($attributes));
        $options = $source->toOptionArray();

        self::assertSame([
            ['value' => '', 'label' => '-- Please select --'],
            ['value' => 'manufacturer', 'label' => 'Hersteller (manufacturer)'],
            ['value' => 'brand_name', 'label' => 'brand_name'],
        ], $options);
        self::assertSame($options, $source->toOptionArray());
    }

    /**
     * @param list<DataObject> $attributes
     */
    private function createCollectionFactory(array $attributes): CollectionFactory&\PHPUnit\Framework\MockObject\MockObject
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchOne')->willReturn('4');

        $collection = $this->createMock(Collection::class);
        $collection->method('getConnection')->willReturn($connection);
        $collection->method('getTable')->willReturn('eav_entity_type');
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator($attributes));

        $factory = $this->getMockBuilder(CollectionFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $factory->method('create')->willReturn($collection);

        return $factory;
    }
}
