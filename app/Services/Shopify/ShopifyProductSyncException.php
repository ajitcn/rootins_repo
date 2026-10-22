<?php

namespace App\Services\Shopify;

use RuntimeException;
use Throwable;

final class ShopifyProductSyncException extends RuntimeException
{
    public function __construct(public readonly string $category, ?Throwable $previous = null)
    {
        parent::__construct($category, 0, $previous);
    }
}
