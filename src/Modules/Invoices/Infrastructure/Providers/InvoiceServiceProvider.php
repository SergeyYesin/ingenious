<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Modules\Invoices\Application\Contracts\InvoiceNotifierInterface;
use Modules\Invoices\Application\Queries\InvoiceReadModelInterface;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Infrastructure\Console\RelayInvoiceOutboxCommand;
use Modules\Invoices\Infrastructure\Listeners\MarkInvoiceDeliveredListener;
use Modules\Invoices\Infrastructure\Notifications\NotificationFacadeInvoiceNotifier;
use Modules\Invoices\Infrastructure\Persistence\EloquentInvoiceRepository;
use Modules\Invoices\Infrastructure\ReadModel\DatabaseInvoiceReadModel;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;

final class InvoiceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(InvoiceRepositoryInterface::class, EloquentInvoiceRepository::class);
        $this->app->scoped(InvoiceReadModelInterface::class, DatabaseInvoiceReadModel::class);
        $this->app->scoped(InvoiceNotifierInterface::class, NotificationFacadeInvoiceNotifier::class);
    }

    public function boot(Dispatcher $events): void
    {
        $events->listen(WebhookDeliveredEvent::class, MarkInvoiceDeliveredListener::class);

        $this->commands([RelayInvoiceOutboxCommand::class]);

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command(RelayInvoiceOutboxCommand::class)->everySecond()->withoutOverlapping();
        });
    }
}
