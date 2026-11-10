<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Entities\ProductLine;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Events\InvoiceSendingStarted;
use Modules\Invoices\Domain\Exceptions\ConcurrentModificationException;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Domain\ValueObjects\CustomerName;
use Modules\Invoices\Domain\ValueObjects\Email;
use Modules\Invoices\Domain\ValueObjects\Money;
use Modules\Invoices\Domain\ValueObjects\ProductName;
use Modules\Invoices\Domain\ValueObjects\Quantity;
use Modules\Invoices\Infrastructure\Outbox\InvoiceOutbox;
use Modules\Invoices\Infrastructure\Persistence\Models\InvoiceModel;
use Modules\Invoices\Infrastructure\Persistence\Models\ProductLineModel;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use WeakMap;

final class EloquentInvoiceRepository implements InvoiceRepositoryInterface
{
    private WeakMap $versions;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly InvoiceOutbox $outbox,
    ) {
        $this->versions = new WeakMap;
    }

    public function find(UuidInterface $id): ?Invoice
    {
        $model = InvoiceModel::query()->with('productLines')->find($id->toString());

        if ($model === null) {
            return null;
        }

        $invoice = $this->toDomain($model);
        $this->versions[$invoice] = $model->version;

        return $invoice;
    }

    public function save(Invoice $invoice): void
    {
        $version = $this->versions[$invoice] ?? null;

        $this->connection->transaction(function () use ($invoice, $version): void {
            if ($version === null) {
                $this->insert($invoice);
            } else {
                $this->update($invoice, $version);
            }

            foreach ($invoice->recordedEvents() as $event) {
                $this->appendToOutbox($event);
            }
        });

        $invoice->clearRecordedEvents();
        $this->versions[$invoice] = ($version ?? 0) + 1;
    }

    private function insert(Invoice $invoice): void
    {
        InvoiceModel::query()->create([
            'id' => $invoice->id->toString(),
            'customer_name' => $invoice->customerName->value,
            'customer_email' => $invoice->customerEmail->value,
            'status' => $invoice->status->value,
            'version' => 1,
        ]);

        foreach ($invoice->productLines as $line) {
            ProductLineModel::query()->create([
                'id' => $line->id->toString(),
                'invoice_id' => $invoice->id->toString(),
                'name' => $line->name->value,
                'quantity' => $line->quantity->value,
                'unit_price' => $line->unitPrice->amount,
            ]);
        }
    }

    private function update(Invoice $invoice, int $version): void
    {
        $updated = InvoiceModel::query()
            ->whereKey($invoice->id->toString())
            ->where('version', $version)
            ->update([
                'status' => $invoice->status->value,
                'version' => $version + 1,
            ]);

        if ($updated === 0) {
            throw new ConcurrentModificationException($invoice->id);
        }
    }

    private function appendToOutbox(InvoiceSendingStarted $event): void
    {
        $this->outbox->append(InvoiceOutbox::SENDING_STARTED, $event->invoiceId);
    }

    private function toDomain(InvoiceModel $invoice): Invoice
    {
        return Invoice::reconstitute(
            Uuid::fromString($invoice->id),
            new CustomerName($invoice->customer_name),
            new Email($invoice->customer_email),
            StatusEnum::from($invoice->status),
            ...$invoice->productLines->map(
                static fn (ProductLineModel $line): ProductLine => new ProductLine(
                    id: Uuid::fromString($line->id),
                    name: new ProductName($line->name),
                    quantity: new Quantity($line->quantity),
                    unitPrice: new Money($line->unit_price),
                ),
            )->all(),
        );
    }
}
