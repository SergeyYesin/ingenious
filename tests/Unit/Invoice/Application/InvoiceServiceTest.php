<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Application;

use Illuminate\Contracts\Bus\Dispatcher;
use Modules\Invoices\Application\Data\CreateInvoiceData;
use Modules\Invoices\Application\Data\ProductLineData;
use Modules\Invoices\Application\Exceptions\InvoiceNotificationFailedException;
use Modules\Invoices\Application\Jobs\SendInvoiceNotificationJob;
use Modules\Invoices\Application\Services\InvoiceService;
use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Entities\ProductLine;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvalidInvoiceException;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Notifications\Api\Data\NotifyData;
use Modules\Notifications\Api\NotificationFacadeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final class InvoiceServiceTest extends TestCase
{
    public function test_create_adds_draft_invoice_with_product_lines(): void
    {
        $repository = $this->createMock(InvoiceRepositoryInterface::class);
        $repository->expects(self::once())->method('add')->with(self::isInstanceOf(Invoice::class));

        $invoice = $this->service($repository)->create(new CreateInvoiceData(
            'Jane Doe',
            'jane@example.com',
            new ProductLineData('Widget', 2, 100),
            new ProductLineData('Gadget', 0, 50),
        ));

        self::assertSame(StatusEnum::Draft, $invoice->status);
        self::assertSame(['Widget', 'Gadget'], array_map(
            static fn (ProductLine $line): string => $line->name,
            $invoice->productLines,
        ));
        self::assertSame(200, $invoice->totalPrice);
    }

    public function test_get_throws_for_unknown_invoice(): void
    {
        $this->expectException(InvoiceNotFoundException::class);

        $this->service($this->repositoryFinding(null))->get(Uuid::uuid4());
    }

    public function test_send_saves_sending_before_dispatching_job(): void
    {
        $invoice = $this->invoice(StatusEnum::Draft);
        $calls = [];

        $repository = $this->repositoryFinding($invoice);
        $repository->method('saveStatus')->willReturnCallback(
            static function (Invoice $saved, StatusEnum $expected) use (&$calls): void {
                $calls[] = "save {$expected->value} -> {$saved->status->value}";
            },
        );

        $bus = $this->createMock(Dispatcher::class);
        $bus->expects(self::once())->method('dispatch')->willReturnCallback(
            static function (SendInvoiceNotificationJob $job) use (&$calls): void {
                $calls[] = "dispatch {$job->invoiceId}";
            },
        );

        $result = $this->service($repository, bus: $bus)->send($invoice->id);

        self::assertSame(StatusEnum::Sending, $result->status);
        self::assertSame(['save draft -> sending', "dispatch {$invoice->id->toString()}"], $calls);
    }

    public function test_send_does_not_save_or_dispatch_unsendable_invoice(): void
    {
        $repository = $this->repositoryFinding($this->invoice(StatusEnum::Draft, $this->line(0, 100)));
        $repository->expects(self::never())->method('saveStatus');

        $bus = $this->createMock(Dispatcher::class);
        $bus->expects(self::never())->method('dispatch');

        $this->expectException(InvalidInvoiceException::class);

        $this->service($repository, bus: $bus)->send(Uuid::uuid4());
    }

    public function test_send_returns_invoice_to_draft_when_dispatch_fails(): void
    {
        $invoice = $this->invoice(StatusEnum::Draft);
        $saves = [];

        $repository = $this->createMock(InvoiceRepositoryInterface::class);
        $repository->expects(self::once())->method('find')->willReturn($invoice);
        $repository->method('saveStatus')->willReturnCallback(
            static function (Invoice $saved, StatusEnum $expected) use (&$saves): void {
                $saves[] = [$expected, $saved->status];
            },
        );

        $bus = $this->createStub(Dispatcher::class);
        $bus->method('dispatch')->willThrowException($reason = new RuntimeException('Redis is down'));

        try {
            $this->service($repository, bus: $bus)->send($invoice->id);
            self::fail('Expected InvoiceNotificationFailedException');
        } catch (InvoiceNotificationFailedException $exception) {
            self::assertSame($reason, $exception->getPrevious());
        }

        self::assertSame([
            [StatusEnum::Draft, StatusEnum::Sending],
            [StatusEnum::Sending, StatusEnum::Draft],
        ], $saves);
    }

    public function test_notify_customer_sends_invoice_details(): void
    {
        $invoice = $this->invoice(StatusEnum::Sending, $this->line(2, 100), $this->line(3, 50));

        $notifications = $this->createMock(NotificationFacadeInterface::class);
        $notifications->expects(self::once())->method('notify')->with(self::callback(
            static fn (NotifyData $data): bool => $data->resourceId->equals($invoice->id)
                && $data->toEmail === 'jane@example.com'
                && $data->subject === "Invoice {$invoice->id->toString()}"
                && $data->message === 'Hello Jane Doe, your invoice total is 350.',
        ));

        $this->service($this->repositoryFinding($invoice), $notifications)->notifyCustomer($invoice->id);
    }

    #[DataProvider('notSendingStatuses')]
    public function test_notify_customer_skips_invoice_not_in_sending(?StatusEnum $status): void
    {
        $notifications = $this->createMock(NotificationFacadeInterface::class);
        $notifications->expects(self::never())->method('notify');

        $invoice = $status === null ? null : $this->invoice($status);

        $this->service($this->repositoryFinding($invoice), $notifications)->notifyCustomer(Uuid::uuid4());
    }

    public function test_abandon_sending_returns_invoice_to_draft(): void
    {
        $invoice = $this->invoice(StatusEnum::Sending);

        $repository = $this->repositoryFinding($invoice);
        $repository->expects(self::once())->method('saveStatus')->with($invoice, StatusEnum::Sending);

        $this->service($repository)->abandonSending($invoice->id, new RuntimeException('Mail server down'));

        self::assertSame(StatusEnum::Draft, $invoice->status);
    }

    #[DataProvider('notSendingStatuses')]
    public function test_abandon_sending_ignores_invoice_not_in_sending(?StatusEnum $status): void
    {
        $repository = $this->repositoryFinding($status === null ? null : $this->invoice($status));
        $repository->expects(self::never())->method('saveStatus');

        $this->service($repository)->abandonSending(Uuid::uuid4(), new RuntimeException('Mail server down'));
    }

    public function test_abandon_sending_keeps_status_changed_concurrently(): void
    {
        $repository = $this->repositoryFinding($this->invoice(StatusEnum::Sending));
        $repository->expects(self::once())->method('saveStatus')->willThrowException($this->lostRace());

        $this->service($repository)->abandonSending(Uuid::uuid4(), new RuntimeException('Mail server down'));
    }

    public function test_mark_delivered_moves_sending_to_sent_to_client(): void
    {
        $invoice = $this->invoice(StatusEnum::Sending);

        $repository = $this->repositoryFinding($invoice);
        $repository->expects(self::once())->method('saveStatus')->with($invoice, StatusEnum::Sending);

        $this->service($repository)->markDelivered($invoice->id);

        self::assertSame(StatusEnum::SentToClient, $invoice->status);
    }

    #[DataProvider('notSendingStatuses')]
    public function test_mark_delivered_ignores_invoice_not_in_sending(?StatusEnum $status): void
    {
        $repository = $this->repositoryFinding($status === null ? null : $this->invoice($status));
        $repository->expects(self::never())->method('saveStatus');

        $this->service($repository)->markDelivered(Uuid::uuid4());
    }

    public function test_mark_delivered_gives_up_when_status_changes_concurrently(): void
    {
        $repository = $this->repositoryFinding($this->invoice(StatusEnum::Sending));
        $repository->expects(self::once())->method('saveStatus')->willThrowException($this->lostRace());

        $this->service($repository)->markDelivered(Uuid::uuid4());
    }

    public static function notSendingStatuses(): iterable
    {
        yield 'unknown invoice' => [null];
        yield 'draft' => [StatusEnum::Draft];
        yield 'sent to client' => [StatusEnum::SentToClient];
    }

    private function service(
        InvoiceRepositoryInterface $repository,
        ?NotificationFacadeInterface $notifications = null,
        ?Dispatcher $bus = null,
    ): InvoiceService {
        return new InvoiceService(
            $repository,
            $notifications ?? $this->createStub(NotificationFacadeInterface::class),
            $bus ?? $this->createStub(Dispatcher::class),
            new NullLogger,
        );
    }

    private function repositoryFinding(?Invoice $invoice): InvoiceRepositoryInterface&MockObject
    {
        $repository = $this->createMock(InvoiceRepositoryInterface::class);
        $repository->method('find')->willReturn($invoice);

        return $repository;
    }

    private function lostRace(): InvalidStatusTransitionException
    {
        return new InvalidStatusTransitionException(Uuid::uuid4(), StatusEnum::Sending, StatusEnum::Draft);
    }

    private function invoice(StatusEnum $status, ProductLine ...$lines): Invoice
    {
        $invoice = Invoice::create(
            Uuid::uuid4(),
            'Jane Doe',
            'jane@example.com',
            ...($lines === [] ? [$this->line(1, 100)] : $lines),
        );

        if ($status !== StatusEnum::Draft) {
            $invoice->send();
        }

        if ($status === StatusEnum::SentToClient) {
            $invoice->markDelivered();
        }

        return $invoice;
    }

    private function line(int $quantity, int $unitPrice): ProductLine
    {
        return new ProductLine(Uuid::uuid4(), 'Widget', $quantity, $unitPrice);
    }
}
