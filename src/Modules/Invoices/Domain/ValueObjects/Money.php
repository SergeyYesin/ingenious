<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\ValueObjects;

use Modules\Invoices\Domain\Exceptions\InvalidInvoiceException;

final readonly class Money
{
    public function __construct(
        public int $amount,
    ) {
        if ($amount < 0) {
            throw new InvalidInvoiceException("Amount must not be negative, {$amount} given.");
        }
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(self $other): self
    {
        return new self(self::guard($this->amount + $other->amount));
    }

    public function multiply(Quantity $quantity): self
    {
        return new self(self::guard($this->amount * $quantity->value));
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    private static function guard(int|float $amount): int
    {
        if (! is_int($amount)) {
            throw new InvalidInvoiceException('Amount exceeds the supported maximum.');
        }

        return $amount;
    }
}
