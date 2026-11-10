<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http;

use Illuminate\Http\JsonResponse;
use Modules\Invoices\Domain\Exceptions\ConcurrentModificationException;
use Modules\Invoices\Domain\Exceptions\InvalidInvoiceException;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\Invoices\Domain\Exceptions\InvoiceException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use Symfony\Component\HttpFoundation\Response;

final class InvoiceExceptionRenderer
{
    public static function render(InvoiceException $exception): JsonResponse
    {
        $status = match (true) {
            $exception instanceof InvoiceNotFoundException => Response::HTTP_NOT_FOUND,
            $exception instanceof InvalidStatusTransitionException,
            $exception instanceof ConcurrentModificationException => Response::HTTP_CONFLICT,
            $exception instanceof InvalidInvoiceException => Response::HTTP_UNPROCESSABLE_ENTITY,
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };

        return new JsonResponse(['message' => $exception->getMessage()], $status);
    }
}
