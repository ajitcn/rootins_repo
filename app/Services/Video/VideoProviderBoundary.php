<?php

namespace App\Services\Video;

use RuntimeException;

final class VideoProviderBoundary
{
    public function providerName(): string
    {
        return trim((string) env('VIDEO_PROVIDER', 'provider_boundary')) ?: 'provider_boundary';
    }

    public function isConfigured(): bool
    {
        return filter_var(env('VIDEO_PROVIDER_CONFIGURED', false), FILTER_VALIDATE_BOOLEAN);
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

        throw new RuntimeException('Video provider token adapter is not deployed.');
    }
}
