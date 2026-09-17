<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Plugin\Sales;

use CopeX\WarrantyLabel\Api\Data\GaranLabelDataInterface;
use CopeX\WarrantyLabel\Api\GaranLabelResolverInterface;
use CopeX\WarrantyLabel\Model\Config;
use CopeX\WarrantyLabel\Model\Email\PendingAttachments;
use CopeX\WarrantyLabel\Model\Language\LanguageRegistry;
use CopeX\WarrantyLabel\Model\Render\GaranPngRenderer;
use CopeX\WarrantyLabel\Model\Render\NoticeRenderer;
use CopeX\WarrantyLabel\Model\Source\DisplayMode;
use CopeX\WarrantyLabel\ViewModel\GaranLabelPresenter;
use Magento\Framework\Escaper;
use Magento\Sales\Block\Order\Email\Items;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Appends the legal guarantee notice and the EU GARAN labels below the items of the order confirmation email.
 *
 * Rendered in email-safe markup: tables, inline styles, PNG images, no CSS classes and no SVG.
 * The email already runs in store emulation, so no emulation is started here.
 */
class EmailItemsPlugin
{
    private const NOTICE_IMAGE_WIDTH = 560;
    private const GARAN_IMAGE_WIDTH = 270;

    public function __construct(
        private readonly Config $config,
        private readonly NoticeRenderer $noticeRenderer,
        private readonly GaranLabelResolverInterface $resolver,
        private readonly GaranPngRenderer $pngRenderer,
        private readonly GaranLabelPresenter $presenter,
        private readonly PendingAttachments $pendingAttachments,
        private readonly Escaper $escaper,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Append notice and GARAN labels; the confirmation email must never fail because of this module.
     *
     * @param Items $subject
     * @param string $result
     * @return string
     */
    public function afterToHtml(Items $subject, $result): string
    {
        $result = (string) $result;
        try {
            $order = $subject->getOrder();
            if (!$order instanceof Order) {
                return $result;
            }
            $storeId = (int) $order->getStoreId();

            return $result . $this->renderNotice($order, $storeId) . $this->renderGaranLabels($order, $storeId);
        } catch (Throwable $exception) {
            $this->logger->error('CopeX_WarrantyLabel: order email additions could not be rendered.', [
                'exception' => $exception,
            ]);

            return $result;
        }
    }

    private function renderNotice(Order $order, int $storeId): string
    {
        if ($this->config->getNoticeMode(Config::PLACEMENT_EMAIL, $storeId) === DisplayMode::OFF
            || !$this->hasEligibleItem($order, $storeId)
        ) {
            return '';
        }

        $linkUrl = $this->escaper->escapeUrl($this->noticeRenderer->getLinkUrl($storeId));

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"'
            . ' style="width:100%;max-width:' . self::NOTICE_IMAGE_WIDTH . 'px;'
            . 'margin:20px 0 0 0;border-collapse:collapse;">'
            . '<tr><td style="padding:10px 0 5px 0;">'
            . '<a href="' . $linkUrl . '" style="text-decoration:none;">'
            . '<img src="' . $this->escaper->escapeUrl($this->noticeRenderer->getPngUrl($storeId)) . '"'
            . ' width="' . self::NOTICE_IMAGE_WIDTH . '" border="0"'
            . ' style="width:100%;max-width:' . self::NOTICE_IMAGE_WIDTH . 'px;height:auto;display:block"'
            . ' alt="' . $this->escaper->escapeHtmlAttr($this->noticeRenderer->getAltText($storeId), false) . '">'
            . '</a></td></tr>'
            . '<tr><td style="padding:0 0 10px 0;">'
            . '<a href="' . $linkUrl . '">'
            . $this->escaper->escapeHtml($this->noticeRenderer->getLinkLabel($storeId))
            . '</a></td></tr></table>';
    }

    private function renderGaranLabels(Order $order, int $storeId): string
    {
        if ($this->config->getGaranMode(Config::PLACEMENT_EMAIL, $storeId) === DisplayMode::OFF) {
            return '';
        }

        $rows = '';
        foreach ($order->getAllVisibleItems() as $item) {
            $itemRows = '';
            foreach ($this->resolver->forOrderItem($item) as $label) {
                $itemRows .= $this->renderGaranLabel($label, (string) $item->getName(), $storeId);
            }
            if ($itemRows !== '') {
                $rows .= '<tr><td style="padding:15px 0 5px 0;font-weight:bold;">'
                    . $this->escaper->escapeHtml((string) $item->getName())
                    . '</td></tr>' . $itemRows;
            }
        }
        if ($rows === '') {
            return '';
        }

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"'
            . ' style="width:100%;margin:20px 0 0 0;border-collapse:collapse;">'
            . '<tr><td style="padding:0 0 5px 0;font-size:16px;font-weight:bold;">'
            . $this->escaper->escapeHtml((string) __('Producer guarantee (EU GARAN label)'))
            . '</td></tr>' . $rows . $this->renderTermsNote($storeId) . '</table>';
    }

    /**
     * Points at the attached guarantee terms, which reach the consumer on a durable medium with this email
     * (§ 9a (3) KSchG, § 479 (2) BGB). Shown only while a document is registered for this very email, so the sentence
     * can never promise an attachment that the observer failed to resolve.
     */
    private function renderTermsNote(int $storeId): string
    {
        if (!$this->config->isTermsAttachmentEnabled($storeId) || !$this->pendingAttachments->hasPending()) {
            return '';
        }

        return '<tr><td style="padding:5px 0 10px 0;">'
            . $this->escaper->escapeHtml((string) __('The guarantee terms of the producer are attached to this email.'))
            . '</td></tr>';
    }

    private function renderGaranLabel(GaranLabelDataInterface $label, string $itemName, int $storeId): string
    {
        $accessibleLabel = $this->presenter->getAccessibleLabel($label);

        $html = '';
        if ($label->getProductName() !== '' && $label->getProductName() !== $itemName) {
            $html .= '<tr><td style="padding:5px 0 0 0;">'
                . $this->escaper->escapeHtml($label->getProductName()) . '</td></tr>';
        }

        $pngUrl = $this->pngRenderer->getUrl($label, 'full', $storeId);
        if ($pngUrl !== null && $pngUrl !== '') {
            $html .= '<tr><td style="padding:5px 0;">'
                . '<img src="' . $this->escaper->escapeUrl($pngUrl) . '"'
                . ' width="' . self::GARAN_IMAGE_WIDTH . '" border="0"'
                . ' style="width:' . self::GARAN_IMAGE_WIDTH . 'px;max-width:100%;height:auto;display:block"'
                . ' alt="' . $this->escaper->escapeHtmlAttr($accessibleLabel, false) . '">'
                . '</td></tr>';
        } else {
            $html .= '<tr><td style="padding:5px 0;">' . $this->escaper->escapeHtml($accessibleLabel) . '</td></tr>';
        }

        $links = '<a href="' . $this->escaper->escapeUrl(LanguageRegistry::GARAN_INFO_URL) . '">'
            . $this->escaper->escapeHtml((string) __('Information on the EU guarantee label')) . '</a>';
        if ($label->getTermsUrl() !== '') {
            $links .= ' &middot; <a href="' . $this->escaper->escapeUrl($label->getTermsUrl()) . '">'
                . $this->escaper->escapeHtml((string) __('Guarantee terms and conditions')) . '</a>';
        }

        return $html . '<tr><td style="padding:0 0 10px 0;">' . $links . '</td></tr>';
    }

    private function hasEligibleItem(Order $order, int $storeId): bool
    {
        $excludedTypes = $this->config->getExcludedProductTypes($storeId);
        foreach ($order->getAllVisibleItems() as $item) {
            if (!in_array((string) $item->getProductType(), $excludedTypes, true)) {
                return true;
            }
        }

        return false;
    }
}
