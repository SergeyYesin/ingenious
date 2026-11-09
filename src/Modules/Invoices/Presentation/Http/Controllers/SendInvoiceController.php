<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Invoices\Application\Services\InvoiceService;
use Modules\Invoices\Presentation\Http\Resources\InvoiceResource;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

final readonly class SendInvoiceController
{
    public function __construct(
        private InvoiceService $invoiceService,
    ) {}

    public function __invoke(string $id): JsonResponse
    {
        $invoice = $this->invoiceService->send(Uuid::fromString($id));

        return new InvoiceResource($invoice)->response()->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
