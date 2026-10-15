<?php

namespace App\Services\Shopify;

use RuntimeException;
use Throwable;

final class ShopifyCustomerLookupException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $httpStatus,
        string $message,
        public readonly ?string $publicCustomerId = null,
        public readonly ?string $storeDomain = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
