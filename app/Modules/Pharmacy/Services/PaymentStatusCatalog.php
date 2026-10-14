<?php

namespace Pharmacy\Services;

final class PaymentStatusCatalog
{
    public function active(): array
    {
        try {
            return db_connect()->table('payment_statuses')
                ->select('id, status_name, status_code, description, sort_order, status')
                ->where('status', 1)
                ->where('archived_at', null)
                ->orderBy('sort_order', 'ASC')
                ->orderBy('status_name', 'ASC')
                ->get()
                ->getResultArray();
        } catch (\Throwable $exception) {
            log_message('error', 'Payment status catalog failed: ' . $exception->getMessage());

            return [];
        }
    }

    public function isActive(string $code): bool
    {
        $code = strtolower(trim($code));

        if ($code === '') {
            return false;
        }

        foreach ($this->active() as $status) {
            if (strtolower((string) ($status['status_code'] ?? '')) === $code) {
                return true;
            }
        }

        return false;
    }
}
