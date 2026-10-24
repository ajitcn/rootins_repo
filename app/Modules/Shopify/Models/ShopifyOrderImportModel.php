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
        'rootins_order_id', 'status', 'failure_reason', 'error_message', 'financial_status', 'payment_status', 'paid_at', 'fulfillment_status',
        'currency', 'total_snapshot', 'shipping_address_snapshot', 'payload_hash', 'last_event_id',
        'retry_count', 'processed_at', 'created_at', 'updated_at',
        'prescription_reference', 'prescription_status',
        'shopify_customer_id', 'customer_name_snapshot', 'customer_email_snapshot', 'customer_mobile_snapshot',
        'patient_status', 'mapping_status', 'quarantine_reason', 'last_error', 'last_attempted_at',
    ];
    protected $useTimestamps = false;
}
