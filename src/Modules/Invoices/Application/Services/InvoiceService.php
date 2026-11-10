<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Services;

use Closure;
use Modules\Invoices\Application\Contracts\InvoiceNotifierInterface;
use Modules\Invoices\Application\Data\CreateInvoiceData;
use Modules\Invoices\Application\Data\ProductLineData;
use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Entities\ProductLine;
use Modules\Invoices\Domain\Exceptions\ConcurrentModificationException;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotSendingException;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Domain\ValueObjects\CustomerName;
use Modules\Invoices\Domain\ValueObjects\Email;
use Modules\Invoices\Domain\ValueObjects\Money;
use Modules\Invoices\Domain\ValueObjects\ProductName;
use Modules\Invoices\Domain\ValueObjects\Quantity;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Throwable;

final readonly class InvoiceService
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private InvoiceNotifierInterface $notifier,
        private LoggerInterface $logger,
    ) {}

    public function create(CreateInvoiceData $data): UuidInterface
    {
        $invoice = Invoice::create(
            Uuid::uuid4(),
            new CustomerName($data->customerName),
            new Email($data->customerEmail),
            ...array_map(
                static fn (ProductLineData $line): ProductLine => new ProductLine(
                    id: Uuid::uuid7(),
                    name: new ProductName($line->name),
                    quantity: new Quantity($line->quantity),
                    unitPrice: new Money($line->unitPrice),
                ),
                $data->productLines,
            ),
        );

        $this->invoices->save($invoice);

        return $invoice->id;
    }

    public function send(UuidInterface $id): void
    {
        $invoice = $this->invoices->find($id) ?? throw new InvoiceNotFoundException($id);

        $invoice->send();

        $this->invoices->save($invoice);
    }

    public function notifyCustomer(UuidInterface $id): void
    {
        try {
            $notification = $this->invoices->find($id)?->notification();
        } catch (InvoiceNotSendingException $exception) {
            $this->logger->info('Invoice notification skipped', [
                'invoice_id' => $id->toString(),
                'reason' => $exception->getMessage(),
            ]);

            return;
        }

        if ($notification !== null) {
            $this->notifier->notify($notification);
        }
    }

    public function abandonSending(UuidInterface $id, ?Throwable $reason): void
    {
        $this->logger->error('Invoice notification failed, returning invoice to draft', [
            'invoice_id' => $id->toString(),
            'exception' => $reason,
        ]);

        $this->changeStatus($id, static fn (Invoice $invoice) => $invoice->cancelSending());
    }

    public function markDelivered(UuidInterface $id): void
    {
        $this->changeStatus($id, static fn (Invoice $invoice) => $invoice->markDelivered());
    }

    private function changeStatus(UuidInterface $id, Closure $change): void
    {
        $invoice = $this->invoices->find($id);

        if ($invoice === null) {
            return;
        }

        try {
            $change($invoice);
            $this->invoices->save($invoice);
        } catch (InvalidStatusTransitionException|ConcurrentModificationException $exception) {
            $this->logger->info('Invoice status change skipped', [
                'invoice_id' => $id->toString(),
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
