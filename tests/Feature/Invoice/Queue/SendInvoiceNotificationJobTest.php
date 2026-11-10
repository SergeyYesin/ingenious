<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice\Queue;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Infrastructure\Persistence\Models\InvoiceModel;
use Modules\Invoices\Infrastructure\Queue\SendInvoiceNotificationJob;
use PHPUnit\Framework\Attributes\DataProvider;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Tests\Support\Invoices\FakesInvoiceNotifier;
use Tests\Support\Invoices\RecordingInvoiceNotifier;
use Tests\TestCase;

final class SendInvoiceNotificationJobTest extends TestCase
{
    use FakesInvoiceNotifier;

    private RecordingInvoiceNotifier $notifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notifier = $this->fakeInvoiceNotifier();
    }

    public function test_handle_notifies_customer_of_sending_invoice(): void
    {
        $invoice = InvoiceModel::factory()->sending()->withProductLines()->create();

        $this->handle($invoice->id);

        self::assertCount(1, $this->notifier->sent);
        self::assertSame($invoice->id, $this->notifier->sent[0]->invoiceId->toString());
        self::assertSame($invoice->customer_email, $this->notifier->sent[0]->recipient->value);
        $this->assertStatus($invoice, StatusEnum::Sending);
    }

    #[DataProvider('notSendingStatuses')]
    public function test_handle_skips_invoice_not_in_sending(StatusEnum $status): void
    {
        $invoice = InvoiceModel::factory()->state(['status' => $status->value])->withProductLines()->create();

        $this->handle($invoice->id);

        self::assertSame([], $this->notifier->sent);
        $this->assertStatus($invoice, $status);
    }

    public static function notSendingStatuses(): iterable
    {
        yield 'draft' => [StatusEnum::Draft];
        yield 'sent to client' => [StatusEnum::SentToClient];
    }

    public function test_handle_skips_unknown_invoice(): void
    {
        $this->handle(Uuid::uuid4()->toString());

        self::assertSame([], $this->notifier->sent);
    }

    public function test_handle_lets_notification_errors_bubble_up_for_retry(): void
    {
        $invoice = InvoiceModel::factory()->sending()->withProductLines()->create();
        $this->notifier->failTimes(1);

        try {
            $this->handle($invoice->id);
            self::fail('Expected RuntimeException');
        } catch (RuntimeException) {
            $this->assertStatus($invoice, StatusEnum::Sending);
        }
    }

    public function test_failed_returns_sending_invoice_to_draft(): void
    {
        $invoice = InvoiceModel::factory()->sending()->withProductLines()->create();

        new SendInvoiceNotificationJob($invoice->id)->failed(new RuntimeException('Mail server down'));

        $this->assertStatus($invoice, StatusEnum::Draft);
    }

    public function test_failed_keeps_delivered_invoice_delivered(): void
    {
        $invoice = InvoiceModel::factory()->sentToClient()->withProductLines()->create();

        new SendInvoiceNotificationJob($invoice->id)->failed(new RuntimeException('Timeout'));

        $this->assertStatus($invoice, StatusEnum::SentToClient);
    }

    private function handle(string $invoiceId): void
    {
        $this->app->call([new SendInvoiceNotificationJob($invoiceId), 'handle']);
    }

    private function assertStatus(InvoiceModel $invoice, StatusEnum $status): void
    {
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => $status->value]);
    }
}
