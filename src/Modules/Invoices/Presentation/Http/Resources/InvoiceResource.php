<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Entities\ProductLine;

final class InvoiceResource extends JsonResource
{
    public function __construct(
        private readonly Invoice $invoice,
    ) {
        parent::__construct($invoice);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->invoice->id->toString(),
            'status' => $this->invoice->status->value,
            'customer_name' => $this->invoice->customerName,
            'customer_email' => $this->invoice->customerEmail,
            'product_lines' => array_map(
                fn (ProductLine $line): array => [
                    'id' => $line->id->toString(),
                    'name' => $line->name,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unitPrice,
                    'total_unit_price' => $line->totalUnitPrice(),
                ],
                $this->invoice->productLines,
            ),
            'total_price' => $this->invoice->totalPrice,
        ];
    }
}
