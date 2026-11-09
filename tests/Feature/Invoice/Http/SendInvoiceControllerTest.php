<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice\Http;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Modules\Invoices\Application\Jobs\SendInvoiceNotificationJob;
use Modules\Invoices\Infrastructure\Persistence\Models\InvoiceModel;
use Modules\Notifications\Api\NotificationFacadeInterface;
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

    public function test_queues_notification_and_moves_invoice_to_sending(): void
    {
        $id = InvoiceModel::factory()->withProductLines()->create()->id;

        $this->mock(NotificationFacadeInterface::class)->shouldNotReceive('notify');

        $this->postJson(route('invoices.send', $id))
            ->assertAccepted()
            ->assertJsonPath('data.status', 'sending');

        Queue::assertPushed(
            SendInvoiceNotificationJob::class,
            static fn (SendInvoiceNotificationJob $job): bool => $job->invoiceId === $id,
        );
        $this->assertDatabaseHas('invoices', ['id' => $id, 'status' => 'sending']);
    }

    public function test_rejects_invoice_without_product_lines(): void
    {
        $id = InvoiceModel::factory()->create()->id;

        $this->postJson(route('invoices.send', $id))->assertUnprocessable();

        Queue::assertNothingPushed();
        $this->assertDatabaseHas('invoices', ['id' => $id, 'status' => 'draft']);
    }

    public function test_rejects_invoice_with_zero_line(): void
    {
        $id = InvoiceModel::factory()
            ->withProductLines()
            ->withProductLines(attributes: ['unit_price' => 0])
            ->create()
            ->id;

        $this->postJson(route('invoices.send', $id))->assertUnprocessable();

        Queue::assertNothingPushed();
        $this->assertDatabaseHas('invoices', ['id' => $id, 'status' => 'draft']);
    }

    public function test_sending_twice_returns_conflict(): void
    {
        $id = InvoiceModel::factory()->withProductLines()->create()->id;

        $this->postJson(route('invoices.send', $id))->assertAccepted();
        $this->postJson(route('invoices.send', $id))->assertConflict();

        Queue::assertPushedTimes(SendInvoiceNotificationJob::class, 1);
        $this->assertDatabaseHas('invoices', ['id' => $id, 'status' => 'sending']);
    }

    public function test_returns_not_found_for_unknown_invoice(): void
    {
        $this->postJson(route('invoices.send', Uuid::uuid4()->toString()))->assertNotFound();
    }

    public function test_returns_service_unavailable_and_keeps_draft_when_job_cannot_be_queued(): void
    {
        $id = InvoiceModel::factory()->withProductLines()->create()->id;

        $this->mock(Dispatcher::class, static function (MockInterface $mock): void {
            $mock->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Redis is down'));
        });

        Exceptions::fake();

        $this->postJson(route('invoices.send', $id))
            ->assertServiceUnavailable()
            ->assertJsonMissing(['message' => 'Redis is down']);

        $this->assertDatabaseHas('invoices', ['id' => $id, 'status' => 'draft']);
        Exceptions::assertNothingReported();
    }

    public function test_can_be_sent_again_after_notification_job_fails(): void
    {
        $id = InvoiceModel::factory()->withProductLines()->create()->id;

        $this->postJson(route('invoices.send', $id))->assertAccepted();

        Queue::pushed(SendInvoiceNotificationJob::class)
            ->first()
            ->failed(new RuntimeException('Mail server down'));

        $this->assertDatabaseHas('invoices', ['id' => $id, 'status' => 'draft']);

        $this->postJson(route('invoices.send', $id))
            ->assertAccepted()
            ->assertJsonPath('data.status', 'sending');

        Queue::assertPushedTimes(SendInvoiceNotificationJob::class, 2);
    }
}
