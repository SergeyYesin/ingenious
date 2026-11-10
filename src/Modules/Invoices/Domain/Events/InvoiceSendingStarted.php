<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Events;

use Ramsey\Uuid\UuidInterface;

final readonly class InvoiceSendingStarted
{
    public function __construct(
        public UuidInterface $invoiceId,
    ) {}
}
