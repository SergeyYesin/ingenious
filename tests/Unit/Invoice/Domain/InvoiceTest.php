<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Domain;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Events\InvoiceSendingStarted;
use Modules\Invoices\Domain\Exceptions\InvalidInvoiceException;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotSendingException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Invoices\InvoiceMother;

final class InvoiceTest extends TestCase
{
    public function test_create_starts_in_draft_without_events(): void
    {
        $invoice = InvoiceMother::invoice();

        self::assertSame(StatusEnum::Draft, $invoice->status);
        self::assertSame([], $invoice->recordedEvents());
    }

    public function test_create_allows_empty_product_lines(): void
    {
        $invoice = InvoiceMother::withoutLines();

        self::assertSame([], $invoice->productLines);
        self::assertSame(0, $invoice->totalPrice->amount);
    }

    public function test_total_price_sums_line_totals(): void
    {
        $invoice = InvoiceMother::invoice(
            StatusEnum::Draft,
            InvoiceMother::line(2, 100),
            InvoiceMother::line(3, 50),
            InvoiceMother::line(0, 999),
        );

        self::assertSame(350, $invoice->totalPrice->amount);
    }

    public function test_rejects_total_price_overflowing_integer(): void
    {
        $this->expectException(InvalidInvoiceException::class);

        InvoiceMother::invoice(StatusEnum::Draft, InvoiceMother::line(1, PHP_INT_MAX), InvoiceMother::line(1, 1));
    }

    public function test_send_moves_draft_to_sending_and_records_event(): void
    {
        $invoice = InvoiceMother::invoice();

        $invoice->send();

        self::assertSame(StatusEnum::Sending, $invoice->status);
        self::assertEquals([new InvoiceSendingStarted($invoice->id)], $invoice->recordedEvents());
    }

    public function test_clear_recorded_events_empties_them(): void
    {
        $invoice = InvoiceMother::invoice();
        $invoice->send();

        $invoice->clearRecordedEvents();

        self::assertSame([], $invoice->recordedEvents());
    }

    public function test_send_rejects_invoice_without_product_lines(): void
    {
        $invoice = InvoiceMother::withoutLines();

        $this->expectException(InvalidInvoiceException::class);

        $invoice->send();
    }

    #[DataProvider('nonBillableLines')]
    public function test_send_rejects_non_billable_line(int $quantity, int $unitPrice): void
    {
        $invoice = InvoiceMother::invoice(StatusEnum::Draft, InvoiceMother::line(), InvoiceMother::line($quantity, $unitPrice));

        try {
            $invoice->send();
            self::fail('Expected InvalidInvoiceException');
        } catch (InvalidInvoiceException) {
            self::assertSame(StatusEnum::Draft, $invoice->status);
            self::assertSame([], $invoice->recordedEvents());
        }
    }

    public static function nonBillableLines(): iterable
    {
        yield 'zero quantity' => [0, 100];
        yield 'zero unit price' => [1, 0];
    }

    #[DataProvider('nonDraftStatuses')]
    public function test_send_rejects_non_draft_invoice(StatusEnum $status): void
    {
        $invoice = InvoiceMother::invoice($status);

        $this->expectException(InvalidStatusTransitionException::class);

        $invoice->send();
    }

    public static function nonDraftStatuses(): iterable
    {
        yield 'sending' => [StatusEnum::Sending];
        yield 'sent to client' => [StatusEnum::SentToClient];
    }

    public function test_mark_delivered_moves_sending_to_sent_to_client(): void
    {
        $invoice = InvoiceMother::invoice(StatusEnum::Sending);

        $invoice->markDelivered();

        self::assertSame(StatusEnum::SentToClient, $invoice->status);
    }

    #[DataProvider('nonSendingStatuses')]
    public function test_mark_delivered_rejects_invoice_not_in_sending(StatusEnum $status): void
    {
        $invoice = InvoiceMother::invoice($status);

        $this->expectException(InvalidStatusTransitionException::class);

        $invoice->markDelivered();
    }

    public static function nonSendingStatuses(): iterable
    {
        yield 'draft' => [StatusEnum::Draft];
        yield 'sent to client' => [StatusEnum::SentToClient];
    }

    public function test_cancel_sending_returns_to_draft(): void
    {
        $invoice = InvoiceMother::invoice(StatusEnum::Sending);

        $invoice->cancelSending();

        self::assertSame(StatusEnum::Draft, $invoice->status);
    }

    #[DataProvider('nonSendingStatuses')]
    public function test_cancel_sending_rejects_invoice_not_in_sending(StatusEnum $status): void
    {
        $invoice = InvoiceMother::invoice($status);

        $this->expectException(InvalidStatusTransitionException::class);

        $invoice->cancelSending();
    }

    public function test_notification_addresses_customer_with_total(): void
    {
        $invoice = InvoiceMother::invoice(StatusEnum::Sending, InvoiceMother::line(2, 100), InvoiceMother::line(3, 50));

        $notification = $invoice->notification();

        self::assertTrue($notification->invoiceId->equals($invoice->id));
        self::assertSame('jane@example.com', $notification->recipient->value);
        self::assertSame("Invoice {$invoice->id->toString()}", $notification->subject);
        self::assertSame('Hello Jane Doe, your invoice total is 350.', $notification->message);
    }

    #[DataProvider('nonSendingStatuses')]
    public function test_notification_is_only_available_while_sending(StatusEnum $status): void
    {
        $invoice = InvoiceMother::invoice($status);

        $this->expectException(InvoiceNotSendingException::class);

        $invoice->notification();
    }
}
