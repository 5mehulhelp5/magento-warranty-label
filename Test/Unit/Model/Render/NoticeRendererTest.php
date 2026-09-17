<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Test\Unit\Model\Render;

use CopeX\WarrantyLabel\Model\Config;
use CopeX\WarrantyLabel\Model\Language\LanguageRegistry;
use CopeX\WarrantyLabel\Model\Render\NoticeRenderer;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NoticeRendererTest extends TestCase
{
    private Config&MockObject $config;
    private AssetRepository&MockObject $assetRepository;
    private NoticeRenderer $renderer;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->assetRepository = $this->createMock(AssetRepository::class);
        $this->renderer = new NoticeRenderer($this->config, new LanguageRegistry(), $this->assetRepository);
    }

    public function testSvgUrlUsesNoticeAssetOfStoreLanguage(): void
    {
        $this->config->expects($this->once())->method('getLanguageCode')->with(3)->willReturn('de');
        $this->assetRepository->expects($this->once())
            ->method('getUrl')
            ->with('CopeX_WarrantyLabel::notice/de.svg')
            ->willReturn('https://shop.test/static/frontend/CopeX_WarrantyLabel/notice/de.svg');

        $this->assertSame(
            'https://shop.test/static/frontend/CopeX_WarrantyLabel/notice/de.svg',
            $this->renderer->getSvgUrl(3)
        );
    }

    public function testPngUrlUsesFrontendAreaAndSecureUrlForEmails(): void
    {
        $this->config->method('getLanguageCode')->with(7)->willReturn('en');
        $this->assetRepository->expects($this->once())
            ->method('getUrlWithParams')
            ->with('CopeX_WarrantyLabel::notice/en.png', ['area' => 'frontend', '_secure' => true])
            ->willReturn('https://shop.test/static/frontend/CopeX_WarrantyLabel/notice/en.png');

        $this->assertSame(
            'https://shop.test/static/frontend/CopeX_WarrantyLabel/notice/en.png',
            $this->renderer->getPngUrl(7)
        );
    }

    public function testLinkMatchesQrTargetOfLanguage(): void
    {
        $this->config->method('getLanguageCode')->willReturn('de');

        $this->assertSame('https://europa.eu/youreurope/garantien', $this->renderer->getLinkUrl());
        $this->assertSame('europa.eu/youreurope/garantien', $this->renderer->getLinkLabel());
    }

    public function testDefaultAltTextContainsDisplayUrl(): void
    {
        $this->config->method('getLanguageCode')->willReturn('en');
        $this->config->method('getAltText')->willReturn('');

        $altText = $this->renderer->getAltText();

        $this->assertStringStartsWith('EU legal guarantee notice:', $altText);
        $this->assertStringEndsWith('More information: europa.eu/youreurope/guarantees', $altText);
    }

    public function testConfiguredAltTextWins(): void
    {
        $this->config->method('getAltText')->with(2)->willReturn('Gewährleistungshinweis');

        $this->assertSame('Gewährleistungshinweis', $this->renderer->getAltText(2));
    }

    public function testTriggerTextFallsBackToDefault(): void
    {
        $this->config->method('getTriggerText')->willReturn('');

        $this->assertSame('Your legal guarantee rights', $this->renderer->getTriggerText());
    }

    public function testConfiguredTriggerTextWins(): void
    {
        $this->config->method('getTriggerText')->willReturn('Ihre Gewährleistungsrechte');

        $this->assertSame('Ihre Gewährleistungsrechte', $this->renderer->getTriggerText());
    }

    public function testMinWidthAndDialogLabel(): void
    {
        $this->config->method('getMinWidthPx')->with(4)->willReturn(360);

        $this->assertSame(360, $this->renderer->getMinWidthPx(4));
        $this->assertSame('EU legal guarantee notice', $this->renderer->getDialogLabel());
    }
}
