<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\ValueObjects;

use Ramsey\Uuid\UuidInterface;

final readonly class InvoiceNotification
{
    public function __construct(
        public UuidInterface $invoiceId,
        public Email $recipient,
        public string $subject,
        public string $message,
    ) {}
}
