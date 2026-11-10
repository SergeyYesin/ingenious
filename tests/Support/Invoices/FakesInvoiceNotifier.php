<?php

declare(strict_types=1);

namespace Tests\Support\Invoices;

use Modules\Invoices\Application\Contracts\InvoiceNotifierInterface;

trait FakesInvoiceNotifier
{
    protected function fakeInvoiceNotifier(): RecordingInvoiceNotifier
    {
        $notifier = new RecordingInvoiceNotifier;

        $this->app->scoped(InvoiceNotifierInterface::class, static fn (): RecordingInvoiceNotifier => $notifier);

        return $notifier;
    }
}
