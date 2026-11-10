<?php

declare(strict_types=1);

namespace Tests\Support\Invoices;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Entities\ProductLine;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\ValueObjects\CustomerName;
use Modules\Invoices\Domain\ValueObjects\Email;
use Modules\Invoices\Domain\ValueObjects\Money;
use Modules\Invoices\Domain\ValueObjects\ProductName;
use Modules\Invoices\Domain\ValueObjects\Quantity;
use Ramsey\Uuid\Uuid;

final class InvoiceMother
{
    public static function invoice(StatusEnum $status = StatusEnum::Draft, ProductLine ...$lines): Invoice
    {
        $invoice = Invoice::create(
            Uuid::uuid4(),
            new CustomerName('Jane Doe'),
            new Email('jane@example.com'),
            ...($lines === [] ? [self::line()] : $lines),
        );

        if ($status !== StatusEnum::Draft) {
            $invoice->send();
        }

        if ($status === StatusEnum::SentToClient) {
            $invoice->markDelivered();
        }

        $invoice->clearRecordedEvents();

        return $invoice;
    }

    public static function withoutLines(): Invoice
    {
        return Invoice::create(Uuid::uuid4(), new CustomerName('Jane Doe'), new Email('jane@example.com'));
    }

    public static function line(int $quantity = 1, int $unitPrice = 100, string $name = 'Widget'): ProductLine
    {
        return new ProductLine(Uuid::uuid7(), new ProductName($name), new Quantity($quantity), new Money($unitPrice));
    }
}
