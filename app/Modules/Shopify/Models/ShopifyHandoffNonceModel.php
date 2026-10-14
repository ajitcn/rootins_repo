<?php

namespace App\Modules\Shopify\Models;

use CodeIgniter\Model;

final class ShopifyHandoffNonceModel extends Model
{
    protected $table = 'shopify_handoff_nonces';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'nonce',
        'shopify_store_domain',
        'shopify_customer_id',
        'patient_id',
        'expires_at',
        'used_at',
        'created_at',
    ];
    protected $useTimestamps = false;
}
