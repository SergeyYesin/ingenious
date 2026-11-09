<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use Ramsey\Uuid\UuidInterface;

final class InvoiceNotFoundException extends InvoiceException
{
    public function __construct(UuidInterface $invoiceId)
    {
        parent::__construct("Invoice {$invoiceId->toString()} not found.");
    }
}
