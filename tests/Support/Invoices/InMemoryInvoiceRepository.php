<?php

declare(strict_types=1);

namespace Tests\Support\Invoices;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Exceptions\ConcurrentModificationException;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Ramsey\Uuid\UuidInterface;
use WeakMap;

final class InMemoryInvoiceRepository implements InvoiceRepositoryInterface
{
    public array $publishedEvents = [];

    private array $stored = [];

    private WeakMap $versions;

    private bool $conflictOnNextSave = false;

    public function __construct()
    {
        $this->versions = new WeakMap;
    }

    public function seed(Invoice ...$invoices): self
    {
        foreach ($invoices as $invoice) {
            $this->stored[$invoice->id->toString()] = ['invoice' => $this->copy($invoice), 'version' => 1];
        }

        return $this;
    }

    public function conflictOnNextSave(): void
    {
        $this->conflictOnNextSave = true;
    }

    public function find(UuidInterface $id): ?Invoice
    {
        $row = $this->stored[$id->toString()] ?? null;

        if ($row === null) {
            return null;
        }

        $invoice = $this->copy($row['invoice']);
        $this->versions[$invoice] = $row['version'];

        return $invoice;
    }

    public function save(Invoice $invoice): void
    {
        $key = $invoice->id->toString();
        $version = $this->versions[$invoice] ?? 0;

        if ($this->conflictOnNextSave || ($this->stored[$key]['version'] ?? 0) !== $version) {
            $this->conflictOnNextSave = false;

            throw new ConcurrentModificationException($invoice->id);
        }

        $this->stored[$key] = ['invoice' => $this->copy($invoice), 'version' => $version + 1];
        array_push($this->publishedEvents, ...$invoice->recordedEvents());

        $invoice->clearRecordedEvents();
        $this->versions[$invoice] = $version + 1;
    }

    private function copy(Invoice $invoice): Invoice
    {
        return Invoice::reconstitute(
            $invoice->id,
            $invoice->customerName,
            $invoice->customerEmail,
            $invoice->status,
            ...$invoice->productLines,
        );
    }
}
