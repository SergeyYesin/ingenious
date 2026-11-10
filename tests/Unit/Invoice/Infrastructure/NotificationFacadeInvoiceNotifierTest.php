<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Infrastructure;

use Modules\Invoices\Domain\ValueObjects\Email;
use Modules\Invoices\Domain\ValueObjects\InvoiceNotification;
use Modules\Invoices\Infrastructure\Notifications\NotificationFacadeInvoiceNotifier;
use Modules\Notifications\Api\Data\NotifyData;
use Modules\Notifications\Api\NotificationFacadeInterface;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class NotificationFacadeInvoiceNotifierTest extends TestCase
{
    public function test_translates_invoice_notification_to_notification_module_call(): void
    {
        $facade = new class implements NotificationFacadeInterface
        {
            public array $received = [];

            public function notify(NotifyData $data): void
            {
                $this->received[] = $data;
            }
        };

        $invoiceId = Uuid::uuid4();

        new NotificationFacadeInvoiceNotifier($facade)->notify(new InvoiceNotification(
            $invoiceId,
            new Email('jane@example.com'),
            'Invoice subject',
            'Invoice message',
        ));

        self::assertEquals(
            [new NotifyData($invoiceId, 'jane@example.com', 'Invoice subject', 'Invoice message')],
            $facade->received,
        );
    }
}
