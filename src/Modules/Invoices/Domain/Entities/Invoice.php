<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Entities;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvalidInvoiceException;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransitionException;
use Ramsey\Uuid\UuidInterface;

final class Invoice
{
    public readonly array $productLines;

    public readonly int $totalPrice;

    private function __construct(
        public readonly UuidInterface $id,
        public readonly string $customerName,
        public readonly string $customerEmail,
        public private(set) StatusEnum $status,
        ProductLine ...$productLines,
    ) {
        if (trim($customerName) === '') {
            throw new InvalidInvoiceException('Customer name must not be empty.');
        }

        if (filter_var($customerEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidInvoiceException("Customer email {$customerEmail} is not a valid email address.");
        }

        $totalPrice = 0;

        foreach ($productLines as $line) {
            $totalPrice += $line->totalUnitPrice();

            if (! is_int($totalPrice)) {
                throw new InvalidInvoiceException('Invoice total exceeds the supported maximum.');
            }
        }

        $this->productLines = $productLines;
        $this->totalPrice = $totalPrice;
    }

    public static function create(
        UuidInterface $id,
        string $customerName,
        string $customerEmail,
        ProductLine ...$productLines,
    ): self {
        return new self($id, $customerName, $customerEmail, StatusEnum::Draft, ...$productLines);
    }

    public static function reconstitute(
        UuidInterface $id,
        string $customerName,
        string $customerEmail,
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

    private function assertStatus(StatusEnum $required, StatusEnum $target): void
    {
        if ($this->status !== $required) {
            throw new InvalidStatusTransitionException($this->id, $this->status, $target);
        }
    }
}
