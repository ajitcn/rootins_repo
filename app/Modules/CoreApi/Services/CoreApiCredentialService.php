<?php

namespace App\Modules\CoreApi\Services;

use App\Modules\CoreApi\Models\CoreApiCredentialModel;

final class CoreApiCredentialService
{
    public function __construct(private readonly ?CoreApiCredentialModel $model = null)
    {
    }

    /**
     * Provisioning is intentionally a server-side operation. The secret is
     * encrypted at rest and is never returned to an external client.
     *
     * @return array{key_id: string, secret: string}
     */
    public function create(string $keyId, string $secret, int $rateLimit = 60): array
    {
        $keyId = trim($keyId);

        if ($keyId === '' || $secret === '') {
            throw new CoreApiException(422, 'CREDENTIAL_INVALID', 'A valid API credential is required.');
        }

        ($this->model ?? new CoreApiCredentialModel())->insert([
            'key_id' => $keyId,
            // Encryption output is binary with the current CI4 configuration;
            // encode it before persisting to the TEXT column so the database
            // connection cannot transform or truncate the ciphertext.
            'secret_encrypted' => base64_encode(service('encrypter')->encrypt($secret, config('Encryption')->key)),
            'status' => 'active',
            'rate_limit_per_minute' => max(1, $rateLimit),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['key_id' => $keyId, 'secret' => $secret];
    }

    /** @return array{key_id: string, secret: string} */
    public function createGenerated(string $keyId, int $rateLimit = 60): array
    {
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        return $this->create($keyId, $secret, $rateLimit);
    }

    /** @return array{key_id: string, secret: string} */
    public function rotate(int $credentialId, int $rateLimit = 60): array
    {
        $model = $this->model ?? new CoreApiCredentialModel();
        $credential = $model->where('id', $credentialId)->first();

        if ($credential === null) {
            throw new CoreApiException(404, 'CREDENTIAL_NOT_FOUND', 'The API credential was not found.');
        }

        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $model->update($credentialId, [
            'secret_encrypted' => base64_encode(service('encrypter')->encrypt($secret, config('Encryption')->key)),
            'status' => 'active',
            'rate_limit_per_minute' => max(1, $rateLimit),
            'last_used_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['key_id' => (string) $credential['key_id'], 'secret' => $secret];
    }

    /** @return array{http_status: int, api_status: bool, error_code: string} */
    public function testConnection(int $credentialId): array
    {
        $model = $this->model ?? new CoreApiCredentialModel();
        $credential = $model->where('id', $credentialId)->where('status', 'active')->first();

        if ($credential === null) {
            throw new CoreApiException(404, 'CREDENTIAL_NOT_FOUND', 'The active API credential was not found.');
        }

        $encodedSecret = base64_decode((string) $credential['secret_encrypted'], true);
        if ($encodedSecret === false) {
            throw new CoreApiException(500, 'CREDENTIAL_UNAVAILABLE', 'The API credential is not configured safely.');
        }
        $secret = service('encrypter')->decrypt($encodedSecret, config('Encryption')->key);
        $timestamp = (string) time();
        $path = '/api/v1/appointments/availability';
        $canonical = $timestamp . "\nGET\n" . $path . "\n";
        $signature = base64_encode(hash_hmac('sha256', $canonical, $secret, true));

        try {
            $response = service('curlrequest')->get(rtrim(base_url(), '/') . $path, [
                'http_errors' => false,
                'timeout' => 10,
                'headers' => [
                    'X-API-Key-Id' => (string) $credential['key_id'],
                    'X-API-Timestamp' => $timestamp,
                    'X-API-Signature' => $signature,
                ],
            ]);
            $body = json_decode((string) $response->getBody(), true) ?: [];

            return ['http_status' => $response->getStatusCode(), 'api_status' => (bool) ($body['status'] ?? false), 'error_code' => (string) ($body['error']['code'] ?? '')];
        } catch (\Throwable) {
            throw new CoreApiException(502, 'CONNECTION_FAILED', 'The signed API connection could not be verified safely.');
        }
    }
}
