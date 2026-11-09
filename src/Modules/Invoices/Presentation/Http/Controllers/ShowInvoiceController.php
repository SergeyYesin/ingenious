<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http\Controllers;

use Modules\Invoices\Application\Services\InvoiceService;
use Modules\Invoices\Presentation\Http\Resources\InvoiceResource;
use Ramsey\Uuid\Uuid;

final readonly class ShowInvoiceController
{
    public function __construct(
        private InvoiceService $invoiceService,
    ) {}

    public function __invoke(string $id): InvoiceResource
    {
        return new InvoiceResource($this->invoiceService->get(Uuid::fromString($id)));
    }
}
