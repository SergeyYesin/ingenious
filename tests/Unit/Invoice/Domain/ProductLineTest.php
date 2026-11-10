<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Domain;

use Modules\Invoices\Domain\Exceptions\InvalidInvoiceException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\Invoices\InvoiceMother;

final class ProductLineTest extends TestCase
{
    public function test_total_unit_price_is_quantity_times_unit_price(): void
    {
        self::assertSame(750, InvoiceMother::line(3, 250)->totalUnitPrice->amount);
    }

    #[DataProvider('billabilityCases')]
    public function test_billable_only_when_quantity_and_unit_price_positive(int $quantity, int $unitPrice, bool $billable): void
    {
        self::assertSame($billable, InvoiceMother::line($quantity, $unitPrice)->isBillable());
    }

    public static function billabilityCases(): iterable
    {
        yield 'positive' => [1, 1, true];
        yield 'zero quantity' => [0, 100, false];
        yield 'zero unit price' => [5, 0, false];
        yield 'both zero' => [0, 0, false];
    }

    public function test_rejects_total_overflowing_integer(): void
    {
        $this->expectException(InvalidInvoiceException::class);

        InvoiceMother::line(2, PHP_INT_MAX);
    }
}
