<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Domain;

use Modules\Invoices\Domain\Entities\ProductLine;
use Modules\Invoices\Domain\Exceptions\InvalidInvoiceException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class ProductLineTest extends TestCase
{
    public function test_total_unit_price_is_quantity_times_unit_price(): void
    {
        $line = new ProductLine(Uuid::uuid4(), 'Widget', 3, 250);

        self::assertSame(750, $line->totalUnitPrice());
    }

    #[DataProvider('billabilityCases')]
    public function test_billable_only_when_quantity_and_unit_price_positive(int $quantity, int $unitPrice, bool $billable): void
    {
        $line = new ProductLine(Uuid::uuid4(), 'Widget', $quantity, $unitPrice);

        self::assertSame($billable, $line->isBillable());
    }

    public static function billabilityCases(): iterable
    {
        yield 'positive' => [1, 1, true];
        yield 'zero quantity' => [0, 100, false];
        yield 'zero unit price' => [5, 0, false];
        yield 'both zero' => [0, 0, false];
    }

    #[DataProvider('invalidLines')]
    public function test_rejects_invalid_data(string $name, int $quantity, int $unitPrice): void
    {
        $this->expectException(InvalidInvoiceException::class);

        new ProductLine(Uuid::uuid4(), $name, $quantity, $unitPrice);
    }

    public static function invalidLines(): iterable
    {
        yield 'empty name' => ['  ', 1, 1];
        yield 'negative quantity' => ['Widget', -1, 1];
        yield 'negative unit price' => ['Widget', 1, -1];
        yield 'total overflows integer' => ['Widget', PHP_INT_MAX, 2];
    }
}
