<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Test\Unit\Model\Attribute\Backend;

use CopeX\WarrantyLabel\Model\Attribute\Backend\TermsUrl;
use CopeX\WarrantyLabel\Model\Garan\Attributes;
use CopeX\WarrantyLabel\Model\Garan\DurationParser;
use CopeX\WarrantyLabel\Model\Garan\FieldFitChecker;
use CopeX\WarrantyLabel\Model\Garan\LabelValidator;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class TermsUrlTest extends TestCase
{
    private TermsUrl $backend;

    protected function setUp(): void
    {
        $attribute = $this->createMock(Attribute::class);
        $attribute->method('getAttributeCode')->willReturn(Attributes::TERMS_URL);
        $this->backend = new TermsUrl(
            new LabelValidator(new DurationParser(), $this->createMock(FieldFitChecker::class))
        );
        $this->backend->setAttribute($attribute);
    }

    public function testBeforeSaveTrimsValidUrl(): void
    {
        $product = new DataObject([Attributes::TERMS_URL => ' https://example.com/terms ']);

        $this->backend->beforeSave($product);

        $this->assertSame('https://example.com/terms', $product->getData(Attributes::TERMS_URL));
    }

    public function testBeforeSaveStoresEmptyValueAsNull(): void
    {
        $product = new DataObject([Attributes::TERMS_URL => '']);

        $this->backend->beforeSave($product);

        $this->assertNull($product->getData(Attributes::TERMS_URL));
    }

    public function testBeforeSaveRejectsUrlWithoutHttpScheme(): void
    {
        $product = new DataObject([Attributes::TERMS_URL => 'www.example.com/terms']);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('The GARAN guarantee terms URL "www.example.com/terms" is invalid.');

        $this->backend->beforeSave($product);
    }

    public function testValidate(): void
    {
        $this->assertTrue($this->backend->validate(new DataObject([Attributes::TERMS_URL => 'http://example.com'])));
        $this->assertTrue($this->backend->validate(new DataObject([])));

        $this->expectException(LocalizedException::class);
        $this->backend->validate(new DataObject([Attributes::TERMS_URL => 'javascript:alert(1)']));
    }
}
