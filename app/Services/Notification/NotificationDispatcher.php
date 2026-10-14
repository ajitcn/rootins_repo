<?php

namespace App\Services\Notification;

use Config\Notifications;

class NotificationDispatcher
{
    private const CHANNELS = ['sms', 'whatsapp', 'email'];

    public function __construct(private readonly ?Notifications $config = null, private readonly ?NotificationProviderInterface $provider = null)
    {
    }

    /**
     * Queue a provider-independent notification request.
     *
     * No message is sent while Notifications::$enabled is false. The returned
     * metadata can be persisted by a future notification delivery store.
     *
     * @param array<string, mixed> $recipient
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function dispatch(string $channel, array $recipient, string $template, array $data = [], ?string $idempotencyKey = null): array
    {
        $channel = strtolower(trim($channel));
        $template = trim($template);
        $config = $this->config ?? new Notifications();
        $errors = $this->validate($channel, $recipient, $template);

        if ($errors !== []) {
            return ['status' => false, 'msg' => 'Notification request is invalid.', 'errors' => $errors];
        }
        $address = trim((string) ($recipient['address'] ?? ''));
        $providerName = (string) ($config->providers[$channel] ?? 'unconfigured');
        $requestKey = $idempotencyKey !== null && trim($idempotencyKey) !== '' ? trim($idempotencyKey) : hash('sha256', $channel . '|' . $address . '|' . $template . '|' . json_encode($data));
        $notificationId = hash('sha256', $providerName . '|' . $requestKey);
        $nextRetryAt = date('Y-m-d H:i:s', time() + max(1, $config->retryDelaySeconds));
        $result = ['status' => 'deferred', 'notification_id' => $notificationId, 'channel' => $channel, 'provider' => $providerName, 'attempts' => 0, 'max_attempts' => max(1, $config->maxAttempts), 'retryable' => true, 'next_retry_at' => $nextRetryAt];

        if (!$config->enabled || $this->provider === null) {
            $result['failure_reason'] = !$config->enabled ? 'Notification dispatch is disabled until provider and consent review is complete.' : 'No notification provider adapter is configured.';
            $this->logDispatch($result, false);

            return $result;
        }
        $providerResult = $this->provider->send(['channel' => $channel, 'recipient' => $recipient, 'template' => $template, 'data' => $data]);
        $result = array_merge($result, $providerResult);
        $this->logDispatch($result, ($result['status'] ?? '') === 'sent');

        return $result;
    }

    /**
     * @param array<string, mixed> $recipient
     *
     * @return array<string, string>
     */
    private function validate(string $channel, array $recipient, string $template): array
    {
        $errors = [];

        if (!in_array($channel, self::CHANNELS, true)) {
            $errors['channel'] = 'Notification channel is invalid.';
        }

        if (trim((string) ($recipient['address'] ?? '')) === '') {
            $errors['recipient'] = 'Notification recipient is required.';
        }

        if ($template === '' || !preg_match('/^[a-z0-9_.-]+$/i', $template)) {
            $errors['template'] = 'Notification template is invalid.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function logDispatch(array $result, bool $sent): void
    {
        log_message('info', sprintf('Notification dispatch: notification_id=%s channel=%s provider=%s status=%s attempts=%d sent=%s', $result['notification_id'] ?? 'unknown', $result['channel'] ?? 'unknown', $result['provider'] ?? 'unknown', $result['status'] ?? 'unknown', (int) ($result['attempts'] ?? 0), $sent ? 'true' : 'false'));
    }
}
