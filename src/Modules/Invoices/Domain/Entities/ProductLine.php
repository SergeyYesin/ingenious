<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Entities;

use Modules\Invoices\Domain\Exceptions\InvalidInvoiceException;
use Ramsey\Uuid\UuidInterface;

final readonly class ProductLine
{
    public function __construct(
        public UuidInterface $id,
        public string $name,
        public int $quantity,
        public int $unitPrice,
    ) {
        if (trim($name) === '') {
            throw new InvalidInvoiceException('Product line name must not be empty.');
        }

        if ($quantity < 0) {
            throw new InvalidInvoiceException("Product line quantity must not be negative, {$quantity} given.");
        }

        if ($unitPrice < 0) {
            throw new InvalidInvoiceException("Product line unit price must not be negative, {$unitPrice} given.");
        }

        if (! is_int($quantity * $unitPrice)) {
            throw new InvalidInvoiceException('Product line total exceeds the supported maximum.');
        }
    }

    public function totalUnitPrice(): int
    {
        return $this->quantity * $this->unitPrice;
    }

    public function isBillable(): bool
    {
        return $this->quantity > 0 && $this->unitPrice > 0;
    }
}
