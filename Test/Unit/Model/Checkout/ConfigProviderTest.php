<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Test\Unit\Model\Checkout;

use CopeX\WarrantyLabel\Api\Data\GaranLabelDataInterface;
use CopeX\WarrantyLabel\Api\GaranLabelResolverInterface;
use CopeX\WarrantyLabel\Model\Checkout\ConfigProvider;
use CopeX\WarrantyLabel\Model\Config;
use CopeX\WarrantyLabel\Model\Language\LanguageRegistry;
use CopeX\WarrantyLabel\Model\Render\GaranPngRenderer;
use CopeX\WarrantyLabel\Model\Render\NoticeRenderer;
use CopeX\WarrantyLabel\Model\Source\DisplayMode;
use CopeX\WarrantyLabel\ViewModel\GaranLabelPresenter;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ConfigProviderTest extends TestCase
{
    private const STORE_ID = 3;

    private Config&MockObject $config;
    private NoticeRenderer&MockObject $noticeRenderer;
    private CheckoutSession&MockObject $checkoutSession;
    private GaranLabelResolverInterface&MockObject $resolver;
    private LoggerInterface&MockObject $logger;
    private ConfigProvider $provider;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->config->method('getExcludedProductTypes')
            ->with(self::STORE_ID)
            ->willReturn(['virtual', 'downloadable', 'mageworx_giftcards']);
        $this->noticeRenderer = $this->createMock(NoticeRenderer::class);
        $this->noticeRenderer->method('getSvgUrl')->willReturn('https://shop.test/notice/de.svg');
        $this->noticeRenderer->method('getAltText')->willReturn('Alt');
        $this->noticeRenderer->method('getTriggerText')->willReturn('Trigger');
        $this->noticeRenderer->method('getLinkUrl')->willReturn('https://europa.eu/youreurope/garantien');
        $this->noticeRenderer->method('getLinkLabel')->willReturn('europa.eu/youreurope/garantien');
        $this->noticeRenderer->method('getMinWidthPx')->willReturn(420);
        $this->noticeRenderer->method('getDialogLabel')->willReturn('EU legal guarantee notice');
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->resolver = $this->createMock(GaranLabelResolverInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $pngRenderer = $this->createMock(GaranPngRenderer::class);
        $pngRenderer->method('getUrl')->willReturnCallback(
            static fn (GaranLabelDataInterface $label, string $variant = 'full', ?int $storeId = null): ?string
                => sprintf('https://shop.test/media/garan/%s-%s-%d.png', $label->getBrand(), $variant, $storeId)
        );

        $this->provider = new ConfigProvider(
            $this->config,
            $this->noticeRenderer,
            $this->checkoutSession,
            $this->resolver,
            new GaranLabelPresenter($pngRenderer),
            $this->logger
        );
    }

    public function testGiftCardOnlyQuoteHidesNotice(): void
    {
        $this->givenModes(DisplayMode::DIRECT, DisplayMode::DIRECT);
        $this->givenQuoteWithItems([1 => 'mageworx_giftcards', 2 => 'virtual']);
        $this->noticeRenderer->expects($this->never())->method('getSvgUrl');

        $notice = $this->provider->getConfig()[ConfigProvider::CONFIG_KEY]['notice'];

        $this->assertSame(DisplayMode::DIRECT, $notice['mode']);
        $this->assertFalse($notice['visible']);
        $this->assertSame('', $notice['svgUrl']);
    }

    public function testMixedQuoteShowsNotice(): void
    {
        $this->givenModes(DisplayMode::NESTED, DisplayMode::OFF);
        $this->givenQuoteWithItems([1 => 'mageworx_giftcards', 2 => 'simple']);

        $this->assertSame([
            'mode' => DisplayMode::NESTED,
            'visible' => true,
            'svgUrl' => 'https://shop.test/notice/de.svg',
            'altText' => 'Alt',
            'triggerText' => 'Trigger',
            'linkUrl' => 'https://europa.eu/youreurope/garantien',
            'linkLabel' => 'europa.eu/youreurope/garantien',
            'minWidthPx' => 420,
            'dialogLabel' => 'EU legal guarantee notice',
            'closeLabel' => 'Close',
        ], $this->provider->getConfig()[ConfigProvider::CONFIG_KEY]['notice']);
    }

    public function testDisabledModuleReturnsOffAndEmptyGaranObject(): void
    {
        $this->givenModes(DisplayMode::OFF, DisplayMode::OFF);
        $this->givenQuoteWithItems([1 => 'simple']);
        $this->noticeRenderer->expects($this->never())->method('getSvgUrl');
        $this->resolver->expects($this->never())->method('forQuoteItem');

        $data = $this->provider->getConfig()[ConfigProvider::CONFIG_KEY];

        $this->assertSame(DisplayMode::OFF, $data['notice']['mode']);
        $this->assertFalse($data['notice']['visible']);
        $this->assertSame(DisplayMode::OFF, $data['garan']['mode']);
        $this->assertSame('{}', json_encode($data['garan']['itemsByQuoteItemId']));
    }

    public function testUnavailableQuoteHidesNoticeAndLogs(): void
    {
        $this->checkoutSession->method('getQuote')->willThrowException(new NoSuchEntityException());
        $this->config->method('getNoticeMode')->with(Config::PLACEMENT_CHECKOUT, null)->willReturn(DisplayMode::DIRECT);
        $this->config->method('getGaranMode')->with(Config::PLACEMENT_CHECKOUT, null)->willReturn(DisplayMode::DIRECT);
        $this->logger->expects($this->once())->method('warning');

        $data = $this->provider->getConfig()[ConfigProvider::CONFIG_KEY];

        $this->assertFalse($data['notice']['visible']);
        $this->assertSame('{}', json_encode($data['garan']['itemsByQuoteItemId']));
    }

    public function testGaranLabelsAreKeyedByQuoteItemId(): void
    {
        $this->givenModes(DisplayMode::DIRECT, DisplayMode::NESTED);
        $items = $this->givenQuoteWithItems([11 => 'bundle', 12 => 'simple']);
        $this->resolver->method('forQuoteItem')->willReturnMap([
            [$items[0], [$this->createLabel('Kettle', 'Brand'), $this->createLabel('Toaster', 'Other')]],
            [$items[1], []],
        ]);

        $garan = $this->provider->getConfig()[ConfigProvider::CONFIG_KEY]['garan'];
        $json = (string) json_encode($garan);
        $decoded = json_decode($json, true);

        $this->assertStringContainsString('"itemsByQuoteItemId":{"11":[', $json);
        $this->assertStringNotContainsString('<svg', $json);
        $this->assertSame([11], array_keys($decoded['itemsByQuoteItemId']));
        $this->assertCount(2, $decoded['itemsByQuoteItemId'][11]);
        $this->assertSame([
            'productName' => 'Kettle',
            'brand' => 'Brand',
            'model' => 'M 1',
            'years' => '5',
            'termsUrl' => 'https://brand.test/terms',
            'alt' => 'GARAN – producer guarantee 5 years, Brand M 1',
            'pngFull' => 'https://shop.test/media/garan/Brand-full-3.png',
            'pngNested' => 'https://shop.test/media/garan/Brand-nested-3.png',
        ], $decoded['itemsByQuoteItemId'][11][0]);
        $this->assertSame(DisplayMode::NESTED, $decoded['mode']);
        $this->assertSame(LanguageRegistry::GARAN_INFO_URL, $decoded['infoUrl']);
        $this->assertSame('Producer guarantee (EU GARAN label)', $decoded['title']);
        $this->assertSame('Guarantee terms and conditions', $decoded['termsLabel']);
    }

    public function testDirectGaranModeOmitsNestedImage(): void
    {
        $this->givenModes(DisplayMode::OFF, DisplayMode::DIRECT);
        $items = $this->givenQuoteWithItems([21 => 'simple']);
        $this->resolver->method('forQuoteItem')->with($items[0])->willReturn([$this->createLabel('Kettle', 'Brand')]);

        $decoded = json_decode(
            (string) json_encode($this->provider->getConfig()[ConfigProvider::CONFIG_KEY]['garan']),
            true
        );

        $this->assertSame('', $decoded['itemsByQuoteItemId'][21][0]['pngNested']);
        $this->assertSame('https://shop.test/media/garan/Brand-full-3.png', $decoded['itemsByQuoteItemId'][21][0]['pngFull']);
    }

    public function testGaranFailureYieldsEmptyObjectAndKeepsNotice(): void
    {
        $this->givenModes(DisplayMode::DIRECT, DisplayMode::DIRECT);
        $this->givenQuoteWithItems([31 => 'simple']);
        $this->resolver->method('forQuoteItem')->willThrowException(new RuntimeException('font missing'));
        $this->logger->expects($this->once())->method('error');

        $data = $this->provider->getConfig()[ConfigProvider::CONFIG_KEY];

        $this->assertTrue($data['notice']['visible']);
        $this->assertSame('{}', json_encode($data['garan']['itemsByQuoteItemId']));
    }

    public function testNoticeFailureIsLoggedAndOnlyHidesNotice(): void
    {
        $this->givenModes(DisplayMode::DIRECT, DisplayMode::DIRECT);
        $item = $this->createMock(QuoteItem::class);
        $item->method('getId')->willReturn('41');
        $item->method('getProductType')->willThrowException(new RuntimeException('broken product'));
        $quote = $this->createMock(Quote::class);
        $quote->method('getStoreId')->willReturn((string) self::STORE_ID);
        $quote->method('getAllVisibleItems')->willReturn([$item]);
        $this->checkoutSession->method('getQuote')->willReturn($quote);
        $this->resolver->method('forQuoteItem')->willReturn([$this->createLabel('Kettle', 'Brand')]);
        $this->logger->expects($this->once())->method('error');

        $data = $this->provider->getConfig()[ConfigProvider::CONFIG_KEY];

        $this->assertSame(DisplayMode::OFF, $data['notice']['mode']);
        $this->assertFalse($data['notice']['visible']);
        $this->assertSame('', $data['notice']['svgUrl']);
        $this->assertStringContainsString('{"41":[', (string) json_encode($data['garan']['itemsByQuoteItemId']));
    }

    private function givenModes(string $noticeMode, string $garanMode): void
    {
        $this->config->method('getNoticeMode')
            ->with(Config::PLACEMENT_CHECKOUT, self::STORE_ID)
            ->willReturn($noticeMode);
        $this->config->method('getGaranMode')
            ->with(Config::PLACEMENT_CHECKOUT, self::STORE_ID)
            ->willReturn($garanMode);
    }

    /**
     * @param array<int, string> $types quote item id => product type
     * @return list<QuoteItem&MockObject>
     */
    private function givenQuoteWithItems(array $types): array
    {
        $items = [];
        foreach ($types as $id => $type) {
            $item = $this->createMock(QuoteItem::class);
            $item->method('getId')->willReturn((string) $id);
            $item->method('getProductType')->willReturn($type);
            $items[] = $item;
        }
        $quote = $this->createMock(Quote::class);
        $quote->method('getStoreId')->willReturn((string) self::STORE_ID);
        $quote->method('getAllVisibleItems')->willReturn($items);
        $this->checkoutSession->method('getQuote')->willReturn($quote);

        return $items;
    }

    private function createLabel(string $productName, string $brand): GaranLabelDataInterface&MockObject
    {
        $label = $this->createMock(GaranLabelDataInterface::class);
        $label->method('getProductName')->willReturn($productName);
        $label->method('getBrand')->willReturn($brand);
        $label->method('getModelIdentifier')->willReturn('M 1');
        $label->method('getFormattedDuration')->willReturn('5');
        $label->method('getTermsUrl')->willReturn('https://brand.test/terms');

        return $label;
    }
}
