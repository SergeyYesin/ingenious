<?php

use Modules\Invoices\Infrastructure\Providers\InvoiceServiceProvider;
use Modules\Notifications\Infrastructure\Providers\NotificationServiceProvider;

return [
    NotificationServiceProvider::class,
    InvoiceServiceProvider::class,
];
