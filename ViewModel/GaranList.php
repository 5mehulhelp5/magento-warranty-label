<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\ViewModel;

use CopeX\WarrantyLabel\Api\Data\GaranLabelDataInterface;
use CopeX\WarrantyLabel\Api\GaranLabelResolverInterface;
use CopeX\WarrantyLabel\Model\Config;
use CopeX\WarrantyLabel\Model\Language\LanguageRegistry;
use CopeX\WarrantyLabel\Model\Source\DisplayMode;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * EU GARAN labels of the items of an order, by default the last order of the checkout session (success page).
 */
class GaranList implements ArgumentInterface
{
    private const DIALOG_ID_PREFIX = 'copex-wl-garan-';

    /**
     * @var list<array{name: string, labels: list<array<string, string>>}>|null
     */
    private ?array $entries = null;

    public function __construct(
        private readonly Config $config,
        private readonly CheckoutSession $checkoutSession,
        private readonly GaranLabelResolverInterface $resolver,
        private readonly GaranLabelPresenter $presenter,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Display mode of the label on the success page of the current store.
     *
     * @return string
     */
    public function getMode(): string
    {
        return $this->config->getGaranMode(Config::PLACEMENT_SUCCESS);
    }

    /**
     * True for both dialog modes: the full label lives in a dialog instead of the page.
     *
     * @return bool
     */
    public function isNested(): bool
    {
        return in_array($this->getMode(), [DisplayMode::NESTED, DisplayMode::DIALOG_ONLY], true);
    }

    /**
     * False in "dialog_only". The dialog ids on this page carry the order item id, so a shop placing its own
     * triggers here has to read them from the rendered markup.
     */
    public function hasTrigger(): bool
    {
        return $this->getMode() === DisplayMode::NESTED;
    }

    /**
     * Entries of the last order placed in this session.
     *
     * @return list<array{name: string, labels: list<array<string, string>>}>
     */
    public function getEntries(): array
    {
        if ($this->entries === null) {
            $this->entries = $this->getEntriesForOrder($this->checkoutSession->getLastRealOrder());
        }

        return $this->entries;
    }

    /**
     * One entry per visible order item with at least one label.
     *
     * Each label provides the label values, alt, pngFull, pngNested ('' = text fallback / direct mode) and dialogId.
     *
     * @param Order $order
     * @return list<array{name: string, labels: list<array<string, string>>}>
     */
    public function getEntriesForOrder(Order $order): array
    {
        if (!$order->getId()) {
            return [];
        }
        $storeId = (int) $order->getStoreId();
        $mode = $this->config->getGaranMode(Config::PLACEMENT_SUCCESS, $storeId);
        if ($mode === DisplayMode::OFF) {
            return [];
        }

        $entries = [];
        try {
            foreach ($order->getAllVisibleItems() as $item) {
                $labels = [];
                foreach ($this->resolver->forOrderItem($item) as $index => $label) {
                    $data = $this->presenter->toViewData($label, $mode === DisplayMode::NESTED, $storeId);
                    $data['dialogId'] = self::DIALOG_ID_PREFIX . (int) $item->getId() . '-' . $index;
                    $labels[] = $data;
                }
                if ($labels !== []) {
                    $entries[] = ['name' => (string) $item->getName(), 'labels' => $labels];
                }
            }
        } catch (Throwable $exception) {
            $this->logger->error('CopeX_WarrantyLabel: GARAN list could not be rendered.', [
                'order_id' => $order->getId(),
                'exception' => $exception,
            ]);

            return [];
        }

        return $entries;
    }

    /**
     * Link target of the QR code on the label.
     *
     * @return string
     */
    public function getInfoUrl(): string
    {
        return LanguageRegistry::GARAN_INFO_URL;
    }

    /**
     * Whether the info link opens in a new tab.
     *
     * @return bool
     */
    public function isLinkNewTab(): bool
    {
        return $this->config->isLinkNewTab();
    }

    /**
     * Accessible text of a label.
     *
     * @param GaranLabelDataInterface $label
     * @return string
     */
    public function getAccessibleLabel(GaranLabelDataInterface $label): string
    {
        return $this->presenter->getAccessibleLabel($label);
    }
}
