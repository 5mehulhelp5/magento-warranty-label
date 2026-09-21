<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Test\Unit\Model;

use CopeX\WarrantyLabel\Model\Config;
use CopeX\WarrantyLabel\Model\Language\LanguageRegistry;
use CopeX\WarrantyLabel\Model\Source\DisplayMode;
use InvalidArgumentException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    /**
     * @var array<string, string|null>
     */
    private array $values = [];

    private Config $config;

    protected function setUp(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            fn (string $path): ?string => $this->values[$path] ?? null
        );
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            fn (string $path): bool => ($this->values[$path] ?? '0') === '1'
        );

        $this->config = new Config($scopeConfig, new LanguageRegistry());
    }

    public function testNoticeModeIsOffWhenModuleDisabled(): void
    {
        $this->values = [
            Config::XML_PATH_ENABLED => '0',
            Config::XML_PATH_NOTICE_PLACEMENT_PREFIX . 'checkout' => DisplayMode::DIRECT,
        ];

        self::assertSame(DisplayMode::OFF, $this->config->getNoticeMode(Config::PLACEMENT_CHECKOUT, 1));
    }

    public function testNoticeModeReturnsConfiguredMode(): void
    {
        $this->values = [
            Config::XML_PATH_ENABLED => '1',
            Config::XML_PATH_NOTICE_PLACEMENT_PREFIX . 'header' => DisplayMode::NESTED,
            Config::XML_PATH_NOTICE_PLACEMENT_PREFIX . 'checkout' => DisplayMode::DIRECT,
        ];

        self::assertSame(DisplayMode::NESTED, $this->config->getNoticeMode(Config::PLACEMENT_HEADER, 1));
        self::assertSame(DisplayMode::DIRECT, $this->config->getNoticeMode(Config::PLACEMENT_CHECKOUT, 1));
    }

    public function testEmailPlacementReadsYesNo(): void
    {
        $this->values = [
            Config::XML_PATH_ENABLED => '1',
            Config::XML_PATH_NOTICE_PLACEMENT_PREFIX . 'email' => '1',
        ];

        self::assertSame(DisplayMode::DIRECT, $this->config->getNoticeMode(Config::PLACEMENT_EMAIL, 1));

        $this->values[Config::XML_PATH_NOTICE_PLACEMENT_PREFIX . 'email'] = '0';

        self::assertSame(DisplayMode::OFF, $this->config->getNoticeMode(Config::PLACEMENT_EMAIL, 1));
    }

    /**
     * Installations configured before the email fields became yes/no still hold a display mode here.
     */
    public function testEmailPlacementKeepsReadingLegacyModes(): void
    {
        $this->values = [
            Config::XML_PATH_ENABLED => '1',
            Config::XML_PATH_NOTICE_PLACEMENT_PREFIX . 'email' => DisplayMode::NESTED,
        ];

        self::assertSame(DisplayMode::DIRECT, $this->config->getNoticeMode(Config::PLACEMENT_EMAIL, 1));

        $this->values[Config::XML_PATH_NOTICE_PLACEMENT_PREFIX . 'email'] = DisplayMode::OFF;

        self::assertSame(DisplayMode::OFF, $this->config->getNoticeMode(Config::PLACEMENT_EMAIL, 1));
    }

    public function testUnknownStoredModeFallsBackToOff(): void
    {
        $this->values = [
            Config::XML_PATH_ENABLED => '1',
            Config::XML_PATH_NOTICE_PLACEMENT_PREFIX . 'footer' => 'sometimes',
        ];

        self::assertSame(DisplayMode::OFF, $this->config->getNoticeMode(Config::PLACEMENT_FOOTER, 1));
    }

    public function testUnknownNoticePlacementThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->config->getNoticeMode(Config::PLACEMENT_PDP, 1);
    }

    public function testGaranModeRequiresModuleAndGaranEnabled(): void
    {
        $this->values = [
            Config::XML_PATH_ENABLED => '1',
            Config::XML_PATH_GARAN_ENABLED => '0',
            Config::XML_PATH_GARAN_PLACEMENT_PREFIX . 'pdp' => DisplayMode::NESTED,
        ];
        self::assertFalse($this->config->isGaranActive(1));
        self::assertSame(DisplayMode::OFF, $this->config->getGaranMode(Config::PLACEMENT_PDP, 1));

        $this->values[Config::XML_PATH_GARAN_ENABLED] = '1';
        self::assertTrue($this->config->isGaranActive(1));
        self::assertSame(DisplayMode::NESTED, $this->config->getGaranMode(Config::PLACEMENT_PDP, 1));

        $this->values[Config::XML_PATH_ENABLED] = '0';
        self::assertFalse($this->config->isGaranActive(1));
    }

    public function testUnknownGaranPlacementThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->config->getGaranMode(Config::PLACEMENT_HEADER, 1);
    }

    public function testConfiguredLanguageWinsOverLocale(): void
    {
        $this->values = [
            Config::XML_PATH_LANGUAGE => 'en',
            'general/locale/code' => 'de_AT',
        ];

        self::assertSame('en', $this->config->getLanguageCode(21));
    }

    public function testLanguageFallsBackToStoreLocale(): void
    {
        $this->values = [
            Config::XML_PATH_LANGUAGE => '',
            'general/locale/code' => 'de_AT',
        ];

        self::assertSame('de', $this->config->getLanguageCode(1));
    }

    public function testLanguageFallsBackToEnglishForNonEuLocaleOrInvalidConfig(): void
    {
        $this->values = [
            Config::XML_PATH_LANGUAGE => 'xx',
            'general/locale/code' => 'tr_TR',
        ];

        self::assertSame(LanguageRegistry::FALLBACK_LANGUAGE, $this->config->getLanguageCode(1));
    }

    public function testExcludedProductTypesAreTrimmedAndEmptyEntriesRemoved(): void
    {
        $this->values = [
            Config::XML_PATH_EXCLUDED_PRODUCT_TYPES => ' virtual, ,downloadable,mageworx_giftcards ',
        ];

        self::assertSame(
            ['virtual', 'downloadable', 'mageworx_giftcards'],
            $this->config->getExcludedProductTypes(1)
        );
    }

    public function testExcludedProductTypesEmptyWhenNotConfigured(): void
    {
        self::assertSame([], $this->config->getExcludedProductTypes(1));
    }

    public function testMinWidthUsesDefaultForInvalidValues(): void
    {
        $this->values = [Config::XML_PATH_MIN_WIDTH_PX => '480'];
        self::assertSame(480, $this->config->getMinWidthPx(1));

        $this->values = [Config::XML_PATH_MIN_WIDTH_PX => '-5'];
        self::assertSame(420, $this->config->getMinWidthPx(1));

        $this->values = [];
        self::assertSame(420, $this->config->getMinWidthPx(1));
    }

    public function testTextsAreTrimmedAndEmptyWhenUnset(): void
    {
        $this->values = [Config::XML_PATH_TRIGGER_TEXT => '  Deine Gewährleistungsrechte '];

        self::assertSame('Deine Gewährleistungsrechte', $this->config->getTriggerText(1));
        self::assertSame('', $this->config->getAltText(1));
    }
}
