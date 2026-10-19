<?php

namespace App\Services\Shopify;

final class ShopifyProxySignatureService
{
    public function query(string $queryString): array { parse_str($queryString, $query); return is_array($query) ? $query : []; }
}
