<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Test\Unit\Model\Email;

use CopeX\WarrantyLabel\Model\Email\PendingAttachments;
use CopeX\WarrantyLabel\Model\Email\TermsDocumentData;
use PHPUnit\Framework\TestCase;

class PendingAttachmentsTest extends TestCase
{
    private PendingAttachments $registry;

    protected function setUp(): void
    {
        $this->registry = new PendingAttachments();
    }

    public function testEmptyRegistryHasNothingPending(): void
    {
        $this->assertFalse($this->registry->hasPending());
        $this->assertSame([], $this->registry->takeAll());
    }

    public function testAddedDocumentsAreReturnedInOrder(): void
    {
        $first = new TermsDocumentData('a.pdf', 'A', 'application/pdf');
        $second = new TermsDocumentData('b.pdf', 'B', 'application/pdf');
        $this->registry->add($first);
        $this->registry->add($second);

        $this->assertTrue($this->registry->hasPending());
        $this->assertSame([$first, $second], $this->registry->takeAll());
    }

    public function testTakeAllEmptiesTheRegistry(): void
    {
        $this->registry->add(new TermsDocumentData('a.pdf', 'A', 'application/pdf'));
        $this->registry->takeAll();

        $this->assertFalse($this->registry->hasPending());
        $this->assertSame([], $this->registry->takeAll());
    }

    public function testClearDropsPendingDocuments(): void
    {
        $this->registry->add(new TermsDocumentData('a.pdf', 'A', 'application/pdf'));
        $this->registry->clear();

        $this->assertFalse($this->registry->hasPending());
        $this->assertSame([], $this->registry->takeAll());
    }
}
