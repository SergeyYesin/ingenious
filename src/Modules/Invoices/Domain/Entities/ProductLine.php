<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Entities;

use Modules\Invoices\Domain\ValueObjects\Money;
use Modules\Invoices\Domain\ValueObjects\ProductName;
use Modules\Invoices\Domain\ValueObjects\Quantity;
use Ramsey\Uuid\UuidInterface;

final readonly class ProductLine
{
    public Money $totalUnitPrice;

    public function __construct(
        public UuidInterface $id,
        public ProductName $name,
        public Quantity $quantity,
        public Money $unitPrice,
    ) {
        $this->totalUnitPrice = $unitPrice->multiply($quantity);
    }

    public function isBillable(): bool
    {
        return $this->quantity->isPositive() && $this->unitPrice->isPositive();
    }
}
