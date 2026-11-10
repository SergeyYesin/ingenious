<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Invoices\Application\Queries\Views\InvoiceView;
use Modules\Invoices\Application\Queries\Views\ProductLineView;

final class InvoiceResource extends JsonResource
{
    public function __construct(
        private readonly InvoiceView $invoice,
    ) {
        parent::__construct($invoice);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->invoice->id,
            'status' => $this->invoice->status,
            'customer_name' => $this->invoice->customerName,
            'customer_email' => $this->invoice->customerEmail,
            'product_lines' => array_map(
                static fn (ProductLineView $line): array => [
                    'id' => $line->id,
                    'name' => $line->name,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unitPrice,
                    'total_unit_price' => $line->totalUnitPrice,
                ],
                $this->invoice->productLines,
            ),
            'total_price' => $this->invoice->totalPrice,
        ];
    }
}
