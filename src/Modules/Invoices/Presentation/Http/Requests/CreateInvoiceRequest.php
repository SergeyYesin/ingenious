<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Invoices\Application\Data\CreateInvoiceData;
use Modules\Invoices\Application\Data\ProductLineData;
use Modules\Invoices\Domain\ValueObjects\CustomerName;
use Modules\Invoices\Domain\ValueObjects\Email;
use Modules\Invoices\Domain\ValueObjects\ProductName;

final class CreateInvoiceRequest extends FormRequest
{
    private const int MAX_DB_INTEGER = 2147483647;

    private const int MAX_QUANTITY = 1000000;

    private const int MAX_PRODUCT_LINES = 100;

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:'.CustomerName::MAX_LENGTH],
            'customer_email' => ['required', 'string', 'email:filter', 'max:'.Email::MAX_LENGTH],
            'product_lines' => ['sometimes', 'array', 'max:'.self::MAX_PRODUCT_LINES],
            'product_lines.*' => ['array'],
            'product_lines.*.name' => ['required', 'string', 'max:'.ProductName::MAX_LENGTH],
            'product_lines.*.quantity' => ['required', 'integer:strict', 'min:0', 'max:'.self::MAX_QUANTITY],
            'product_lines.*.unit_price' => ['required', 'integer:strict', 'min:0', 'max:'.self::MAX_DB_INTEGER],
        ];
    }

    public function toData(): CreateInvoiceData
    {
        return new CreateInvoiceData(
            $this->string('customer_name')->toString(),
            $this->string('customer_email')->toString(),
            ...array_map(
                static fn (array $productLine): ProductLineData => new ProductLineData(
                    name: (string) $productLine['name'],
                    quantity: (int) $productLine['quantity'],
                    unitPrice: (int) $productLine['unit_price'],
                ),
                array_values($this->validated('product_lines', [])),
            ),
        );
    }
}
