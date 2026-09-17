<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Test\Unit\Observer;

use CopeX\WarrantyLabel\Api\Data\GaranLabelDataInterface;
use CopeX\WarrantyLabel\Api\GaranLabelResolverInterface;
use CopeX\WarrantyLabel\Model\Config;
use CopeX\WarrantyLabel\Model\Email\PendingAttachments;
use CopeX\WarrantyLabel\Model\Email\TermsDocument;
use CopeX\WarrantyLabel\Model\Email\TermsDocumentData;
use CopeX\WarrantyLabel\Observer\RegisterTermsAttachment;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class RegisterTermsAttachmentTest extends TestCase
{
    private const STORE_ID = 5;

    private Config&MockObject $config;
    private GaranLabelResolverInterface&MockObject $resolver;
    private TermsDocument&MockObject $termsDocument;
    private PendingAttachments $pendingAttachments;
    private LoggerInterface&MockObject $logger;
    private RegisterTermsAttachment $observer;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->resolver = $this->createMock(GaranLabelResolverInterface::class);
        $this->termsDocument = $this->createMock(TermsDocument::class);
        $this->pendingAttachments = new PendingAttachments();
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->observer = new RegisterTermsAttachment(
            $this->config,
            $this->resolver,
            $this->termsDocument,
            $this->pendingAttachments,
            $this->logger
        );
    }

    public function testDocumentIsRegisteredForAnOrderWithGaranLabel(): void
    {
        $document = new TermsDocumentData('Garantiebedingungen.pdf', '%PDF', 'application/pdf');
        $this->config->method('isTermsAttachmentEnabled')->with(self::STORE_ID)->willReturn(true);
        $this->resolver->method('forOrderItem')->willReturn([$this->createMock(GaranLabelDataInterface::class)]);
        $this->termsDocument->method('resolve')->with(self::STORE_ID)->willReturn($document);

        $this->observer->execute($this->createObserver('transportObject'));

        $this->assertSame([$document], $this->pendingAttachments->takeAll());
    }

    public function testDeprecatedTransportKeyIsAccepted(): void
    {
        $document = new TermsDocumentData('Garantiebedingungen.pdf', '%PDF', 'application/pdf');
        $this->config->method('isTermsAttachmentEnabled')->willReturn(true);
        $this->resolver->method('forOrderItem')->willReturn([$this->createMock(GaranLabelDataInterface::class)]);
        $this->termsDocument->method('resolve')->willReturn($document);

        $this->observer->execute($this->createObserver('transport'));

        $this->assertTrue($this->pendingAttachments->hasPending());
    }

    public function testMissingTransportObjectIsIgnored(): void
    {
        $this->config->expects($this->never())->method('isTermsAttachmentEnabled');

        $this->observer->execute(new Observer());

        $this->assertFalse($this->pendingAttachments->hasPending());
    }

    public function testTransportWithoutOrderIsIgnored(): void
    {
        $this->config->expects($this->never())->method('isTermsAttachmentEnabled');

        $this->observer->execute(new Observer(['transportObject' => new DataObject(['order_id' => 7])]));

        $this->assertFalse($this->pendingAttachments->hasPending());
    }

    public function testDisabledAttachmentRegistersNothing(): void
    {
        $this->config->method('isTermsAttachmentEnabled')->willReturn(false);
        $this->resolver->expects($this->never())->method('forOrderItem');
        $this->termsDocument->expects($this->never())->method('resolve');

        $this->observer->execute($this->createObserver('transportObject'));

        $this->assertFalse($this->pendingAttachments->hasPending());
    }

    public function testOrderWithoutGaranLabelsRegistersNothing(): void
    {
        $this->config->method('isTermsAttachmentEnabled')->willReturn(true);
        $this->resolver->method('forOrderItem')->willReturn([]);
        $this->termsDocument->expects($this->never())->method('resolve');

        $this->observer->execute($this->createObserver('transportObject'));

        $this->assertFalse($this->pendingAttachments->hasPending());
    }

    public function testUnresolvableDocumentRegistersNothing(): void
    {
        $this->config->method('isTermsAttachmentEnabled')->willReturn(true);
        $this->resolver->method('forOrderItem')->willReturn([$this->createMock(GaranLabelDataInterface::class)]);
        $this->termsDocument->method('resolve')->willReturn(null);

        $this->observer->execute($this->createObserver('transportObject'));

        $this->assertFalse($this->pendingAttachments->hasPending());
    }

    public function testExceptionIsLoggedAndSwallowed(): void
    {
        $this->config->method('isTermsAttachmentEnabled')->willReturn(true);
        $this->resolver->method('forOrderItem')->willThrowException(new RuntimeException('snapshot broken'));
        $this->logger->expects($this->once())->method('error');

        $this->observer->execute($this->createObserver('transportObject'));

        $this->assertFalse($this->pendingAttachments->hasPending());
    }

    private function createObserver(string $transportKey): Observer
    {
        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn((string) self::STORE_ID);
        $order->method('getAllVisibleItems')->willReturn([$this->createMock(OrderItem::class)]);

        return new Observer([$transportKey => new DataObject(['order' => $order])]);
    }
}
