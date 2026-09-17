<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Model\Checkout;

use CopeX\WarrantyLabel\Api\GaranLabelResolverInterface;
use CopeX\WarrantyLabel\Model\Config;
use CopeX\WarrantyLabel\Model\Language\LanguageRegistry;
use CopeX\WarrantyLabel\Model\Render\NoticeRenderer;
use CopeX\WarrantyLabel\Model\Source\DisplayMode;
use CopeX\WarrantyLabel\ViewModel\GaranLabelPresenter;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;
use stdClass;
use Throwable;

/**
 * Exposes window.checkoutConfig.copexWarrantyLabel for the checkout notice and GARAN label components.
 *
 * Failures of this module never break the checkout configuration: they are logged and hide the output.
 */
class ConfigProvider implements ConfigProviderInterface
{
    public const CONFIG_KEY = 'copexWarrantyLabel';

    public function __construct(
        private readonly Config $config,
        private readonly NoticeRenderer $noticeRenderer,
        private readonly CheckoutSession $checkoutSession,
        private readonly GaranLabelResolverInterface $resolver,
        private readonly GaranLabelPresenter $presenter,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Checkout configuration under the key copexWarrantyLabel.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getConfig(): array
    {
        $quote = $this->getQuote();
        $storeId = $quote !== null ? (int) $quote->getStoreId() : null;

        return [
            self::CONFIG_KEY => [
                'notice' => $this->getNoticeConfig($quote, $storeId),
                'garan' => $this->getGaranConfig($quote, $storeId),
            ],
        ];
    }

    /**
     * Notice data; URLs and texts are only resolved when the notice is actually shown.
     *
     * @param Quote|null $quote
     * @param int|null $storeId
     * @return array<string, mixed>
     */
    private function getNoticeConfig(?Quote $quote, ?int $storeId): array
    {
        try {
            $mode = $this->config->getNoticeMode(Config::PLACEMENT_CHECKOUT, $storeId);
            if ($mode === DisplayMode::OFF || $quote === null || !$this->hasEligibleItem($quote, $storeId)) {
                return $this->getHiddenNoticeConfig($mode);
            }

            return [
                'mode' => $mode,
                'visible' => true,
                'svgUrl' => $this->noticeRenderer->getSvgUrl($storeId),
                'altText' => $this->noticeRenderer->getAltText($storeId),
                'triggerText' => $this->noticeRenderer->getTriggerText($storeId),
                'linkUrl' => $this->noticeRenderer->getLinkUrl($storeId),
                'linkLabel' => $this->noticeRenderer->getLinkLabel($storeId),
                'minWidthPx' => $this->noticeRenderer->getMinWidthPx($storeId),
                'dialogLabel' => $this->noticeRenderer->getDialogLabel(),
                'closeLabel' => (string) __('Close'),
            ];
        } catch (Throwable $exception) {
            $this->logger->error('CopeX_WarrantyLabel: checkout notice could not be rendered.', [
                'exception' => $exception,
            ]);

            return $this->getHiddenNoticeConfig(DisplayMode::OFF);
        }
    }

    /**
     * Notice data of a hidden notice.
     *
     * @param string $mode
     * @return array<string, mixed>
     */
    private function getHiddenNoticeConfig(string $mode): array
    {
        return [
            'mode' => $mode,
            'visible' => false,
            'svgUrl' => '',
            'altText' => '',
            'triggerText' => '',
            'linkUrl' => '',
            'linkLabel' => '',
            'minWidthPx' => 0,
            'dialogLabel' => '',
            'closeLabel' => '',
        ];
    }

    /**
     * GARAN labels per visible quote item; itemsByQuoteItemId is always a JSON object.
     *
     * @param Quote|null $quote
     * @param int|null $storeId
     * @return array<string, mixed>
     */
    private function getGaranConfig(?Quote $quote, ?int $storeId): array
    {
        $mode = $this->config->getGaranMode(Config::PLACEMENT_CHECKOUT, $storeId);

        return [
            'mode' => $mode,
            'itemsByQuoteItemId' => $mode === DisplayMode::OFF || $quote === null
                ? new stdClass()
                : $this->getGaranItems($quote, $mode === DisplayMode::NESTED, $storeId),
            'infoUrl' => LanguageRegistry::GARAN_INFO_URL,
            'title' => (string) __('Producer guarantee (EU GARAN label)'),
            'infoLabel' => (string) __('Information on the EU guarantee label'),
            'termsLabel' => (string) __('Guarantee terms and conditions'),
            'closeLabel' => (string) __('Close'),
        ];
    }

    /**
     * Label values plus pngFull and pngNested URLs ('' = text fallback / direct mode) keyed by quote item id.
     *
     * @param Quote $quote
     * @param bool $withNested
     * @param int|null $storeId
     * @return stdClass
     */
    private function getGaranItems(Quote $quote, bool $withNested, ?int $storeId): stdClass
    {
        $items = [];
        try {
            foreach ($quote->getAllVisibleItems() as $item) {
                $labels = [];
                foreach ($this->resolver->forQuoteItem($item) as $label) {
                    $labels[] = $this->presenter->toViewData($label, $withNested, $storeId);
                }
                if ($labels !== []) {
                    $items[(string) $item->getId()] = $labels;
                }
            }
        } catch (Throwable $exception) {
            $this->logger->error('CopeX_WarrantyLabel: GARAN checkout labels could not be rendered.', [
                'quote_id' => $quote->getId(),
                'exception' => $exception,
            ]);

            return new stdClass();
        }

        return (object) $items;
    }

    /**
     * The notice is shown as soon as one item is not of an excluded product type (e.g. gift cards).
     *
     * @param Quote $quote
     * @param int|null $storeId
     * @return bool
     */
    private function hasEligibleItem(Quote $quote, ?int $storeId): bool
    {
        $excludedTypes = $this->config->getExcludedProductTypes($storeId);
        foreach ($quote->getAllVisibleItems() as $item) {
            if (!in_array((string) $item->getProductType(), $excludedTypes, true)) {
                return true;
            }
        }

        return false;
    }

    private function getQuote(): ?Quote
    {
        try {
            return $this->checkoutSession->getQuote();
        } catch (LocalizedException | NoSuchEntityException $exception) {
            $this->logger->warning('CopeX_WarrantyLabel: checkout quote unavailable.', ['exception' => $exception]);

            return null;
        }
    }
}
