<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Domain;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Entities\ProductLine;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvalidInvoiceException;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransitionException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class InvoiceTest extends TestCase
{
    public function test_create_starts_in_draft(): void
    {
        $invoice = $this->invoice(StatusEnum::Draft, $this->line(1, 100));

        self::assertSame(StatusEnum::Draft, $invoice->status);
    }

    public function test_create_allows_empty_product_lines(): void
    {
        $invoice = Invoice::create(Uuid::uuid4(), 'Jane Doe', 'jane@example.com');

        self::assertSame([], $invoice->productLines);
        self::assertSame(0, $invoice->totalPrice);
    }

    public function test_total_price_sums_line_totals(): void
    {
        $invoice = $this->invoice(StatusEnum::Draft, $this->line(2, 100), $this->line(3, 50), $this->line(0, 999));

        self::assertSame(350, $invoice->totalPrice);
    }

    public function test_rejects_total_price_overflowing_integer(): void
    {
        $this->expectException(InvalidInvoiceException::class);

        $this->invoice(StatusEnum::Draft, $this->line(PHP_INT_MAX, 1), $this->line(1, 1));
    }

    public function test_rejects_empty_customer_name(): void
    {
        $this->expectException(InvalidInvoiceException::class);

        Invoice::create(Uuid::uuid4(), ' ', 'jane@example.com');
    }

    public function test_rejects_invalid_customer_email(): void
    {
        $this->expectException(InvalidInvoiceException::class);

        Invoice::create(Uuid::uuid4(), 'Jane Doe', 'not-an-email');
    }

    public function test_send_moves_draft_to_sending(): void
    {
        $invoice = $this->invoice(StatusEnum::Draft, $this->line(1, 100));

        $invoice->send();

        self::assertSame(StatusEnum::Sending, $invoice->status);
    }

    public function test_send_rejects_invoice_without_product_lines(): void
    {
        $invoice = $this->invoice(StatusEnum::Draft);

        $this->expectException(InvalidInvoiceException::class);

        $invoice->send();
    }

    #[DataProvider('nonBillableLines')]
    public function test_send_rejects_non_billable_line(int $quantity, int $unitPrice): void
    {
        $invoice = $this->invoice(StatusEnum::Draft, $this->line(1, 100), $this->line($quantity, $unitPrice));

        try {
            $invoice->send();
            self::fail('Expected InvalidInvoiceException');
        } catch (InvalidInvoiceException) {
            self::assertSame(StatusEnum::Draft, $invoice->status);
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
        $invoice = $this->invoice($status, $this->line(1, 100));

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
        $invoice = $this->invoice(StatusEnum::Sending, $this->line(1, 100));

        $invoice->markDelivered();

        self::assertSame(StatusEnum::SentToClient, $invoice->status);
    }

    #[DataProvider('nonSendingStatuses')]
    public function test_mark_delivered_rejects_invoice_not_in_sending(StatusEnum $status): void
    {
        $invoice = $this->invoice($status, $this->line(1, 100));

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
        $invoice = $this->invoice(StatusEnum::Sending, $this->line(1, 100));

        $invoice->cancelSending();

        self::assertSame(StatusEnum::Draft, $invoice->status);
    }

    #[DataProvider('nonSendingStatuses')]
    public function test_cancel_sending_rejects_invoice_not_in_sending(StatusEnum $status): void
    {
        $invoice = $this->invoice($status, $this->line(1, 100));

        $this->expectException(InvalidStatusTransitionException::class);

        $invoice->cancelSending();
    }

    private function invoice(StatusEnum $status, ProductLine ...$lines): Invoice
    {
        $invoice = Invoice::create(Uuid::uuid4(), 'Jane Doe', 'jane@example.com', ...$lines);

        if ($status !== StatusEnum::Draft) {
            $invoice->send();
        }

        if ($status === StatusEnum::SentToClient) {
            $invoice->markDelivered();
        }

        return $invoice;
    }

    private function line(int $quantity, int $unitPrice): ProductLine
    {
        return new ProductLine(Uuid::uuid4(), 'Widget', $quantity, $unitPrice);
    }
}
