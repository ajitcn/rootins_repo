<?php

namespace App\Services\Shopify;

use RuntimeException;

final class ShopifyProxyAuthenticationException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 401)
    {
        parent::__construct($message);
    }
}
