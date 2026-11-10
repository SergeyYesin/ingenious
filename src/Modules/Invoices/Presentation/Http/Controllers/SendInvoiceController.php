<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Invoices\Application\Queries\InvoiceQueryService;
use Modules\Invoices\Application\Services\InvoiceService;
use Modules\Invoices\Presentation\Http\Resources\InvoiceResource;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

final readonly class SendInvoiceController
{
    public function __construct(
        private InvoiceService $invoiceService,
        private InvoiceQueryService $invoiceQueries,
    ) {}

    public function __invoke(string $id): JsonResponse
    {
        $invoiceId = Uuid::fromString($id);

        $this->invoiceService->send($invoiceId);

        return new InvoiceResource($this->invoiceQueries->get($invoiceId))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
