<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Entities\ProductLine;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Infrastructure\Persistence\Models\InvoiceModel;
use Modules\Invoices\Infrastructure\Persistence\Models\ProductLineModel;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class EloquentInvoiceRepository implements InvoiceRepositoryInterface
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function find(UuidInterface $id): ?Invoice
    {
        $model = InvoiceModel::query()->with('productLines')->find($id->toString());

        return $model === null ? null : $this->toDomain($model);
    }

    public function add(Invoice $invoice): void
    {
        $this->connection->transaction(static function () use ($invoice): void {
            InvoiceModel::query()->create([
                'id' => $invoice->id->toString(),
                'customer_name' => $invoice->customerName,
                'customer_email' => $invoice->customerEmail,
                'status' => $invoice->status->value,
            ]);

            foreach ($invoice->productLines as $line) {
                ProductLineModel::query()->create([
                    'id' => $line->id->toString(),
                    'invoice_id' => $invoice->id->toString(),
                    'name' => $line->name,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unitPrice,
                ]);
            }
        });
    }

    public function saveStatus(Invoice $invoice, StatusEnum $expectedStatus): void
    {
        $updated = InvoiceModel::query()
            ->whereKey($invoice->id->toString())
            ->where('status', $expectedStatus->value)
            ->update(['status' => $invoice->status->value]);

        if ($updated === 0) {
            throw new InvalidStatusTransitionException($invoice->id, $expectedStatus, $invoice->status);
        }
    }

    private function toDomain(InvoiceModel $invoice): Invoice
    {
        return Invoice::reconstitute(
            Uuid::fromString($invoice->id),
            $invoice->customer_name,
            $invoice->customer_email,
            StatusEnum::from($invoice->status),
            ...$invoice->productLines->map(
                static fn (ProductLineModel $line): ProductLine => new ProductLine(
                    id: Uuid::fromString($line->id),
                    name: $line->name,
                    quantity: $line->quantity,
                    unitPrice: $line->unit_price,
                ),
            )->all(),
        );
    }
}
