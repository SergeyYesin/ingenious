<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Entities;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Events\InvoiceSendingStarted;
use Modules\Invoices\Domain\Exceptions\InvalidInvoiceException;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotSendingException;
use Modules\Invoices\Domain\ValueObjects\CustomerName;
use Modules\Invoices\Domain\ValueObjects\Email;
use Modules\Invoices\Domain\ValueObjects\InvoiceNotification;
use Modules\Invoices\Domain\ValueObjects\Money;
use Ramsey\Uuid\UuidInterface;

final class Invoice
{
    public readonly array $productLines;

    public readonly Money $totalPrice;

    private array $recordedEvents = [];

    private function __construct(
        public readonly UuidInterface $id,
        public readonly CustomerName $customerName,
        public readonly Email $customerEmail,
        public private(set) StatusEnum $status,
        ProductLine ...$productLines,
    ) {
        $this->productLines = $productLines;
        $this->totalPrice = array_reduce(
            $productLines,
            static fn (Money $total, ProductLine $line): Money => $total->add($line->totalUnitPrice),
            Money::zero(),
        );
    }

    public static function create(
        UuidInterface $id,
        CustomerName $customerName,
        Email $customerEmail,
        ProductLine ...$productLines,
    ): self {
        return new self($id, $customerName, $customerEmail, StatusEnum::Draft, ...$productLines);
    }

    public static function reconstitute(
        UuidInterface $id,
        CustomerName $customerName,
        Email $customerEmail,
        StatusEnum $status,
        ProductLine ...$productLines,
    ): self {
        return new self($id, $customerName, $customerEmail, $status, ...$productLines);
    }

    public function send(): void
    {
        $this->assertStatus(StatusEnum::Draft, StatusEnum::Sending);

        if ($this->productLines === []) {
            throw new InvalidInvoiceException("Invoice {$this->id->toString()} has no product lines.");
        }

        foreach ($this->productLines as $line) {
            if (! $line->isBillable()) {
                throw new InvalidInvoiceException("Invoice {$this->id->toString()} has product line {$line->id->toString()} with zero quantity or unit price.");
            }
        }

        $this->status = StatusEnum::Sending;
        $this->recordedEvents[] = new InvoiceSendingStarted($this->id);
    }

    public function cancelSending(): void
    {
        $this->assertStatus(StatusEnum::Sending, StatusEnum::Draft);

        $this->status = StatusEnum::Draft;
    }

    public function markDelivered(): void
    {
        $this->assertStatus(StatusEnum::Sending, StatusEnum::SentToClient);

        $this->status = StatusEnum::SentToClient;
    }

    public function notification(): InvoiceNotification
    {
        if ($this->status !== StatusEnum::Sending) {
            throw new InvoiceNotSendingException($this->id, $this->status);
        }

        return new InvoiceNotification(
            invoiceId: $this->id,
            recipient: $this->customerEmail,
            subject: "Invoice {$this->id->toString()}",
            message: "Hello {$this->customerName->value}, your invoice total is {$this->totalPrice->amount}.",
        );
    }

    public function recordedEvents(): array
    {
        return $this->recordedEvents;
    }

    public function clearRecordedEvents(): void
    {
        $this->recordedEvents = [];
    }

    private function assertStatus(StatusEnum $required, StatusEnum $target): void
    {
        if ($this->status !== $required) {
            throw new InvalidStatusTransitionException($this->id, $this->status, $target);
        }
    }
}
