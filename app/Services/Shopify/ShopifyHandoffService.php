<?php

namespace App\Services\Shopify;

use App\Modules\Shopify\Models\ShopifyHandoffNonceModel;
use App\Modules\Shopify\Models\ShopifyPatientIdentityLinkModel;
use InvalidArgumentException;

final class ShopifyHandoffService
{
    public function __construct(
        private readonly ?ShopifyHandoffNonceModel $nonces = null,
        private readonly ?ShopifyConfiguration $configuration = null,
        private readonly ?ShopifyPatientIdentityLinkModel $links = null,
    ) {
    }

    public function issue(string $storeDomain, string $customerId, int $patientId, int $ttl = 120): string
    {
        $configuration = $this->configuration ?? new ShopifyConfiguration();
        $storeDomain = strtolower(trim($storeDomain));

        if ($configuration->encryptionKey() === '' || $storeDomain !== strtolower($configuration->storeDomain()) || $customerId === '' || $patientId < 1) {
            throw new InvalidArgumentException('Shopify handoff cannot be issued safely.');
        }

        $link = ($this->links ?? new ShopifyPatientIdentityLinkModel())
            ->where('shopify_store_domain', $storeDomain)
            ->where('shopify_customer_id', $customerId)
            ->where('patient_id', $patientId)
            ->where('link_status', 'linked')
            ->first();

        if ($link === null) {
            throw new InvalidArgumentException('Shopify handoff cannot be issued for an unlinked customer.');
        }

        $now = time();
        $payload = [
            'aud' => 'rootins-patient-session',
            'store' => $storeDomain,
            'customer' => $customerId,
            'patient' => $patientId,
            'exp' => $now + min(max($ttl, 30), 300),
            'nonce' => bin2hex(random_bytes(24)),
        ];
        $encodedPayload = $this->encode($payload);
        $signature = $this->sign($encodedPayload, $configuration->encryptionKey());
        $model = $this->nonces ?? new ShopifyHandoffNonceModel();
        $model->insert([
            'nonce' => $payload['nonce'],
            'shopify_store_domain' => $storeDomain,
            'shopify_customer_id' => $customerId,
            'patient_id' => $patientId,
            'expires_at' => date('Y-m-d H:i:s', $payload['exp']),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $encodedPayload . '.' . $signature;
    }

    /**
     * @return array{patient_id: int, store_domain: string, customer_id: string}
     */
    public function consume(string $token): array
    {
        $configuration = $this->configuration ?? new ShopifyConfiguration();
        $payload = $this->verify($token);
        $model = $this->nonces ?? new ShopifyHandoffNonceModel();
        $nonce = $model
            ->where('nonce', $payload['nonce'])
            ->where('shopify_store_domain', strtolower($configuration->storeDomain()))
            ->where('shopify_customer_id', $payload['customer'])
            ->where('patient_id', (int) $payload['patient'])
            ->where('used_at IS NULL', null, false)
            ->where('expires_at >=', date('Y-m-d H:i:s'))
            ->first();

        if ($nonce === null || ! $model->where('id', (int) $nonce['id'])->where('used_at IS NULL', null, false)->set(['used_at' => date('Y-m-d H:i:s')])->update()) {
            throw new InvalidArgumentException('Shopify handoff token has expired or was already used.');
        }

        return $payload;
    }

    /**
     * Verify a handoff without consuming it.
     *
     * Availability reads may be requested before the booking operation. The
     * booking operation still uses consume(), which atomically marks the nonce
     * as used after the idempotency check.
     *
     * @return array{patient_id: int, store_domain: string, customer_id: string}
     */
    public function verify(string $token): array
    {
        $configuration = $this->configuration ?? new ShopifyConfiguration();
        $parts = explode('.', trim($token));

        if (count($parts) !== 2 || $configuration->encryptionKey() === '') {
            throw new InvalidArgumentException('Shopify handoff token is invalid.');
        }

        [$encodedPayload, $signature] = $parts;

        if (! hash_equals($this->sign($encodedPayload, $configuration->encryptionKey()), $signature)) {
            throw new InvalidArgumentException('Shopify handoff token is invalid.');
        }

        $payload = json_decode($this->decode($encodedPayload), true);

        if (! is_array($payload)
            || ($payload['aud'] ?? '') !== 'rootins-patient-session'
            || ($payload['store'] ?? '') !== strtolower($configuration->storeDomain())
            || ! ctype_digit((string) ($payload['patient'] ?? ''))
            || (int) ($payload['patient'] ?? 0) < 1
            || ! ctype_digit((string) ($payload['exp'] ?? ''))
            || (int) $payload['exp'] < time()
            || ! preg_match('/^[a-f0-9]{48}$/', (string) ($payload['nonce'] ?? ''))
            || trim((string) ($payload['customer'] ?? '')) === '') {
            throw new InvalidArgumentException('Shopify handoff token is invalid or expired.');
        }

        $model = $this->nonces ?? new ShopifyHandoffNonceModel();
        $nonce = $model
            ->where('nonce', $payload['nonce'])
            ->where('shopify_store_domain', strtolower($configuration->storeDomain()))
            ->where('shopify_customer_id', $payload['customer'])
            ->where('patient_id', (int) $payload['patient'])
            ->where('used_at IS NULL', null, false)
            ->where('expires_at >=', date('Y-m-d H:i:s'))
            ->first();

        if ($nonce === null) {
            throw new InvalidArgumentException('Shopify handoff token has expired or was already used.');
        }

        return [
            'patient_id' => (int) $payload['patient'],
            'store_domain' => (string) $payload['store'],
            'customer_id' => (string) $payload['customer'],
        ];
    }

    private function sign(string $payload, string $key): string
    {
        return $this->encode(hash_hmac('sha256', $payload, $key, true));
    }

    /** @param array<string, mixed>|string $value */
    private function encode(array|string $value): string
    {
        $json = is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : $value;

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    private function decode(string $value): string
    {
        $padding = strlen($value) % 4;

        return (string) base64_decode(strtr($value . ($padding ? str_repeat('=', 4 - $padding) : ''), '-_', '+/'), true);
    }
}
