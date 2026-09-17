<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Test\Unit\Model\Source;

use CopeX\WarrantyLabel\Model\Language\LanguageRegistry;
use CopeX\WarrantyLabel\Model\Source\DisplayMode;
use CopeX\WarrantyLabel\Model\Source\Language;
use CopeX\WarrantyLabel\Model\Source\ProductType;
use Magento\Catalog\Model\Product\Type;
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
}
