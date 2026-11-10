<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice\Outbox;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Modules\Invoices\Infrastructure\Outbox\InvoiceOutbox;
use Modules\Invoices\Infrastructure\Outbox\InvoiceOutboxRelay;
use Modules\Invoices\Infrastructure\Persistence\Models\InvoiceModel;
use Modules\Invoices\Infrastructure\Queue\SendInvoiceNotificationJob;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class InvoiceOutboxRelayTest extends TestCase
{
    public function test_dispatches_pending_messages_in_order_and_marks_them(): void
    {
        Queue::fake();
        $first = $this->pendingMessage();
        $second = $this->pendingMessage();

        self::assertSame(2, $this->relay()->relay());

        self::assertSame(
            [$first, $second],
            Queue::pushed(SendInvoiceNotificationJob::class)
                ->map(static fn (SendInvoiceNotificationJob $job): string => $job->invoiceId)
                ->all(),
        );
        $this->assertDatabaseMissing(InvoiceOutbox::TABLE, ['dispatched_at' => null]);
    }

    public function test_skips_already_dispatched_messages(): void
    {
        Queue::fake();
        $this->pendingMessage();
        $this->relay()->relay();

        self::assertSame(0, $this->relay()->relay());

        Queue::assertPushedTimes(SendInvoiceNotificationJob::class, 1);
    }

    public function test_stops_at_limit(): void
    {
        Queue::fake();
        $this->pendingMessage();
        $this->pendingMessage();

        self::assertSame(1, $this->relay()->relay(limit: 1));

        self::assertSame(1, DB::table(InvoiceOutbox::TABLE)->whereNull('dispatched_at')->count());
    }

    public function test_keeps_message_pending_when_queue_is_unavailable(): void
    {
        config(['queue.default' => 'database']);
        $this->pendingMessage();

        self::assertSame(0, $this->relay()->relay());

        $this->assertDatabaseHas(InvoiceOutbox::TABLE, ['dispatched_at' => null]);
    }

    public function test_command_relays_pending_messages(): void
    {
        Queue::fake();
        $this->pendingMessage();

        $this->artisan('invoices:relay-outbox')
            ->expectsOutputToContain('Relayed 1 invoice outbox messages.')
            ->assertSuccessful();

        Queue::assertPushed(SendInvoiceNotificationJob::class);
    }

    public function test_command_is_scheduled_every_second(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(static fn (Event $event): bool => str_contains((string) $event->command, 'invoices:relay-outbox'));

        self::assertCount(1, $events);
        self::assertSame(1, $events->first()->repeatSeconds);
        self::assertTrue($events->first()->withoutOverlapping);
    }

    private function relay(): InvoiceOutboxRelay
    {
        return $this->app->make(InvoiceOutboxRelay::class);
    }

    private function pendingMessage(): string
    {
        $id = InvoiceModel::factory()->sending()->withProductLines()->create()->id;

        $this->app->make(InvoiceOutbox::class)->append(InvoiceOutbox::SENDING_STARTED, Uuid::fromString($id));

        return $id;
    }
}
