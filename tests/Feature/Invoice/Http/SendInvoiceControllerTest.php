<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice\Http;

use Illuminate\Support\Facades\Queue;
use Modules\Invoices\Infrastructure\Outbox\InvoiceOutbox;
use Modules\Invoices\Infrastructure\Persistence\Models\InvoiceModel;
use Modules\Invoices\Infrastructure\Queue\SendInvoiceNotificationJob;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Tests\TestCase;

final class SendInvoiceControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_moves_invoice_to_sending_and_records_outbox_message(): void
    {
        $id = InvoiceModel::factory()->withProductLines()->create()->id;

        $this->postJson(route('invoices.send', $id))
            ->assertAccepted()
            ->assertJsonPath('data.status', 'sending');

        $this->assertDatabaseHas('invoices', ['id' => $id, 'status' => 'sending']);
        $this->assertDatabaseHas(InvoiceOutbox::TABLE, [
            'type' => InvoiceOutbox::SENDING_STARTED,
            'invoice_id' => $id,
            'dispatched_at' => null,
        ]);
        Queue::assertNothingPushed();
    }

    public function test_rejects_invoice_without_product_lines(): void
    {
        $id = InvoiceModel::factory()->create()->id;

        $this->postJson(route('invoices.send', $id))->assertUnprocessable();

        $this->assertDatabaseHas('invoices', ['id' => $id, 'status' => 'draft']);
        $this->assertDatabaseCount(InvoiceOutbox::TABLE, 0);
    }

    public function test_rejects_invoice_with_zero_line(): void
    {
        $id = InvoiceModel::factory()
            ->withProductLines()
            ->withProductLines(attributes: ['unit_price' => 0])
            ->create()
            ->id;

        $this->postJson(route('invoices.send', $id))->assertUnprocessable();

        $this->assertDatabaseHas('invoices', ['id' => $id, 'status' => 'draft']);
        $this->assertDatabaseCount(InvoiceOutbox::TABLE, 0);
    }

    public function test_sending_twice_returns_conflict(): void
    {
        $id = InvoiceModel::factory()->withProductLines()->create()->id;

        $this->postJson(route('invoices.send', $id))->assertAccepted();
        $this->postJson(route('invoices.send', $id))->assertConflict();

        $this->assertDatabaseHas('invoices', ['id' => $id, 'status' => 'sending']);
        $this->assertDatabaseCount(InvoiceOutbox::TABLE, 1);
    }

    public function test_returns_not_found_for_unknown_invoice(): void
    {
        $this->postJson(route('invoices.send', Uuid::uuid4()->toString()))->assertNotFound();
    }

    public function test_can_be_sent_again_after_notification_job_fails(): void
    {
        $id = InvoiceModel::factory()->withProductLines()->create()->id;

        $this->postJson(route('invoices.send', $id))->assertAccepted();
        new SendInvoiceNotificationJob($id)->failed(new RuntimeException('Mail server down'));
        $this->assertDatabaseHas('invoices', ['id' => $id, 'status' => 'draft']);

        $this->postJson(route('invoices.send', $id))
            ->assertAccepted()
            ->assertJsonPath('data.status', 'sending');

        $this->assertDatabaseCount(InvoiceOutbox::TABLE, 2);
    }
}
