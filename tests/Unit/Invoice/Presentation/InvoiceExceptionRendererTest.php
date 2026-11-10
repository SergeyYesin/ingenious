<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Presentation;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\ConcurrentModificationException;
use Modules\Invoices\Domain\Exceptions\InvalidInvoiceException;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\Invoices\Domain\Exceptions\InvoiceException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use Modules\Invoices\Presentation\Http\InvoiceExceptionRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class InvoiceExceptionRendererTest extends TestCase
{
    #[DataProvider('exceptions')]
    public function test_maps_exception_to_status_with_its_message(InvoiceException $exception, int $status): void
    {
        $response = InvoiceExceptionRenderer::render($exception);

        self::assertSame($status, $response->getStatusCode());
        self::assertSame(['message' => $exception->getMessage()], $response->getData(true));
    }

    public static function exceptions(): iterable
    {
        yield 'not found' => [new InvoiceNotFoundException(Uuid::uuid4()), 404];
        yield 'invalid status transition' => [new InvalidStatusTransitionException(Uuid::uuid4(), StatusEnum::Sending, StatusEnum::Sending), 409];
        yield 'concurrent modification' => [new ConcurrentModificationException(Uuid::uuid4()), 409];
        yield 'invalid invoice' => [new InvalidInvoiceException('Customer name must not be empty.'), 422];
    }

    public function test_falls_back_to_internal_server_error_for_unmapped_exception(): void
    {
        $response = InvoiceExceptionRenderer::render(new class('Unexpected') extends InvoiceException {});

        self::assertSame(500, $response->getStatusCode());
    }
}
