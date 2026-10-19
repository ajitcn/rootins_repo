<?php

namespace App\Services\Shopify;

final class ShopifyProxySignatureService
{
    /** @return array<string, string|list<string>> */
    public function query(string $queryString): array
    {
        $query = [];
        foreach (explode('&', $queryString) as $part) {
            if ($part === '') continue;
            [$rawKey, $rawValue] = array_pad(explode('=', $part, 2), 2, '');
            $key = urldecode($rawKey);
            if ($key === '') continue;
            $value = urldecode($rawValue);
            if (! array_key_exists($key, $query)) {
                $query[$key] = $value;
            } else {
                $query[$key] = is_array($query[$key]) ? [...$query[$key], $value] : [$query[$key], $value];
            }
        }
        return $query;
    }

    /** @param array<string, string|list<string>> $query */
    public function canonical(array $query): string
    {
        unset($query['signature']);
        ksort($query, SORT_STRING);
        $canonical = '';
        foreach ($query as $key => $value) {
            $canonical .= $key . '=' . (is_array($value) ? implode(',', $value) : $value);
        }
        return $canonical;
    }
}
