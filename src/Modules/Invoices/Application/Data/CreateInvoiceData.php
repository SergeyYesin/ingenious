<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Data;

final readonly class CreateInvoiceData
{
    public array $productLines;

    public function __construct(
        public string $customerName,
        public string $customerEmail,
        ProductLineData ...$productLines,
    ) {
        $this->productLines = $productLines;
    }
}
