<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Services;

use Illuminate\Contracts\Bus\Dispatcher;
use Modules\Invoices\Application\Data\CreateInvoiceData;
use Modules\Invoices\Application\Data\ProductLineData;
use Modules\Invoices\Application\Exceptions\InvoiceNotificationFailedException;
use Modules\Invoices\Application\Jobs\SendInvoiceNotificationJob;
use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Entities\ProductLine;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Notifications\Api\Data\NotifyData;
use Modules\Notifications\Api\NotificationFacadeInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Throwable;

final readonly class InvoiceService
{
    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private NotificationFacadeInterface $notifications,
        private Dispatcher $bus,
        private LoggerInterface $logger,
    ) {}

    public function create(CreateInvoiceData $data): Invoice
    {
        $invoice = Invoice::create(
            Uuid::uuid4(),
            $data->customerName,
            $data->customerEmail,
            ...array_map(
                static fn (ProductLineData $line): ProductLine => new ProductLine(
                    id: Uuid::uuid7(),
                    name: $line->name,
                    quantity: $line->quantity,
                    unitPrice: $line->unitPrice,
                ),
                $data->productLines,
            ),
        );

        $this->invoices->add($invoice);

        return $invoice;
    }

    public function get(UuidInterface $id): Invoice
    {
        return $this->invoices->find($id) ?? throw new InvoiceNotFoundException($id);
    }

    public function send(UuidInterface $id): Invoice
    {
        $invoice = $this->get($id);

        $invoice->send();
        $this->invoices->saveStatus($invoice, StatusEnum::Draft);

        try {
            $this->bus->dispatch(new SendInvoiceNotificationJob($invoice->id->toString()));
        } catch (Throwable $exception) {
            $this->revertToDraft($invoice, $exception);

            throw new InvoiceNotificationFailedException($invoice->id, $exception);
        }

        return $invoice;
    }

    public function notifyCustomer(UuidInterface $id): void
    {
        $invoice = $this->invoices->find($id);

        if ($invoice?->status !== StatusEnum::Sending) {
            $this->logger->info(
                'Invoice notification skipped for invoice not in sending status',
                [
                    'invoice_id' => $id->toString(),
                    'status' => $invoice?->status->value,
                ]
            );

            return;
        }

        $this->notifications->notify(new NotifyData(
            resourceId: $invoice->id,
            toEmail: $invoice->customerEmail,
            subject: "Invoice {$invoice->id->toString()}",
            message: "Hello {$invoice->customerName}, your invoice total is {$invoice->totalPrice}.",
        ));
    }

    public function abandonSending(UuidInterface $id, ?Throwable $reason): void
    {
        $invoice = $this->invoices->find($id);

        if ($invoice?->status !== StatusEnum::Sending) {
            $this->logger->info(
                'Invoice notification failure ignored for invoice not in sending status',
                [
                    'invoice_id' => $id->toString(),
                    'status' => $invoice?->status->value,
                ]
            );

            return;
        }

        $this->revertToDraft($invoice, $reason);
    }

    public function markDelivered(UuidInterface $id): void
    {
        $invoice = $this->invoices->find($id);

        if ($invoice === null) {
            return;
        }

        if ($invoice->status !== StatusEnum::Sending) {
            $this->logger->info(
                'Delivery webhook ignored for invoice not in sending status',
                [
                    'invoice_id' => $id->toString(),
                    'status' => $invoice->status->value,
                ]
            );

            return;
        }

        $invoice->markDelivered();

        try {
            $this->invoices->saveStatus($invoice, StatusEnum::Sending);
        } catch (InvalidStatusTransitionException) {
            $this->logger->info(
                'Delivery webhook lost a race with another status change',
                [
                    'invoice_id' => $id->toString(),
                ]
            );
        }
    }

    private function revertToDraft(Invoice $invoice, ?Throwable $reason): void
    {
        $this->logger->error(
            'Invoice notification failed, returning invoice to draft',
            [
                'invoice_id' => $invoice->id->toString(),
                'exception' => $reason,
            ]
        );

        $invoice->cancelSending();

        try {
            $this->invoices->saveStatus($invoice, StatusEnum::Sending);
        } catch (InvalidStatusTransitionException) {
            $this->logger->info(
                'Invoice status changed while returning it to draft, keeping the newer status',
                [
                    'invoice_id' => $invoice->id->toString(),
                ]
            );
        }
    }
}
