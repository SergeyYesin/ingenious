<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Contracts;

use Modules\Invoices\Domain\ValueObjects\InvoiceNotification;

interface InvoiceNotifierInterface
{
    public function notify(InvoiceNotification $notification): void;
}
