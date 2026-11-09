<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Invoices\Application\Services\InvoiceService;
use Ramsey\Uuid\Uuid;
use Throwable;

final class SendInvoiceNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30];

    public function __construct(
        public readonly string $invoiceId,
    ) {}

    public function handle(InvoiceService $invoiceService): void
    {
        $invoiceService->notifyCustomer(Uuid::fromString($this->invoiceId));
    }

    public function failed(?Throwable $exception): void
    {
        app(InvoiceService::class)
            ->abandonSending(Uuid::fromString($this->invoiceId), $exception);
    }
}
