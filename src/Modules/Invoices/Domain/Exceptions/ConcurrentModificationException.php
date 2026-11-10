<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use Ramsey\Uuid\UuidInterface;

final class ConcurrentModificationException extends InvoiceException
{
    public function __construct(UuidInterface $invoiceId)
    {
        parent::__construct("Invoice {$invoiceId->toString()} was changed by another request, reload it and try again.");
    }
}
