<?php

namespace App\Modules\CoreApi\Services;

use App\Modules\CoreApi\Models\CoreApiIdempotencyModel;

final class CoreApiIdempotencyService
{
    public function __construct(private readonly ?CoreApiIdempotencyModel $model = null)
    {
    }

    /** @return array{replay: bool, status: int, body: array<string, mixed>} */
    public function begin(int $credentialId, string $key, string $requestHash): array
    {
        $key = trim($key);

        if ($key === '' || mb_strlen($key) > 191) {
            throw new CoreApiException(422, 'IDEMPOTENCY_KEY_REQUIRED', 'A valid Idempotency-Key header is required.');
        }

        $model = $this->model ?? new CoreApiIdempotencyModel();
        $existing = $model->where('credential_id', $credentialId)->where('idempotency_key', $key)->first();

        // A worker can terminate after the business transaction commits but
        // before the replay response is persisted. Do not leave that claim
        // blocking the key forever; only reclaim an expired processing claim.
        if ($existing !== null
            && ($existing['status'] ?? '') === 'processing'
            && ! empty($existing['expires_at'])
            && (string) $existing['expires_at'] < date('Y-m-d H:i:s')) {
            $model->where('id', (int) $existing['id'])->where('status', 'processing')->delete();
            $existing = null;
        }

        if ($existing !== null) {
            if (! hash_equals((string) $existing['request_hash'], $requestHash)) {
                throw new CoreApiException(409, 'IDEMPOTENCY_KEY_REUSED', 'The idempotency key was used for a different request.');
            }

            if (($existing['status'] ?? '') === 'completed' && ! empty($existing['response_json'])) {
                return ['replay' => true, 'status' => (int) ($existing['response_status'] ?? 200), 'body' => json_decode((string) $existing['response_json'], true) ?: []];
            }

            throw new CoreApiException(409, 'REQUEST_IN_PROGRESS', 'The request is already being processed.');
        }

        try {
            $model->insert([
                'credential_id' => $credentialId,
                'idempotency_key' => $key,
                'request_hash' => $requestHash,
                'status' => 'processing',
                'expires_at' => date('Y-m-d H:i:s', time() + 86400),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            throw new CoreApiException(409, 'REQUEST_IN_PROGRESS', 'The request is already being processed.');
        }

        return ['replay' => false, 'status' => 0, 'body' => []];
    }

    /** @param array<string, mixed> $body */
    public function complete(int $credentialId, string $key, int $status, array $body): void
    {
        ($this->model ?? new CoreApiIdempotencyModel())
            ->where('credential_id', $credentialId)
            ->where('idempotency_key', trim($key))
            ->set([
                'status' => 'completed',
                'response_status' => $status,
                'response_json' => json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
                'updated_at' => date('Y-m-d H:i:s'),
            ])
            ->update();
    }

    /**
     * Release only a claim still owned by the current request.
     *
     * Completed responses are never deleted, so an exception after the
     * business transaction commits cannot turn a valid replay into a second
     * write.
     */
    public function release(int $credentialId, string $key): void
    {
        ($this->model ?? new CoreApiIdempotencyModel())
            ->where('credential_id', $credentialId)
            ->where('idempotency_key', trim($key))
            ->where('status', 'processing')
            ->delete();
    }
}
