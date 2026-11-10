<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Outbox;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Modules\Invoices\Infrastructure\Queue\SendInvoiceNotificationJob;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class InvoiceOutboxRelay
{
    public function __construct(
        private ConnectionInterface $connection,
        private Dispatcher $bus,
        private LoggerInterface $logger,
    ) {}

    public function relay(int $limit = 100): int
    {
        $relayed = 0;

        try {
            while ($relayed < $limit && $this->connection->transaction(fn (): bool => $this->relayNext())) {
                $relayed++;
            }
        } catch (Throwable $exception) {
            $this->logger->error('Invoice outbox relay stopped, pending messages will be retried on the next run', [
                'exception' => $exception,
            ]);
        }

        return $relayed;
    }

    private function relayNext(): bool
    {
        $message = $this->connection->table(InvoiceOutbox::TABLE)
            ->whereNull('dispatched_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($message === null) {
            return false;
        }

        $this->bus->dispatch(match ($message->type) {
            InvoiceOutbox::SENDING_STARTED => new SendInvoiceNotificationJob($message->invoice_id),
        });

        $this->connection->table(InvoiceOutbox::TABLE)
            ->where('id', $message->id)
            ->update(['dispatched_at' => Carbon::now()]);

        return true;
    }
}
