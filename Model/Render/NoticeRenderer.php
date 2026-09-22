<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Model\Render;

use CopeX\WarrantyLabel\Model\Config;
use CopeX\WarrantyLabel\Model\Language\LanguageRegistry;
use Magento\Framework\App\Area;
use Magento\Framework\View\Asset\Repository as AssetRepository;

/**
 * URLs and texts of the harmonised notice on the legal guarantee for one store view.
 */
class NoticeRenderer
{
    public function __construct(
        private readonly Config $config,
        private readonly LanguageRegistry $languageRegistry,
        private readonly AssetRepository $assetRepository
    ) {
    }

    /**
     * Official SVG of the store language for web pages.
     */
    public function getSvgUrl(?int $storeId = null): string
    {
        return $this->assetRepository->getUrl(
            $this->languageRegistry->getNoticeAssetId($this->config->getLanguageCode($storeId), 'svg')
        );
    }

    /**
     * Official PNG of the store language for emails: frontend area and secure URL, also inside store emulation.
     */
    public function getPngUrl(?int $storeId = null): string
    {
        return $this->assetRepository->getUrlWithParams(
            $this->languageRegistry->getNoticeAssetId($this->config->getLanguageCode($storeId), 'png'),
            ['area' => Area::AREA_FRONTEND, '_secure' => true]
        );
    }

    /**
     * Absolute path of the official PNG in the module directory, for attaching it to an email.
     */
    public function getPngSourceFile(?int $storeId = null): string
    {
        return $this->assetRepository
            ->createAsset(
                $this->languageRegistry->getNoticeAssetId($this->config->getLanguageCode($storeId), 'png'),
                ['area' => Area::AREA_FRONTEND]
            )
            ->getSourceFile();
    }

    /**
     * Link target, identical to the QR code destination of the notice.
     */
    public function getLinkUrl(?int $storeId = null): string
    {
        return $this->languageRegistry->getYourEuropeUrl($this->config->getLanguageCode($storeId));
    }

    /**
     * Visible link text as printed on the notice, e.g. "europa.eu/youreurope/garantien".
     */
    public function getLinkLabel(?int $storeId = null): string
    {
        return $this->languageRegistry->getYourEuropeDisplayUrl($this->config->getLanguageCode($storeId));
    }

    public function getAltText(?int $storeId = null): string
    {
        $configured = $this->config->getAltText($storeId);
        if ($configured !== '') {
            return $configured;
        }

        return (string) __(
            // phpcs:ignore Generic.Files.LineLength.TooLong, Magento2.Files.LineLength.MaxExceeded
            'EU legal guarantee notice: minimum two-year legal guarantee protection for goods sold in the European Union. More information: %1',
            $this->getLinkLabel($storeId)
        );
    }

    /**
     * Accessible name of the dialog that shows the complete notice.
     */
    public function getDialogLabel(): string
    {
        return (string) __('EU legal guarantee notice');
    }

    public function getTriggerText(?int $storeId = null): string
    {
        $configured = $this->config->getTriggerText($storeId);

        return $configured !== '' ? $configured : (string) __('Your legal guarantee rights');
    }

    public function getMinWidthPx(?int $storeId = null): int
    {
        return $this->config->getMinWidthPx($storeId);
    }
}
