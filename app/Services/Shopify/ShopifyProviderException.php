<?php

namespace App\Services\Shopify;

use RuntimeException;
use Throwable;

final class ShopifyProviderException extends RuntimeException
{
    public readonly ?int $httpStatus;
    public readonly string $errorCode;
    public readonly string $category;

    public function __construct(string $message, ?int $httpStatus = null, string $errorCode = 'provider_error', string $category = 'provider_error', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->httpStatus = $httpStatus;
        $this->errorCode = $errorCode;
        $this->category = $category;
    }
}
