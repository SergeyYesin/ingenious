<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Ramsey\Uuid\UuidInterface;

final class InvalidStatusTransitionException extends InvoiceException
{
    public function __construct(UuidInterface $invoiceId, StatusEnum $from, StatusEnum $to)
    {
        parent::__construct("Invoice {$invoiceId->toString()} cannot move from {$from->value} to {$to->value}.");
    }
}
