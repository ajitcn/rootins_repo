<?php

namespace App\Services\Shopify;

use App\Modules\Shopify\Models\ShopifyOperationIdempotencyModel;

final class ShopifyOperationIdempotencyService
{
    public function __construct(private readonly ?ShopifyOperationIdempotencyModel $model = null)
    {
    }

    /**
     * Claim an operation key. A false result means the operation was already seen.
     */
    public function claim(string $operationKey, string $operationType): bool
    {
        $key = trim($operationKey);

        if ($key === '') {
            return false;
        }

        $model = $this->model ?? new ShopifyOperationIdempotencyModel();

        if ($model->where('operation_key', $key)->first() !== null) {
            return false;
        }

        try {
            return (bool) $model->insert([
                'operation_key' => $key,
                'operation_type' => trim($operationType),
                'status' => 'processing',
                'retry_count' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            return false;
        }
    }

    public function complete(string $operationKey, ?string $responseHash = null): bool
    {
        return $this->update($operationKey, [
            'status' => 'completed',
            'response_hash' => $responseHash,
            'completed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function fail(string $operationKey, string $error): bool
    {
        return $this->update($operationKey, [
            'status' => 'failed',
            'error_message' => substr(trim($error), 0, 1000),
            'retry_count' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function update(string $operationKey, array $data): bool
    {
        $model = $this->model ?? new ShopifyOperationIdempotencyModel();

        return (bool) $model->where('operation_key', trim($operationKey))->set($data)->update();
    }
}
