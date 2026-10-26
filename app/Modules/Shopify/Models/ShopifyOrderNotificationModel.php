<?php

namespace App\Modules\Shopify\Models;

use CodeIgniter\Model;

final class ShopifyOrderNotificationModel extends Model
{
    protected $table = 'shopify_order_notifications';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'store_domain', 'import_id', 'patient_id', 'shopify_customer_id', 'shopify_order_id',
        'notification_type', 'channel', 'template_key', 'safe_payload_json', 'status', 'sent_at',
        'retry_count', 'last_error', 'created_at', 'updated_at',
    ];
}
