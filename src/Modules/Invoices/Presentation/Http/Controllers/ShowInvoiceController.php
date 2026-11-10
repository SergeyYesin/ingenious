<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http\Controllers;

use Modules\Invoices\Application\Queries\InvoiceQueryService;
use Modules\Invoices\Presentation\Http\Resources\InvoiceResource;
use Ramsey\Uuid\Uuid;

final readonly class ShowInvoiceController
{
    public function __construct(
        private InvoiceQueryService $invoiceQueries,
    ) {}

    public function __invoke(string $id): InvoiceResource
    {
        return new InvoiceResource($this->invoiceQueries->get(Uuid::fromString($id)));
    }
}
