<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Notifications;

use Modules\Invoices\Application\Contracts\InvoiceNotifierInterface;
use Modules\Invoices\Domain\ValueObjects\InvoiceNotification;
use Modules\Notifications\Api\Data\NotifyData;
use Modules\Notifications\Api\NotificationFacadeInterface;

final readonly class NotificationFacadeInvoiceNotifier implements InvoiceNotifierInterface
{
    public function __construct(
        private NotificationFacadeInterface $notifications,
    ) {}

    public function notify(InvoiceNotification $notification): void
    {
        $this->notifications->notify(new NotifyData(
            resourceId: $notification->invoiceId,
            toEmail: $notification->recipient->value,
            subject: $notification->subject,
            message: $notification->message,
        ));
    }
}
