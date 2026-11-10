<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Repositories;

use Modules\Invoices\Domain\Entities\Invoice;
use Ramsey\Uuid\UuidInterface;

interface InvoiceRepositoryInterface
{
    public function find(UuidInterface $id): ?Invoice;

    public function save(Invoice $invoice): void;
}
