<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Observer;

use CopeX\WarrantyLabel\Api\GaranLabelResolverInterface;
use CopeX\WarrantyLabel\Model\Config;
use CopeX\WarrantyLabel\Model\Email\PendingAttachments;
use CopeX\WarrantyLabel\Model\Email\TermsDocument;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Registers the guarantee terms document for the order confirmation email.
 *
 * The producer's guarantee statement must reach the consumer on a durable medium at the latest at delivery
 * (§ 9a (3) KSchG, § 479 (2) BGB, Art. 17(2) Directive (EU) 2019/771); a link is not enough (CJEU C-49/11), so the
 * configured file is attached to the confirmation mail whenever the order contains a product with a GARAN label.
 *
 * "email_order_set_template_vars_before" is dispatched by Magento\Sales\Model\Order\Email\Sender\OrderSender only -
 * invoice, shipment, credit memo and comment senders each dispatch their own event - so no further restriction is
 * needed: the attachment cannot end up on any other sales email.
 *
 * Failures are logged and swallowed; the order confirmation must never fail because of this module.
 */
class RegisterTermsAttachment implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly GaranLabelResolverInterface $resolver,
        private readonly TermsDocument $termsDocument,
        private readonly PendingAttachments $pendingAttachments,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        try {
            $order = $this->getOrder($observer);
            if (!$order instanceof Order) {
                return;
            }

            $storeId = (int) $order->getStoreId();
            if (!$this->config->isTermsAttachmentEnabled($storeId) || !$this->hasGaranLabel($order)) {
                return;
            }

            $document = $this->termsDocument->resolve($storeId);
            if ($document === null) {
                return;
            }

            $this->pendingAttachments->add($document);
        } catch (Throwable $exception) {
            $this->logger->error(
                'CopeX_WarrantyLabel: guarantee terms attachment could not be registered.',
                ['exception' => $exception]
            );
        }
    }

    /**
     * The order carried by the transport object; "transport" is the deprecated alias of "transportObject".
     */
    private function getOrder(Observer $observer): ?Order
    {
        $transport = $observer->getData('transportObject') ?? $observer->getData('transport');
        if (!$transport instanceof DataObject) {
            return null;
        }

        $order = $transport->getData('order');

        return $order instanceof Order ? $order : null;
    }

    /**
     * Reads the GARAN snapshot written at order placement, for visible items only (children carry their own).
     */
    private function hasGaranLabel(Order $order): bool
    {
        foreach ($order->getAllVisibleItems() as $item) {
            if ($this->resolver->forOrderItem($item) !== []) {
                return true;
            }
        }

        return false;
    }
}
