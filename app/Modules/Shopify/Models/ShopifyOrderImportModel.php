<?php

namespace App\Modules\Shopify\Models;

use CodeIgniter\Model;

final class ShopifyOrderImportModel extends Model
{
    protected $table = 'shopify_order_imports';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'store_domain', 'shopify_order_id', 'shopify_order_number', 'patient_id', 'clinic_id',
        'rootins_order_id', 'status', 'failure_reason', 'financial_status', 'fulfillment_status',
        'currency', 'total_snapshot', 'shipping_address_snapshot', 'payload_hash', 'last_event_id',
        'retry_count', 'processed_at', 'created_at', 'updated_at',
    ];
    protected $useTimestamps = false;
}
