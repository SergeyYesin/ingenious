<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Queries;

use Modules\Invoices\Application\Queries\Views\InvoiceView;
use Ramsey\Uuid\UuidInterface;

interface InvoiceReadModelInterface
{
    public function find(UuidInterface $id): ?InvoiceView;
}
