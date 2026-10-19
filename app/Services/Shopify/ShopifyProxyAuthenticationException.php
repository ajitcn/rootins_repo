<?php

namespace App\Services\Shopify;

use RuntimeException;

final class ShopifyProxyAuthenticationException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode = 'SHOPIFY_PROXY_AUTHENTICATION_FAILED', public readonly int $httpStatus = 401)
    {
        parent::__construct($message);
    }
}
