<?php

namespace App\Services\Video;

use RuntimeException;

final class VideoProviderBoundary
{
    public function providerName(): string
    {
        $runtime = (new VideoConfigurationService())->runtime();

        return trim((string) ($runtime['provider'] ?? 'provider_boundary')) ?: 'provider_boundary';
    }

    public function isConfigured(): bool
    {
        $runtime = (new VideoConfigurationService())->runtime();

        return $runtime['provider'] === 'twilio'
            ? preg_match('/^AC[a-f0-9]{32}$/i', (string) $runtime['account_sid']) === 1
                && preg_match('/^SK[a-f0-9]{32}$/i', (string) $runtime['api_key_sid']) === 1
                && strlen((string) $runtime['api_key_secret']) >= 16
            : filter_var(env('VIDEO_PROVIDER_CONFIGURED', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Provider credentials and SDK token generation stay server-side.
     * This boundary deliberately returns no secret or credential to callers.
     */
    public function issueShortLivedToken(array $session, string $identity): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Video provider is not configured.');
        }

        $runtime = (new VideoConfigurationService())->runtime();
        $issuedAt = time();
        $header = $this->base64Url(json_encode(['typ' => 'JWT', 'alg' => 'HS256', 'cty' => 'twilio-fpa;v=1'], JSON_THROW_ON_ERROR));
        $payload = $this->base64Url(json_encode([
            'jti' => (string) $runtime['api_key_sid'] . '-' . $issuedAt . '-' . bin2hex(random_bytes(8)),
            'iss' => (string) $runtime['api_key_sid'],
            'sub' => (string) $runtime['account_sid'],
            'iat' => $issuedAt,
            'exp' => $issuedAt + 3600,
            'grants' => [
                'identity' => $identity,
                'video' => ['room' => (string) ($session['room_reference'] ?? '')],
            ],
        ], JSON_THROW_ON_ERROR));
        $signingInput = $header . '.' . $payload;
        $signature = $this->base64Url(hash_hmac('sha256', $signingInput, (string) $runtime['api_key_secret'], true));

        return $signingInput . '.' . $signature;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
