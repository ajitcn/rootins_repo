<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateShopifyCoreApiProxyConfiguration extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('shopify_core_api_proxy_configurations')) return;
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'app_name' => ['type' => 'VARCHAR', 'constraint' => 120, 'default' => 'Shopify Core API App Proxy'],
            'store_domain' => ['type' => 'VARCHAR', 'constraint' => 255],
            'client_id_encrypted' => ['type' => 'TEXT'],
            'client_secret_encrypted' => ['type' => 'TEXT'],
            'proxy_prefix' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'apps'],
            'proxy_subpath' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'rootins'],
            'backend_url' => ['type' => 'VARCHAR', 'constraint' => 500, 'default' => 'https://rootins.newrootssoftware.in/public/shopify-proxy'],
            'enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => 0],
            'allowed_operations' => ['type' => 'TEXT'],
            'rate_limit_per_minute' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 60],
            'created_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'updated_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('store_domain');
        $this->forge->createTable('shopify_core_api_proxy_configurations', true);
    }

    public function down(): void { $this->forge->dropTable('shopify_core_api_proxy_configurations', true); }
}
