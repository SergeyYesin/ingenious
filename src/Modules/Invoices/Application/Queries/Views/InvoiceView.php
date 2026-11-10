<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Queries\Views;

final readonly class InvoiceView
{
    public array $productLines;

    public function __construct(
        public string $id,
        public string $status,
        public string $customerName,
        public string $customerEmail,
        public int $totalPrice,
        ProductLineView ...$productLines,
    ) {
        $this->productLines = $productLines;
    }
}
