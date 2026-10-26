<?php

namespace App\Modules\Shopify\Models;

use CodeIgniter\Model;

final class ShopifyPrescriptionOrderLinkModel extends Model
{
    protected $table = 'shopify_prescription_order_links';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'store_domain', 'shopify_order_id', 'import_id', 'patient_id', 'clinic_id',
        'prescription_id', 'prescription_reference', 'bundle_reference', 'document_id',
        'upload_reference', 'status', 'created_at', 'updated_at',
    ];
}
