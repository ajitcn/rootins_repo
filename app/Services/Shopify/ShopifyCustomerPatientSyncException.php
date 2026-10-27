<?php

namespace App\Services\Shopify;

use RuntimeException;

final class ShopifyCustomerPatientSyncException extends RuntimeException
{
    public function __construct(public readonly string $category, string $message)
    {
        parent::__construct($message);
    }
}
