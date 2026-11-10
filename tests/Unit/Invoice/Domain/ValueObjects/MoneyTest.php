<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Domain\ValueObjects;

use Modules\Invoices\Domain\Exceptions\InvalidInvoiceException;
use Modules\Invoices\Domain\ValueObjects\Money;
use Modules\Invoices\Domain\ValueObjects\Quantity;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_zero_has_no_amount(): void
    {
        self::assertSame(0, Money::zero()->amount);
        self::assertFalse(Money::zero()->isPositive());
    }

    public function test_rejects_negative_amount(): void
    {
        $this->expectException(InvalidInvoiceException::class);

        new Money(-1);
    }

    public function test_add_returns_new_sum(): void
    {
        $money = new Money(150);

        self::assertSame(400, $money->add(new Money(250))->amount);
        self::assertSame(150, $money->amount);
    }

    public function test_multiply_by_quantity(): void
    {
        self::assertSame(750, new Money(250)->multiply(new Quantity(3))->amount);
    }

    public function test_add_rejects_overflow(): void
    {
        $this->expectException(InvalidInvoiceException::class);

        new Money(PHP_INT_MAX)->add(new Money(1));
    }

    public function test_multiply_rejects_overflow(): void
    {
        $this->expectException(InvalidInvoiceException::class);

        new Money(PHP_INT_MAX)->multiply(new Quantity(2));
    }

    public function test_is_positive_above_zero(): void
    {
        self::assertTrue(new Money(1)->isPositive());
    }
}
