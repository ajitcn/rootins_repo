<?php

namespace App\Services\Notification;

final class DryRunNotificationProvider implements NotificationProviderInterface
{
    /**
     * This adapter deliberately never sends a message.
     *
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    public function send(array $message): array
    {
        return ['status' => 'deferred', 'retryable' => true, 'failure_reason' => 'Notification provider dispatch is disabled.'];
    }
}
