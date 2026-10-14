<?php

namespace App\Modules\Shopify\Models;

use CodeIgniter\Model;

final class ShopifyPatientIdentityLinkModel extends Model
{
    protected $table = 'shopify_patient_identity_links';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'shopify_store_domain',
        'shopify_customer_id',
        'patient_id',
        'email_snapshot',
        'mobile_snapshot',
        'link_status',
        'matched_by',
        'active_patient_key',
        'linked_at',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = false;
}
