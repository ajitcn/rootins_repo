<?php

namespace App\Services\Shopify;

use RuntimeException;

final class ShopifyConfigurationException extends RuntimeException
{
    public readonly string $errorCode;

    public function __construct(string $message, string $errorCode = 'configuration_error')
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
    }
}
