<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Test\Unit\ViewModel;

use CopeX\WarrantyLabel\Model\Config;
use CopeX\WarrantyLabel\Model\Render\NoticeRenderer;
use CopeX\WarrantyLabel\Model\Source\DisplayMode;
use CopeX\WarrantyLabel\ViewModel\Notice;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NoticeTest extends TestCase
{
    private Config&MockObject $config;
    private NoticeRenderer&MockObject $noticeRenderer;
    private AssetRepository&MockObject $assetRepository;
    private Notice $viewModel;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('getNoticeMode')->willReturnMap([
            [Config::PLACEMENT_HEADER, null, DisplayMode::NESTED],
            [Config::PLACEMENT_FOOTER, null, DisplayMode::OFF],
            [Config::PLACEMENT_CHECKOUT, null, DisplayMode::DIRECT],
        ]);
        $this->noticeRenderer = $this->createMock(NoticeRenderer::class);
        $this->assetRepository = $this->createMock(AssetRepository::class);
        $this->viewModel = new Notice($this->config, $this->noticeRenderer, $this->assetRepository);
    }

    /**
     * @return array<string, array{string, string, bool, bool}>
     */
    public static function placementProvider(): array
    {
        return [
            'nested header' => [Config::PLACEMENT_HEADER, DisplayMode::NESTED, true, true],
            'disabled footer' => [Config::PLACEMENT_FOOTER, DisplayMode::OFF, false, false],
            'direct checkout' => [Config::PLACEMENT_CHECKOUT, DisplayMode::DIRECT, true, false],
        ];
    }

    #[DataProvider('placementProvider')]
    public function testModeAndVisibilityPerPlacement(
        string $placement,
        string $expectedMode,
        bool $expectedVisible,
        bool $expectedNested
    ): void {
        $this->assertSame($expectedMode, $this->viewModel->getMode($placement));
        $this->assertSame($expectedVisible, $this->viewModel->isVisible($placement));
        $this->assertSame($expectedNested, $this->viewModel->isNested($placement));
    }

    public function testDialogIdIsUniquePerPlacement(): void
    {
        $this->assertSame('copex-wl-notice-header', $this->viewModel->getDialogId(Config::PLACEMENT_HEADER));
        $this->assertSame('copex-wl-notice-footer', $this->viewModel->getDialogId(Config::PLACEMENT_FOOTER));
        $this->assertSame('copex-wl-notice-bad', $this->viewModel->getDialogId('"Bad<'));
    }

    public function testDelegatesTextsAndUrlsToRenderer(): void
    {
        $this->noticeRenderer->method('getSvgUrl')->willReturn('https://shop.test/notice/de.svg');
        $this->noticeRenderer->method('getAltText')->willReturn('Alt');
        $this->noticeRenderer->method('getTriggerText')->willReturn('Trigger');
        $this->noticeRenderer->method('getDialogLabel')->willReturn('Dialog');
        $this->noticeRenderer->method('getLinkUrl')->willReturn('https://europa.eu/youreurope/garantien');
        $this->noticeRenderer->method('getLinkLabel')->willReturn('europa.eu/youreurope/garantien');

        $this->assertSame('https://shop.test/notice/de.svg', $this->viewModel->getImageUrl());
        $this->assertSame('Alt', $this->viewModel->getAltText());
        $this->assertSame('Trigger', $this->viewModel->getTriggerText());
        $this->assertSame('Dialog', $this->viewModel->getDialogLabel());
        $this->assertSame('https://europa.eu/youreurope/garantien', $this->viewModel->getLinkUrl());
        $this->assertSame('europa.eu/youreurope/garantien', $this->viewModel->getLinkLabel());
    }

    public function testImageSizeKeepsA4AspectRatioAtMinimumWidth(): void
    {
        $this->noticeRenderer->method('getMinWidthPx')->willReturn(420);

        $this->assertSame(420, $this->viewModel->getMinWidthPx());
        $this->assertSame(594, $this->viewModel->getImageHeightPx());
    }

    public function testStylesheetAndEnabledFlag(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->assetRepository->expects($this->once())
            ->method('getUrl')
            ->with('CopeX_WarrantyLabel::css/warranty-label.css')
            ->willReturn('https://shop.test/static/css/warranty-label.css');

        $this->assertTrue($this->viewModel->isEnabled());
        $this->assertSame('https://shop.test/static/css/warranty-label.css', $this->viewModel->getStylesheetUrl());
    }
}
