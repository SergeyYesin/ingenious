<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Domain\ValueObjects;

use Modules\Invoices\Domain\Exceptions\InvalidInvoiceException;
use Modules\Invoices\Domain\ValueObjects\CustomerName;
use Modules\Invoices\Domain\ValueObjects\ProductName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NameTest extends TestCase
{
    #[DataProvider('nameTypes')]
    public function test_keeps_name_up_to_maximum_length(string $class): void
    {
        $name = str_repeat('ä', 255);

        self::assertSame($name, new $class($name)->value);
    }

    #[DataProvider('invalidNames')]
    public function test_rejects_invalid_name(string $class, string $name): void
    {
        $this->expectException(InvalidInvoiceException::class);

        new $class($name);
    }

    public static function nameTypes(): iterable
    {
        yield 'customer name' => [CustomerName::class];
        yield 'product name' => [ProductName::class];
    }

    public static function invalidNames(): iterable
    {
        foreach (self::nameTypes() as $type => [$class]) {
            yield "{$type} empty" => [$class, ''];
            yield "{$type} blank" => [$class, '   '];
            yield "{$type} too long" => [$class, str_repeat('a', 256)];
        }
    }
}
