<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\ValueObjects;

use Modules\Invoices\Domain\Exceptions\InvalidInvoiceException;

final readonly class ProductName
{
    public const int MAX_LENGTH = 255;

    public function __construct(
        public string $value,
    ) {
        if (trim($value) === '') {
            throw new InvalidInvoiceException('Product name must not be empty.');
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidInvoiceException('Product name must not be longer than '.self::MAX_LENGTH.' characters.');
        }
    }
}
