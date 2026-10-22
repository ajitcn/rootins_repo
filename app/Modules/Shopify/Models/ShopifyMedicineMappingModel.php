<?php

namespace App\Modules\Shopify\Models;

use CodeIgniter\Model;

final class ShopifyMedicineMappingModel extends Model
{
    protected $table = 'shopify_medicine_mappings';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'store_domain', 'shopify_product_id', 'shopify_variant_id', 'shopify_product_title', 'shopify_variant_title', 'shopify_sku', 'medicine_id', 'status', 'created_at', 'updated_at',
    ];
    protected $useTimestamps = false;
}
