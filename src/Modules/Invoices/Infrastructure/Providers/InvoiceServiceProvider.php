<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Providers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Infrastructure\Listeners\MarkInvoiceDeliveredListener;
use Modules\Invoices\Infrastructure\Persistence\EloquentInvoiceRepository;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;

final class InvoiceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(InvoiceRepositoryInterface::class, EloquentInvoiceRepository::class);
    }

    public function boot(Dispatcher $events): void
    {
        $events->listen(WebhookDeliveredEvent::class, MarkInvoiceDeliveredListener::class);
    }
}
