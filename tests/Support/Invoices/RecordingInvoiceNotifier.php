<?php

declare(strict_types=1);

namespace Tests\Support\Invoices;

use Modules\Invoices\Application\Contracts\InvoiceNotifierInterface;
use Modules\Invoices\Domain\ValueObjects\InvoiceNotification;
use RuntimeException;

final class RecordingInvoiceNotifier implements InvoiceNotifierInterface
{
    public array $sent = [];

    private int $failuresLeft = 0;

    public function failTimes(int $times): self
    {
        $this->failuresLeft = $times;

        return $this;
    }

    public function notify(InvoiceNotification $notification): void
    {
        if ($this->failuresLeft > 0) {
            $this->failuresLeft--;

            throw new RuntimeException('Mail server down');
        }

        $this->sent[] = $notification;
    }
}
