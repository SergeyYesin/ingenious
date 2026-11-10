<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Console;

use Illuminate\Console\Command;
use Modules\Invoices\Infrastructure\Outbox\InvoiceOutboxRelay;

final class RelayInvoiceOutboxCommand extends Command
{
    protected $signature = 'invoices:relay-outbox';

    protected $description = 'Dispatch pending invoice outbox messages to the queue';

    public function handle(InvoiceOutboxRelay $relay): int
    {
        $this->components->info("Relayed {$relay->relay()} invoice outbox messages.");

        return self::SUCCESS;
    }
}
