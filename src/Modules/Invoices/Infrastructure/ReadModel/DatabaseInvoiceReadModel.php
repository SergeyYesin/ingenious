<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\ReadModel;

use Illuminate\Database\ConnectionInterface;
use Modules\Invoices\Application\Queries\InvoiceReadModelInterface;
use Modules\Invoices\Application\Queries\Views\InvoiceView;
use Modules\Invoices\Application\Queries\Views\ProductLineView;
use Ramsey\Uuid\UuidInterface;

final readonly class DatabaseInvoiceReadModel implements InvoiceReadModelInterface
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function find(UuidInterface $id): ?InvoiceView
    {
        $invoice = $this->connection->table('invoices')
            ->where('id', $id->toString())
            ->first(['id', 'status', 'customer_name', 'customer_email']);

        if ($invoice === null) {
            return null;
        }

        $lines = $this->connection->table('invoice_product_lines')
            ->where('invoice_id', $invoice->id)
            ->orderBy('id')
            ->get(['id', 'name', 'quantity', 'unit_price'])
            ->map(static fn (object $line): ProductLineView => new ProductLineView(
                id: $line->id,
                name: $line->name,
                quantity: (int) $line->quantity,
                unitPrice: (int) $line->unit_price,
                totalUnitPrice: (int) $line->quantity * (int) $line->unit_price,
            ))
            ->all();

        return new InvoiceView(
            $invoice->id,
            $invoice->status,
            $invoice->customer_name,
            $invoice->customer_email,
            array_sum(array_map(static fn (ProductLineView $line): int => $line->totalUnitPrice, $lines)),
            ...$lines,
        );
    }
}
