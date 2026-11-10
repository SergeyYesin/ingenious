<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice\Persistence;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Entities\ProductLine;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\ConcurrentModificationException;
use Modules\Invoices\Infrastructure\Outbox\InvoiceOutbox;
use Modules\Invoices\Infrastructure\Persistence\EloquentInvoiceRepository;
use Modules\Invoices\Infrastructure\Persistence\Models\InvoiceModel;
use Ramsey\Uuid\Uuid;
use Tests\Support\Invoices\InvoiceMother;
use Tests\TestCase;

final class EloquentInvoiceRepositoryTest extends TestCase
{
    public function test_saved_invoice_can_be_found(): void
    {
        $line = InvoiceMother::line(2, 150);
        $invoice = InvoiceMother::invoice(StatusEnum::Draft, $line);

        $this->repository()->save($invoice);
        $found = $this->repository()->find($invoice->id);

        self::assertNotNull($found);
        self::assertTrue($found->id->equals($invoice->id));
        self::assertSame('Jane Doe', $found->customerName->value);
        self::assertSame('jane@example.com', $found->customerEmail->value);
        self::assertSame(StatusEnum::Draft, $found->status);
        self::assertCount(1, $found->productLines);
        self::assertTrue($found->productLines[0]->id->equals($line->id));
        self::assertSame(300, $found->totalPrice->amount);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id->toString(), 'version' => 1]);
    }

    public function test_saved_invoice_without_lines_can_be_found(): void
    {
        $invoice = InvoiceMother::withoutLines();

        $this->repository()->save($invoice);

        self::assertSame([], $this->repository()->find($invoice->id)?->productLines);
    }

    public function test_find_maps_stored_status_and_lines_in_order(): void
    {
        $model = InvoiceModel::factory()->sentToClient()->withProductLines(3)->create();

        $found = $this->repository()->find(Uuid::fromString($model->id));

        self::assertSame(StatusEnum::SentToClient, $found?->status);
        self::assertSame(
            $model->productLines()->pluck('id')->sort()->values()->all(),
            array_map(static fn (ProductLine $line): string => $line->id->toString(), $found->productLines),
        );
    }

    public function test_find_returns_null_for_unknown_invoice(): void
    {
        self::assertNull($this->repository()->find(Uuid::uuid4()));
    }

    public function test_save_persists_changes_and_bumps_version(): void
    {
        $repository = $this->repository();
        $invoice = $this->findDraft($repository);

        $invoice->send();
        $repository->save($invoice);
        $invoice->markDelivered();
        $repository->save($invoice);

        self::assertSame(StatusEnum::SentToClient, $this->repository()->find($invoice->id)?->status);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id->toString(), 'version' => 3]);
    }

    public function test_save_writes_recorded_events_to_outbox_once(): void
    {
        $repository = $this->repository();
        $invoice = $this->findDraft($repository);

        $invoice->send();
        $repository->save($invoice);
        $invoice->markDelivered();
        $repository->save($invoice);

        self::assertSame([], $invoice->recordedEvents());
        $this->assertDatabaseCount(InvoiceOutbox::TABLE, 1);
        $this->assertDatabaseHas(InvoiceOutbox::TABLE, [
            'type' => InvoiceOutbox::SENDING_STARTED,
            'invoice_id' => $invoice->id->toString(),
            'dispatched_at' => null,
        ]);
    }

    public function test_save_rejects_stale_invoice_without_writing_outbox(): void
    {
        $firstRepository = $this->repository();
        $invoice = $this->findDraft($firstRepository);
        $staleRepository = $this->repository();
        $stale = $staleRepository->find($invoice->id);

        $invoice->send();
        $firstRepository->save($invoice);

        $stale?->send();

        try {
            $staleRepository->save($stale);
            self::fail('Expected ConcurrentModificationException');
        } catch (ConcurrentModificationException) {
            $this->assertDatabaseCount(InvoiceOutbox::TABLE, 1);
            $this->assertDatabaseHas('invoices', ['id' => $invoice->id->toString(), 'version' => 2]);
        }
    }

    private function repository(): EloquentInvoiceRepository
    {
        return $this->app->make(EloquentInvoiceRepository::class);
    }

    private function findDraft(EloquentInvoiceRepository $repository): Invoice
    {
        $model = InvoiceModel::factory()->withProductLines()->create();

        return $repository->find(Uuid::fromString($model->id));
    }
}
