<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Domain\ValueObjects;

use Modules\Invoices\Domain\Exceptions\InvalidInvoiceException;
use Modules\Invoices\Domain\ValueObjects\Email;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function test_keeps_valid_address(): void
    {
        self::assertSame('jane@example.com', new Email('jane@example.com')->value);
    }

    #[DataProvider('invalidAddresses')]
    public function test_rejects_invalid_address(string $address): void
    {
        $this->expectException(InvalidInvoiceException::class);

        new Email($address);
    }

    public static function invalidAddresses(): iterable
    {
        yield 'no at sign' => ['not-an-email'];
        yield 'no domain' => ['jane@'];
        yield 'empty' => [''];
        yield 'longer than column' => [str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 63).'.com'];
    }
}
