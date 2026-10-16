<?php

namespace App\Services\Shopify;

use RuntimeException;
use Throwable;

final class ShopifyConfigurationException extends RuntimeException
{
}

final class ShopifyProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly string $errorCode = 'SHOPIFY_PROVIDER_ERROR',
        public readonly string $category = 'provider_error',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }
}

final class ShopifyIdempotencyException extends RuntimeException
{
}
