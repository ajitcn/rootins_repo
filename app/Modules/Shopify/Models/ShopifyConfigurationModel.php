<?php

namespace App\Modules\Shopify\Models;

use CodeIgniter\Model;

final class ShopifyConfigurationModel extends Model
{
    protected $table = 'shopify_configurations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'legacy_clinic_id', 'store_domain', 'admin_api_access_token_encrypted', 'client_id_encrypted', 'client_secret_encrypted', 'api_version',
        'webhook_secret_encrypted', 'app_url', 'encryption_key_encrypted',
        'auto_create_patients', 'status', 'created_by', 'updated_by', 'created_at', 'updated_at',
    ];
    protected $useTimestamps = false;
}
