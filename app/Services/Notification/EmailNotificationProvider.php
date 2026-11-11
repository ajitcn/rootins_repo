<?php

namespace App\Services\Notification;

final class EmailNotificationProvider implements NotificationProviderInterface
{
    public function __construct(private readonly ?EmailSettingsService $settings = null, private readonly ?int $clinicId = null) {}

    public function send(array $message): array
    {
        if (($message['channel'] ?? '') !== 'email') return ['status' => 'deferred', 'retryable' => true, 'failure_reason' => 'This provider only handles email.'];
        $runtime = ($this->settings ?? new EmailSettingsService())->runtime($this->clinicId);
        if (! $runtime['enabled']) return ['status' => 'deferred', 'retryable' => true, 'failure_reason' => 'Email delivery is disabled.'];
        $recipient = trim((string) ($message['recipient']['address'] ?? ''));
        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) return ['status' => 'failed', 'retryable' => false, 'failure_reason' => 'The email recipient is invalid.'];
        $email = service('email');
        $emailConfig = $runtime;
        unset($emailConfig['enabled']);
        $email->initialize($emailConfig);
        $email->setFrom($runtime['fromEmail'], $runtime['fromName']);
        $email->setTo($recipient);
        $template = trim((string) ($message['template'] ?? 'notification'));
        $email->setSubject((string) ($message['data']['subject'] ?? ('ROOTINS ' . ucwords(str_replace(['.', '_', '-'], ' ', $template)))));
        $body = (string) ($message['data']['message'] ?? 'You have a new ROOTINS notification. Please sign in to review the latest update.');
        $email->setMessage($body);
        if (! $email->send()) return ['status' => 'failed', 'retryable' => true, 'failure_reason' => 'The email provider could not send the message.'];
        return ['status' => 'sent', 'attempts' => 1, 'retryable' => false];
    }
}
