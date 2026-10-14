<?php

namespace App\Modules\Shopify\Models;

use CodeIgniter\Model;

final class ShopifyWebhookEventModel extends Model
{
    protected $table = 'shopify_webhook_events';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'store_domain',
        'external_event_id',
        'topic',
        'payload_hash',
        'payload_json',
        'status',
        'processed_at',
        'error_message',
        'retry_count',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = false;
}
