<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Queries;

use Modules\Invoices\Application\Queries\Views\InvoiceView;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use Ramsey\Uuid\UuidInterface;

final readonly class InvoiceQueryService
{
    public function __construct(
        private InvoiceReadModelInterface $readModel,
    ) {}

    public function get(UuidInterface $id): InvoiceView
    {
        return $this->readModel->find($id) ?? throw new InvoiceNotFoundException($id);
    }
}
