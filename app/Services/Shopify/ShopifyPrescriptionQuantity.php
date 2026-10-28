<?php

namespace App\Services\Shopify;

final class ShopifyPrescriptionQuantity
{
    public static function normalize(mixed $value): ?int
    {
        if ($value === null) return null;
        if (is_int($value)) return $value;
        if (is_float($value)) return is_finite($value) && floor($value) === $value ? (int) $value : null;
        if (! is_string($value)) return null;

        $value = trim($value);
        if ($value === '') return null;
        if (preg_match('/^\d+$/', $value) === 1) return (int) $value;
        if (preg_match('/^\d+\.0+$/', $value) === 1) return (int) $value;
        return null;
    }
}
