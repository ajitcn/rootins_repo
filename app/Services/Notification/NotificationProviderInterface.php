<?php

namespace App\Services\Notification;

interface NotificationProviderInterface
{
    /**
     * @param array<string, mixed> $message
     *
     * @return array<string, mixed>
     */
    public function send(array $message): array;
}
