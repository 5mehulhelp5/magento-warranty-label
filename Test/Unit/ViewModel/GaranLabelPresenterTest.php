<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Test\Unit\ViewModel;

use CopeX\WarrantyLabel\Api\Data\GaranLabelDataInterface;
use CopeX\WarrantyLabel\Model\Render\GaranPngRenderer;
use CopeX\WarrantyLabel\ViewModel\GaranLabelPresenter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GaranLabelPresenterTest extends TestCase
{
    private const ALT = 'GARAN – producer guarantee 4,5 years, Brand & Co K 100';

    private GaranPngRenderer&MockObject $pngRenderer;
    private GaranLabelDataInterface&MockObject $label;
    private GaranLabelPresenter $presenter;

    protected function setUp(): void
    {
        $this->pngRenderer = $this->createMock(GaranPngRenderer::class);
        $this->label = $this->createMock(GaranLabelDataInterface::class);
        $this->label->method('getProductName')->willReturn('Kettle');
        $this->label->method('getBrand')->willReturn('Brand & Co');
        $this->label->method('getModelIdentifier')->willReturn('K 100');
        $this->label->method('getFormattedDuration')->willReturn('4,5');
        $this->label->method('getTermsUrl')->willReturn('https://brand.test/terms');
        $this->presenter = new GaranLabelPresenter($this->pngRenderer);
    }

    public function testAccessibleLabelAndArrayCarryAllLabelFields(): void
    {
        $this->assertSame(self::ALT, $this->presenter->getAccessibleLabel($this->label));
        $this->assertSame([
            'productName' => 'Kettle',
            'brand' => 'Brand & Co',
            'model' => 'K 100',
            'years' => '4,5',
            'termsUrl' => 'https://brand.test/terms',
            'alt' => self::ALT,
        ], $this->presenter->toArray($this->label));
    }

    public function testViewDataAddsPngUrlsAndMissingImageBecomesEmpty(): void
    {
        $this->pngRenderer->method('getUrl')->willReturnMap([
            [$this->label, 'full', 4, 'https://shop.test/media/copex_warranty_label/garan/full.png'],
            [$this->label, 'nested', 4, null],
        ]);

        $data = $this->presenter->toViewData($this->label, true, 4);

        $this->assertSame('https://shop.test/media/copex_warranty_label/garan/full.png', $data['pngFull']);
        $this->assertSame('', $data['pngNested']);
        $this->assertSame(self::ALT, $data['alt']);
    }

    public function testViewDataInDirectModeRequestsFullImageOnly(): void
    {
        $this->pngRenderer->expects($this->once())
            ->method('getUrl')
            ->with($this->label, 'full', null)
            ->willReturn('https://shop.test/full.png');

        $data = $this->presenter->toViewData($this->label, false);

        $this->assertSame('https://shop.test/full.png', $data['pngFull']);
        $this->assertSame('', $data['pngNested']);
    }
}
