<?php

namespace App\Services\Shopify;

use RuntimeException;

final class ShopifyProxyCredentialVerificationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'SHOPIFY_PROXY_CONNECTION_FAILED',
        public readonly int $httpStatus = 503,
    ) {
        parent::__construct($message);
    }
}
