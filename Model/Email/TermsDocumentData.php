<?php

declare(strict_types=1);

namespace CopeX\WarrantyLabel\Model\Email;

/**
 * Immutable description of one file that is attached to an order email.
 *
 * The content is held in memory on purpose: the attachment has to survive even if the configured file is replaced
 * between resolving and sending, and Symfony renders the part from the string without touching the file again.
 */
class TermsDocumentData
{
    public function __construct(
        private readonly string $name,
        private readonly string $content,
        private readonly string $mimeType
    ) {
    }

    /**
     * File name the customer sees in the email client.
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }
}
