<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\ViewModel;

use CopeX\WarrantyLabel\Model\Config;
use CopeX\WarrantyLabel\Model\Render\NoticeRenderer;
use CopeX\WarrantyLabel\Model\Source\DisplayMode;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Harmonised notice on the legal guarantee for one layout placement of the current store view.
 */
class Notice implements ArgumentInterface
{
    public const STYLESHEET_ASSET_ID = 'CopeX_WarrantyLabel::css/warranty-label.css';

    /**
     * Height / width of the official A4 notice (SVG viewBox 595.28 x 841.89).
     */
    private const ASPECT_RATIO = 841.89 / 595.28;
    private const DIALOG_ID_PREFIX = 'copex-wl-notice-';

    public function __construct(
        private readonly Config $config,
        private readonly NoticeRenderer $noticeRenderer,
        private readonly AssetRepository $assetRepository
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    public function getMode(string $placement): string
    {
        return $this->config->getNoticeMode($placement);
    }

    public function isVisible(string $placement): bool
    {
        return $this->getMode($placement) !== DisplayMode::OFF;
    }

    public function isNested(string $placement): bool
    {
        return $this->getMode($placement) === DisplayMode::NESTED;
    }

    /**
     * Unique per placement, header and footer notices share one page.
     */
    public function getDialogId(string $placement): string
    {
        return self::DIALOG_ID_PREFIX . preg_replace('/[^a-z0-9_-]/', '', strtolower($placement));
    }

    public function getImageUrl(): string
    {
        return $this->noticeRenderer->getSvgUrl();
    }

    public function getAltText(): string
    {
        return $this->noticeRenderer->getAltText();
    }

    public function getTriggerText(): string
    {
        return $this->noticeRenderer->getTriggerText();
    }

    public function getDialogLabel(): string
    {
        return $this->noticeRenderer->getDialogLabel();
    }

    public function getLinkUrl(): string
    {
        return $this->noticeRenderer->getLinkUrl();
    }

    public function getLinkLabel(): string
    {
        return $this->noticeRenderer->getLinkLabel();
    }

    /**
     * The graphic is never rendered narrower than this width.
     */
    public function getMinWidthPx(): int
    {
        return $this->noticeRenderer->getMinWidthPx();
    }

    /**
     * Intrinsic height matching getMinWidthPx(), avoids layout shift while the image loads.
     */
    public function getImageHeightPx(): int
    {
        return (int) round($this->getMinWidthPx() * self::ASPECT_RATIO);
    }

    public function getStylesheetUrl(): string
    {
        return $this->assetRepository->getUrl(self::STYLESHEET_ASSET_ID);
    }
}
