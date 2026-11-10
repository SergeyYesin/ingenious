<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Ramsey\Uuid\UuidInterface;

final class InvoiceNotSendingException extends InvoiceException
{
    public function __construct(UuidInterface $invoiceId, StatusEnum $status)
    {
        parent::__construct("Invoice {$invoiceId->toString()} is {$status->value}, only an invoice being sent can be notified.");
    }
}
