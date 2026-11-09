<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Invoices\Application\Services\InvoiceService;
use Modules\Invoices\Presentation\Http\Requests\CreateInvoiceRequest;
use Modules\Invoices\Presentation\Http\Resources\InvoiceResource;
use Symfony\Component\HttpFoundation\Response;

final readonly class CreateInvoiceController
{
    public function __construct(
        private InvoiceService $invoiceService,
    ) {}

    public function __invoke(CreateInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->create($request->toData());

        return new InvoiceResource($invoice)->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
