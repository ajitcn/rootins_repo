<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateShopifyProxyRateLimits extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('shopify_proxy_rate_limits')) return;
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'configuration_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'window_start' => ['type' => 'DATETIME'],
            'attempts' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['configuration_id', 'window_start']);
        $this->forge->addKey(['window_start', 'configuration_id']);
        $this->forge->addForeignKey('configuration_id', 'shopify_core_api_proxy_configurations', 'id', 'CASCADE', 'CASCADE', 'shopify_proxy_rate_limit_configuration_fk');
        $this->forge->createTable('shopify_proxy_rate_limits', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('shopify_proxy_rate_limits', true);
    }
}
