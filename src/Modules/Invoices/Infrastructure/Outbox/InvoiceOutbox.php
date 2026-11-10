<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Outbox;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class InvoiceOutbox
{
    public const string TABLE = 'invoice_outbox';

    public const string SENDING_STARTED = 'invoice.sending_started';

    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function append(string $type, UuidInterface $invoiceId): void
    {
        $this->connection->table(self::TABLE)->insert([
            'id' => Uuid::uuid7()->toString(),
            'type' => $type,
            'invoice_id' => $invoiceId->toString(),
            'created_at' => Carbon::now(),
        ]);
    }
}
