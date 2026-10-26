<?php

namespace App\Services\Shopify;

use App\Modules\Shopify\Models\ShopifyOrderNotificationModel;
use App\Services\Notification\NotificationDispatcher;

final class ShopifyOrderNotificationOutboxService
{
    public function __construct(private readonly ?NotificationDispatcher $dispatcher = null) {}

    /** Processes independently committed jobs; order decisions are never rolled back by delivery. */
    public function process(int $limit = 50): array
    {
        $model = new ShopifyOrderNotificationModel();
        $rows = $model->whereIn('status', ['pending', 'failed'])->where('retry_count <', 8)->orderBy('id', 'ASC')->findAll(max(1, min(200, $limit)));
        $result = ['processed' => 0, 'sent' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            $result['processed']++;
            $payload = json_decode((string) ($row['safe_payload_json'] ?? '{}'), true);
            $payload = is_array($payload) ? $payload : [];
            $address = trim((string) ($payload['recipient'] ?? ''));
            if ($address === '') $address = trim((string) ($payload['email'] ?? ''));
            $dispatch = ($this->dispatcher ?? new NotificationDispatcher())->dispatch((string) $row['channel'], ['address' => $address], (string) $row['template_key'], $payload, 'shopify-outbox-' . (int) $row['id']);
            $sent = ($dispatch['status'] ?? '') === 'sent';
            $model->update((int) $row['id'], ['status' => $sent ? 'sent' : 'failed', 'sent_at' => $sent ? date('Y-m-d H:i:s') : null, 'retry_count' => (int) $row['retry_count'] + 1, 'last_error' => $sent ? null : substr((string) ($dispatch['failure_reason'] ?? 'Notification delivery failed.'), 0, 1000), 'updated_at' => date('Y-m-d H:i:s')]);
            if ($sent) $result['sent']++; else { $result['failed']++; log_message('warning', 'Shopify notification delivery failed ' . json_encode(['import_id' => (int) $row['import_id'], 'notification_type' => (string) $row['notification_type'], 'failure_category' => 'NOTIFICATION_DELIVERY_FAILED'], JSON_UNESCAPED_SLASHES)); }
        }
        return $result;
    }
}
