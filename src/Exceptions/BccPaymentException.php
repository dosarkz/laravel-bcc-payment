<?php

namespace Dosarkz\BccPayment\Exceptions;

use RuntimeException;

class BccPaymentException extends RuntimeException
{
    public static function missingConfig(string $key): self
    {
        return new self("BCC payment config [bcc-payment.{$key}] is not set.");
    }
}
