<?php

namespace App\Modules\Shopify\Models;

use CodeIgniter\Model;

final class ShopifyMappingIgnoreModel extends Model
{
    protected $table = 'shopify_mapping_ignores';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['store_domain', 'shopify_product_id', 'shopify_variant_id', 'reason', 'created_by', 'created_at', 'updated_at'];
    protected $useTimestamps = false;
}
