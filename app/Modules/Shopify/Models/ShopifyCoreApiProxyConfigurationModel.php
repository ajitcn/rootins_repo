<?php

namespace App\Modules\Shopify\Models;

use CodeIgniter\Model;

final class ShopifyCoreApiProxyConfigurationModel extends Model
{
    protected $table = 'shopify_core_api_proxy_configurations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['app_name', 'store_domain', 'client_id_encrypted', 'client_secret_encrypted', 'proxy_prefix', 'proxy_subpath', 'backend_url', 'enabled', 'allowed_operations', 'rate_limit_per_minute', 'created_by', 'updated_by', 'created_at', 'updated_at'];
    protected $useTimestamps = false;
}
