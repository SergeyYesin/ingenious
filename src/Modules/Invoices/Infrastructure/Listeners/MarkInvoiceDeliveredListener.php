<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Listeners;

use Modules\Invoices\Application\Services\InvoiceService;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;

final readonly class MarkInvoiceDeliveredListener
{
    public function __construct(
        private InvoiceService $invoiceService,
    ) {}

    public function handle(WebhookDeliveredEvent $event): void
    {
        $this->invoiceService->markDelivered($event->resourceId);
    }
}
