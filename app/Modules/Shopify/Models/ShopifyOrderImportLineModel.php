<?php

namespace App\Modules\Shopify\Models;

use CodeIgniter\Model;

final class ShopifyOrderImportLineModel extends Model
{
    protected $table = 'shopify_order_import_lines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'import_id', 'store_domain', 'shopify_order_id', 'shopify_line_item_id', 'shopify_product_id', 'shopify_variant_id', 'medicine_id',
        'title_snapshot', 'quantity', 'shopify_sku', 'shopify_barcode', 'product_image_url', 'shopify_price_snapshot', 'rootins_order_item_id', 'status',
        'mapping_reason',
        'review_status', 'review_reason', 'substitute_medicine_id', 'substitute_note', 'approved_quantity', 'reviewed_by', 'reviewed_at',
        'created_at', 'updated_at',
    ];
    protected $useTimestamps = false;
}
