<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Domain\ValueObjects;

use Modules\Invoices\Domain\Exceptions\InvalidInvoiceException;
use Modules\Invoices\Domain\ValueObjects\Quantity;
use PHPUnit\Framework\TestCase;

final class QuantityTest extends TestCase
{
    public function test_rejects_negative_value(): void
    {
        $this->expectException(InvalidInvoiceException::class);

        new Quantity(-1);
    }

    public function test_zero_is_allowed_but_not_positive(): void
    {
        self::assertFalse(new Quantity(0)->isPositive());
    }

    public function test_positive_above_zero(): void
    {
        self::assertTrue(new Quantity(1)->isPositive());
    }
}
