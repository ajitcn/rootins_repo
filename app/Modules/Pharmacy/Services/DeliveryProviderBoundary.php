<?php

namespace Pharmacy\Services;

final class DeliveryProviderBoundary
{
    public function isConfigured(string $provider): bool
    {
        $provider = strtolower(trim($provider));

        if ($provider === '' || $provider === 'manual') {
            return false;
        }
        $prefix = strtoupper($provider);

        return (string) env('DELIVERY_' . $prefix . '_API_KEY', '') !== '' && (string) env('DELIVERY_' . $prefix . '_API_SECRET', '') !== '';
    }

    public function readiness(string $provider): array
    {
        return ['provider' => strtolower(trim($provider)), 'configured' => $this->isConfigured($provider), 'message' => $this->isConfigured($provider) ? 'Provider boundary is configured.' : 'Delivery provider is not configured; no external delivery call was made.'];
    }

    /**
     * External delivery adapters are optional. Master data alone must never
     * turn a manual delivery update into a blocked provider integration.
     */
    public function hasAdapter(string $provider): bool
    {
        return false;
    }
}
