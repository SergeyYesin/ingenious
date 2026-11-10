<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\ValueObjects;

use Modules\Invoices\Domain\Exceptions\InvalidInvoiceException;

final readonly class Quantity
{
    public function __construct(
        public int $value,
    ) {
        if ($value < 0) {
            throw new InvalidInvoiceException("Quantity must not be negative, {$value} given.");
        }
    }

    public function isPositive(): bool
    {
        return $this->value > 0;
    }
}
