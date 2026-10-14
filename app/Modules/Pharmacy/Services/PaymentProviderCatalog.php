<?php

namespace Pharmacy\Services;

final class PaymentProviderCatalog
{
    public function active(): array
    {
        try {
            return db_connect()->table('payment_providers')
                ->select('id, provider_name, provider_code, provider_type, default_currency, status')
                ->where('status', 1)->where('archived_at', null)
                ->orderBy('sort_order', 'ASC')->orderBy('provider_name', 'ASC')
                ->get()->getResultArray();
        } catch (\Throwable $exception) {
            log_message('error', 'Payment provider catalog failed: ' . $exception->getMessage());
            return [];
        }
    }

    public function findActive(string $code): ?array
    {
        $code = strtolower(trim($code));
        if ($code === '') return null;
        foreach ($this->active() as $provider) {
            if (strtolower((string) $provider['provider_code']) === $code) return $provider;
        }
        return null;
    }

    public function isActive(string $code): bool
    {
        return $this->findActive($code) !== null;
    }
}
