<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Repositories;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Ramsey\Uuid\UuidInterface;

interface InvoiceRepositoryInterface
{
    public function find(UuidInterface $id): ?Invoice;

    public function add(Invoice $invoice): void;

    public function saveStatus(Invoice $invoice, StatusEnum $expectedStatus): void;
}
