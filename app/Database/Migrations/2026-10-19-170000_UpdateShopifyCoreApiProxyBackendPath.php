<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class UpdateShopifyCoreApiProxyBackendPath extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('shopify_core_api_proxy_configurations')) {
            return;
        }

        $this->db->table('shopify_core_api_proxy_configurations')
            ->where('backend_url', 'https://rootins.newrootssoftware.in/public/shopify-proxy')
            ->set('backend_url', 'https://rootins.newrootssoftware.in/public/rootins-proxy')
            ->update();
    }

    public function down(): void
    {
        if (! $this->db->tableExists('shopify_core_api_proxy_configurations')) {
            return;
        }

        $this->db->table('shopify_core_api_proxy_configurations')
            ->where('backend_url', 'https://rootins.newrootssoftware.in/public/rootins-proxy')
            ->set('backend_url', 'https://rootins.newrootssoftware.in/public/shopify-proxy')
            ->update();
    }
}
