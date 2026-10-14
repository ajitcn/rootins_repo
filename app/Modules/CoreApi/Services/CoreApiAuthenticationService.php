<?php

namespace App\Modules\CoreApi\Services;

use App\Modules\CoreApi\Models\CoreApiCredentialModel;
use CodeIgniter\HTTP\IncomingRequest;

final class CoreApiAuthenticationService
{
    public function __construct(private readonly ?CoreApiCredentialModel $credentials = null)
    {
    }

    /** @return array<string, mixed> */
    public function authenticate(IncomingRequest $request): array
    {
        $keyId = trim($request->getHeaderLine('X-API-Key-Id'));
        $timestamp = trim($request->getHeaderLine('X-API-Timestamp'));
        $signature = trim($request->getHeaderLine('X-API-Signature'));

        if ($keyId === '' || $timestamp === '' || $signature === '') {
            throw new CoreApiException(401, 'API_AUTH_REQUIRED', 'API authentication is required.');
        }

        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) {
            throw new CoreApiException(401, 'API_TIMESTAMP_INVALID', 'The API request timestamp is invalid or expired.');
        }

        $credential = ($this->credentials ?? new CoreApiCredentialModel())
            ->where('key_id', $keyId)
            ->where('status', 'active')
            ->first();

        if ($credential === null) {
            throw new CoreApiException(401, 'API_CREDENTIAL_INVALID', 'API authentication failed.');
        }

        try {
            $encodedSecret = base64_decode((string) $credential['secret_encrypted'], true);
            if ($encodedSecret === false) {
                throw new \RuntimeException('The API credential ciphertext is not encoded safely.');
            }
            $secret = service('encrypter')->decrypt($encodedSecret, config('Encryption')->key);
        } catch (\Throwable) {
            throw new CoreApiException(500, 'API_CREDENTIAL_UNAVAILABLE', 'The API credential is not configured safely.');
        }

        $canonical = $timestamp . "\n" . strtoupper($request->getMethod()) . "\n" . '/' . ltrim(uri_string(), '/') . "\n" . $request->getBody();
        $expected = base64_encode(hash_hmac('sha256', $canonical, $secret, true));

        if (! hash_equals($expected, $signature)) {
            throw new CoreApiException(401, 'API_SIGNATURE_INVALID', 'API authentication failed.');
        }

        $limit = max(1, (int) ($credential['rate_limit_per_minute'] ?? 60));

        try {
            $rate = (new CoreApiRateLimitService())->consume((int) $credential['id'], $limit);
        } catch (\Throwable) {
            throw new CoreApiException(503, 'API_RATE_LIMITER_UNAVAILABLE', 'The API request could not be admitted safely.');
        }

        if (! $rate['allowed']) {
            throw new CoreApiException(429, 'API_RATE_LIMITED', 'Too many API requests.', [], $rate['retry_after']);
        }

        (new CoreApiCredentialModel())->update((int) $credential['id'], ['last_used_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        unset($credential['secret_encrypted']);
        CoreApiContext::set($credential);

        service('audit')->record('core_api.authenticated', [
            'api_credential_id' => (int) $credential['id'],
            'method' => strtoupper($request->getMethod()),
        ]);

        return $credential;
    }
}
