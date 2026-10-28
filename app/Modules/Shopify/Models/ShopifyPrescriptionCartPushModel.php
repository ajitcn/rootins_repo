<?php

namespace App\Modules\Shopify\Models;

use CodeIgniter\Model;

final class ShopifyPrescriptionCartPushModel extends Model
{
    protected $table = 'shopify_prescription_cart_pushes';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'store_domain', 'prescription_id', 'prescription_reference', 'patient_id',
        'shopify_customer_id', 'variant_ids_json', 'cart_pushed_at', 'status',
        'shopify_order_id', 'last_event_topic', 'last_event_id', 'ordered_at',
        'created_at', 'updated_at',
    ];
    protected $useTimestamps = false;
}
