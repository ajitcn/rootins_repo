<?php

namespace App\Services\Shopify;

final class ShopifyWebhookVerifier
{
    public function __construct(private readonly ShopifyConfiguration $configuration)
    {
    }

    public function isValid(string $rawBody, string $signature): bool
    {
        $secret = $this->configuration->webhookSecret();

        if ($secret === '' || $signature === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

        return hash_equals($expected, trim($signature));
    }
}
