<?php

namespace Pharmacy\Services;

final class PaymentProviderBoundary
{
    public function isConfigured(string $provider): bool
    {
        $provider = strtolower(trim($provider));

        if ($provider === 'manual') {
            return false;
        }
        $prefix = strtoupper($provider);

        return (string) env($prefix . '_KEY_ID', '') !== '' && (string) env($prefix . '_KEY_SECRET', '') !== '';
    }

    public function readiness(string $provider): array
    {
        return ['provider' => strtolower(trim($provider)), 'configured' => $this->isConfigured($provider), 'message' => $this->isConfigured($provider) ? 'Provider adapter is configured.' : 'Provider adapter is not configured; no external payment call was made.'];
    }
}
