<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Exceptions;

use Modules\Invoices\Domain\Exceptions\InvoiceException;
use Ramsey\Uuid\UuidInterface;
use Throwable;

final class InvoiceNotificationFailedException extends InvoiceException
{
    public function __construct(UuidInterface $invoiceId, Throwable $previous)
    {
        parent::__construct(
            message: "Notification for invoice {$invoiceId->toString()} could not be queued, check the invoice status before retrying.",
            previous: $previous,
        );
    }
}
