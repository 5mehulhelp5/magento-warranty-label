<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Model;

use CopeX\WarrantyLabel\Model\Language\LanguageRegistry;
use CopeX\WarrantyLabel\Model\Source\DisplayMode;
use InvalidArgumentException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const XML_PATH_ENABLED = 'copex_warrantylabel/general/enabled';
    public const XML_PATH_LANGUAGE = 'copex_warrantylabel/general/language';
    public const XML_PATH_TRIGGER_TEXT = 'copex_warrantylabel/general/trigger_text';
    public const XML_PATH_ALT_TEXT = 'copex_warrantylabel/general/alt_text';
    public const XML_PATH_MIN_WIDTH_PX = 'copex_warrantylabel/general/min_width_px';
    public const XML_PATH_EXCLUDED_PRODUCT_TYPES = 'copex_warrantylabel/general/excluded_product_types';
    public const XML_PATH_NOTICE_PLACEMENT_PREFIX = 'copex_warrantylabel/notice_placement/';
    public const XML_PATH_GARAN_ENABLED = 'copex_warrantylabel/garan/enabled';
    public const XML_PATH_GARAN_PLACEMENT_PREFIX = 'copex_warrantylabel/garan/';
    public const XML_PATH_GARAN_ATTACH_TERMS = 'copex_warrantylabel/garan/attach_terms';
    public const XML_PATH_GARAN_TERMS_FILE = 'copex_warrantylabel/garan/terms_file';
    public const XML_PATH_GARAN_TERMS_FILENAME = 'copex_warrantylabel/garan/terms_filename';

    /**
     * Media sub directory the guarantee terms file is uploaded to (system.xml upload_dir).
     */
    public const TERMS_UPLOAD_DIR = 'copex_warranty_label/terms';

    public const PLACEMENT_HEADER = 'header';
    public const PLACEMENT_FOOTER = 'footer';
    public const PLACEMENT_CATEGORY = 'category';
    public const PLACEMENT_SEARCH = 'search';
    public const PLACEMENT_CART = 'cart';
    public const PLACEMENT_CHECKOUT = 'checkout';
    public const PLACEMENT_SUCCESS = 'success';
    public const PLACEMENT_EMAIL = 'email';
    public const PLACEMENT_PDP = 'pdp';

    public const NOTICE_PLACEMENTS = [
        self::PLACEMENT_HEADER,
        self::PLACEMENT_FOOTER,
        self::PLACEMENT_CATEGORY,
        self::PLACEMENT_SEARCH,
        self::PLACEMENT_CART,
        self::PLACEMENT_CHECKOUT,
        self::PLACEMENT_SUCCESS,
        self::PLACEMENT_EMAIL,
    ];

    public const GARAN_PLACEMENTS = [
        self::PLACEMENT_PDP,
        self::PLACEMENT_CHECKOUT,
        self::PLACEMENT_SUCCESS,
        self::PLACEMENT_EMAIL,
    ];

    private const XML_PATH_LOCALE = 'general/locale/code';
    /**
     * Smallest QR code share of the notice width is 18.04 % (FR); 420 px keeps every QR >= 2 cm (75.6 CSS px).
     */
    private const DEFAULT_MIN_WIDTH_PX = 420;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LanguageRegistry $languageRegistry
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Module and GARAN label are both enabled for the store.
     */
    public function isGaranActive(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId)
            && $this->scopeConfig->isSetFlag(self::XML_PATH_GARAN_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Configured label language, or the language of the store locale, or English.
     */
    public function getLanguageCode(?int $storeId = null): string
    {
        $configured = $this->getString(self::XML_PATH_LANGUAGE, $storeId);
        if ($configured !== '' && $this->languageRegistry->isSupported($configured)) {
            return $configured;
        }

        return $this->languageRegistry->fromLocale($this->getString(self::XML_PATH_LOCALE, $storeId))
            ?? LanguageRegistry::FALLBACK_LANGUAGE;
    }

    /**
     * Empty string means "use the translated default text".
     */
    public function getTriggerText(?int $storeId = null): string
    {
        return $this->getString(self::XML_PATH_TRIGGER_TEXT, $storeId);
    }

    /**
     * Empty string means "use the translated default text".
     */
    public function getAltText(?int $storeId = null): string
    {
        return $this->getString(self::XML_PATH_ALT_TEXT, $storeId);
    }

    public function getMinWidthPx(?int $storeId = null): int
    {
        $value = $this->getString(self::XML_PATH_MIN_WIDTH_PX, $storeId);

        return ctype_digit($value) ? (int) $value : self::DEFAULT_MIN_WIDTH_PX;
    }

    /**
     * @return list<string>
     */
    public function getExcludedProductTypes(?int $storeId = null): array
    {
        $types = array_map('trim', explode(',', $this->getString(self::XML_PATH_EXCLUDED_PRODUCT_TYPES, $storeId)));

        return array_values(array_filter($types, static fn (string $type): bool => $type !== ''));
    }

    /**
     * Display mode of the legal guarantee notice; "off" whenever the module is disabled.
     */
    public function getNoticeMode(string $placement, ?int $storeId = null): string
    {
        $this->assertPlacement($placement, self::NOTICE_PLACEMENTS);
        if (!$this->isEnabled($storeId)) {
            return DisplayMode::OFF;
        }

        $path = self::XML_PATH_NOTICE_PLACEMENT_PREFIX . $placement;

        return $placement === self::PLACEMENT_EMAIL
            ? $this->getFlagMode($path, $storeId)
            : $this->normalizeMode($this->getString($path, $storeId));
    }

    /**
     * Display mode of the GARAN label; "off" whenever the module or the GARAN label is disabled.
     */
    public function getGaranMode(string $placement, ?int $storeId = null): string
    {
        $this->assertPlacement($placement, self::GARAN_PLACEMENTS);
        if (!$this->isGaranActive($storeId)) {
            return DisplayMode::OFF;
        }

        $path = self::XML_PATH_GARAN_PLACEMENT_PREFIX . $placement;

        return $placement === self::PLACEMENT_EMAIL
            ? $this->getFlagMode($path, $storeId)
            : $this->normalizeMode($this->getString($path, $storeId));
    }

    /**
     * The guarantee statement has to reach the consumer on a durable medium at the latest at delivery
     * (§ 9a (3) KSchG, § 479 (2) BGB); a link is not enough (CJEU C-49/11).
     */
    public function isTermsAttachmentEnabled(?int $storeId = null): bool
    {
        return $this->isGaranActive($storeId)
            && $this->scopeConfig->isSetFlag(
                self::XML_PATH_GARAN_ATTACH_TERMS,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
    }

    /**
     * File name as stored by the config file backend, relative to the upload directory. Empty when unset.
     */
    public function getTermsFileValue(?int $storeId = null): string
    {
        return $this->getString(self::XML_PATH_GARAN_TERMS_FILE, $storeId);
    }

    /**
     * File name shown in the email; empty string means "use the uploaded file name".
     */
    public function getTermsAttachmentFilename(?int $storeId = null): string
    {
        return $this->getString(self::XML_PATH_GARAN_TERMS_FILENAME, $storeId);
    }

    private function normalizeMode(string $mode): string
    {
        return in_array($mode, DisplayMode::MODES, true) ? $mode : DisplayMode::OFF;
    }

    /**
     * The email fields are yes/no: an email cannot open a dialog.
     */
    private function getFlagMode(string $path, ?int $storeId): string
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId)
            ? DisplayMode::DIRECT
            : DisplayMode::OFF;
    }

    /**
     * @param list<string> $allowed
     */
    private function assertPlacement(string $placement, array $allowed): void
    {
        if (!in_array($placement, $allowed, true)) {
            throw new InvalidArgumentException(sprintf('Unknown placement "%s".', $placement));
        }
    }

    private function getString(string $path, ?int $storeId): string
    {
        return trim((string) $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId));
    }
}
