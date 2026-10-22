<?php

namespace App\Services\Shopify;

use RuntimeException;
use Throwable;

final class ShopifyMappingException extends RuntimeException
{
    /** @param array<string, string> $fields */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $fields = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
