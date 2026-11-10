<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\ValueObjects;

use Modules\Invoices\Domain\Exceptions\InvalidInvoiceException;

final readonly class Email
{
    public const int MAX_LENGTH = 255;

    public function __construct(
        public string $value,
    ) {
        if (mb_strlen($value) > self::MAX_LENGTH || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidInvoiceException("Customer email {$value} is not a valid email address.");
        }
    }
}
