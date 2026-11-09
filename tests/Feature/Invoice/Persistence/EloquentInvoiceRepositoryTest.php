<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice\Persistence;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Entities\ProductLine;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\Invoices\Infrastructure\Persistence\EloquentInvoiceRepository;
use Modules\Invoices\Infrastructure\Persistence\Models\InvoiceModel;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class EloquentInvoiceRepositoryTest extends TestCase
{
    private EloquentInvoiceRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(EloquentInvoiceRepository::class);
    }

    public function test_added_invoice_can_be_found(): void
    {
        $line = new ProductLine(Uuid::uuid7(), 'Widget', 2, 150);
        $invoice = Invoice::create(Uuid::uuid4(), 'Jane Doe', 'jane@example.com', $line);

        $this->repository->add($invoice);
        $found = $this->repository->find($invoice->id);

        self::assertNotNull($found);
        self::assertTrue($found->id->equals($invoice->id));
        self::assertSame('Jane Doe', $found->customerName);
        self::assertSame('jane@example.com', $found->customerEmail);
        self::assertSame(StatusEnum::Draft, $found->status);
        self::assertCount(1, $found->productLines);
        self::assertTrue($found->productLines[0]->id->equals($line->id));
        self::assertSame(300, $found->totalPrice);
    }

    public function test_added_invoice_without_lines_can_be_found(): void
    {
        $invoice = Invoice::create(Uuid::uuid4(), 'Jane Doe', 'jane@example.com');

        $this->repository->add($invoice);

        self::assertSame([], $this->repository->find($invoice->id)?->productLines);
    }

    public function test_find_maps_stored_status_and_lines_in_order(): void
    {
        $model = InvoiceModel::factory()->sentToClient()->withProductLines(3)->create();

        $found = $this->repository->find(Uuid::fromString($model->id));

        self::assertSame(StatusEnum::SentToClient, $found?->status);
        self::assertSame(
            $model->productLines()->pluck('id')->sort()->values()->all(),
            array_map(static fn (ProductLine $line): string => $line->id->toString(), $found->productLines),
        );
    }

    public function test_find_returns_null_for_unknown_invoice(): void
    {
        self::assertNull($this->repository->find(Uuid::uuid4()));
    }

    public function test_save_status_updates_when_expected_status_matches(): void
    {
        $invoice = $this->findDraft();

        $invoice->send();
        $this->repository->saveStatus($invoice, StatusEnum::Draft);

        self::assertSame(StatusEnum::Sending, $this->repository->find($invoice->id)?->status);
    }

    public function test_save_status_rejects_stale_expected_status(): void
    {
        $invoice = $this->findDraft();
        $stale = $this->repository->find($invoice->id);

        $invoice->send();
        $this->repository->saveStatus($invoice, StatusEnum::Draft);

        $stale?->send();

        $this->expectException(InvalidStatusTransitionException::class);

        $this->repository->saveStatus($stale, StatusEnum::Draft);
    }

    private function findDraft(): Invoice
    {
        $model = InvoiceModel::factory()->withProductLines()->create();

        return $this->repository->find(Uuid::fromString($model->id));
    }
}
