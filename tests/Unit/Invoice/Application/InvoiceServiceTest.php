<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Application;

use Modules\Invoices\Application\Data\CreateInvoiceData;
use Modules\Invoices\Application\Data\ProductLineData;
use Modules\Invoices\Application\Services\InvoiceService;
use Modules\Invoices\Domain\Entities\ProductLine;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Events\InvoiceSendingStarted;
use Modules\Invoices\Domain\Exceptions\ConcurrentModificationException;
use Modules\Invoices\Domain\Exceptions\InvalidInvoiceException;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use Tests\Support\Invoices\InMemoryInvoiceRepository;
use Tests\Support\Invoices\InvoiceMother;
use Tests\Support\Invoices\RecordingInvoiceNotifier;

final class InvoiceServiceTest extends TestCase
{
    private InMemoryInvoiceRepository $invoices;

    private RecordingInvoiceNotifier $notifier;

    private InvoiceService $service;

    protected function setUp(): void
    {
        $this->invoices = new InMemoryInvoiceRepository;
        $this->notifier = new RecordingInvoiceNotifier;
        $this->service = new InvoiceService($this->invoices, $this->notifier, new NullLogger);
    }

    public function test_create_saves_draft_invoice_with_product_lines(): void
    {
        $id = $this->service->create(new CreateInvoiceData(
            'Jane Doe',
            'jane@example.com',
            new ProductLineData('Widget', 2, 100),
            new ProductLineData('Gadget', 0, 50),
        ));

        $invoice = $this->invoices->find($id);

        self::assertSame(StatusEnum::Draft, $invoice?->status);
        self::assertSame('Jane Doe', $invoice->customerName->value);
        self::assertSame(['Widget', 'Gadget'], array_map(
            static fn (ProductLine $line): string => $line->name->value,
            $invoice->productLines,
        ));
        self::assertSame(200, $invoice->totalPrice->amount);
        self::assertSame([], $this->invoices->publishedEvents);
    }

    public function test_create_rejects_invalid_customer_email(): void
    {
        $this->expectException(InvalidInvoiceException::class);

        $this->service->create(new CreateInvoiceData('Jane Doe', 'not-an-email'));
    }

    public function test_send_moves_invoice_to_sending_and_publishes_event(): void
    {
        $id = $this->seed(StatusEnum::Draft);

        $this->service->send($id);

        self::assertSame(StatusEnum::Sending, $this->statusOf($id));
        self::assertEquals([new InvoiceSendingStarted($id)], $this->invoices->publishedEvents);
        self::assertSame([], $this->notifier->sent);
    }

    public function test_send_rejects_unknown_invoice(): void
    {
        $this->expectException(InvoiceNotFoundException::class);

        $this->service->send(Uuid::uuid4());
    }

    public function test_send_keeps_unsendable_invoice_in_draft(): void
    {
        $invoice = InvoiceMother::invoice(StatusEnum::Draft, InvoiceMother::line(0, 100));
        $this->invoices->seed($invoice);

        try {
            $this->service->send($invoice->id);
            self::fail('Expected InvalidInvoiceException');
        } catch (InvalidInvoiceException) {
            self::assertSame(StatusEnum::Draft, $this->statusOf($invoice->id));
            self::assertSame([], $this->invoices->publishedEvents);
        }
    }

    public function test_send_rejects_invoice_already_sending(): void
    {
        $id = $this->seed(StatusEnum::Sending);

        $this->expectException(InvalidStatusTransitionException::class);

        $this->service->send($id);
    }

    public function test_send_reports_concurrent_change(): void
    {
        $id = $this->seed(StatusEnum::Draft);
        $this->invoices->conflictOnNextSave();

        try {
            $this->service->send($id);
            self::fail('Expected ConcurrentModificationException');
        } catch (ConcurrentModificationException) {
            self::assertSame(StatusEnum::Draft, $this->statusOf($id));
            self::assertSame([], $this->invoices->publishedEvents);
        }
    }

    public function test_notify_customer_sends_invoice_notification(): void
    {
        $id = $this->seed(StatusEnum::Sending);

        $this->service->notifyCustomer($id);

        self::assertCount(1, $this->notifier->sent);
        self::assertTrue($this->notifier->sent[0]->invoiceId->equals($id));
    }

    #[DataProvider('notSendingStatuses')]
    public function test_notify_customer_skips_invoice_not_in_sending(?StatusEnum $status): void
    {
        $this->service->notifyCustomer($this->seed($status));

        self::assertSame([], $this->notifier->sent);
    }

    public function test_notify_customer_lets_notifier_errors_bubble_up(): void
    {
        $id = $this->seed(StatusEnum::Sending);
        $this->notifier->failTimes(1);

        $this->expectException(RuntimeException::class);

        $this->service->notifyCustomer($id);
    }

    public function test_abandon_sending_returns_invoice_to_draft(): void
    {
        $id = $this->seed(StatusEnum::Sending);

        $this->service->abandonSending($id, new RuntimeException('Mail server down'));

        self::assertSame(StatusEnum::Draft, $this->statusOf($id));
    }

    #[DataProvider('notSendingStatuses')]
    public function test_abandon_sending_keeps_invoice_not_in_sending(?StatusEnum $status): void
    {
        $id = $this->seed($status);

        $this->service->abandonSending($id, new RuntimeException('Mail server down'));

        self::assertSame($status, $this->statusOf($id));
    }

    public function test_mark_delivered_moves_sending_to_sent_to_client(): void
    {
        $id = $this->seed(StatusEnum::Sending);

        $this->service->markDelivered($id);

        self::assertSame(StatusEnum::SentToClient, $this->statusOf($id));
    }

    #[DataProvider('notSendingStatuses')]
    public function test_mark_delivered_keeps_invoice_not_in_sending(?StatusEnum $status): void
    {
        $id = $this->seed($status);

        $this->service->markDelivered($id);

        self::assertSame($status, $this->statusOf($id));
    }

    public function test_mark_delivered_gives_up_when_invoice_changes_concurrently(): void
    {
        $id = $this->seed(StatusEnum::Sending);
        $this->invoices->conflictOnNextSave();

        $this->service->markDelivered($id);

        self::assertSame(StatusEnum::Sending, $this->statusOf($id));
    }

    public static function notSendingStatuses(): iterable
    {
        yield 'unknown invoice' => [null];
        yield 'draft' => [StatusEnum::Draft];
        yield 'sent to client' => [StatusEnum::SentToClient];
    }

    private function seed(?StatusEnum $status): UuidInterface
    {
        if ($status === null) {
            return Uuid::uuid4();
        }

        $invoice = InvoiceMother::invoice($status);
        $this->invoices->seed($invoice);

        return $invoice->id;
    }

    private function statusOf(UuidInterface $id): ?StatusEnum
    {
        return $this->invoices->find($id)?->status;
    }
}
