<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Queries\Views;

final readonly class ProductLineView
{
    public function __construct(
        public string $id,
        public string $name,
        public int $quantity,
        public int $unitPrice,
        public int $totalUnitPrice,
    ) {}
}
