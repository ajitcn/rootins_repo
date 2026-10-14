<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateDeliveryProviders extends Migration
{
    public function up(): void
    {
        if (!$this->db->tableExists('delivery_providers')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'provider_name' => ['type' => 'VARCHAR', 'constraint' => 120],
                'provider_code' => ['type' => 'VARCHAR', 'constraint' => 60],
                'tracking_url_template' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'support_contact' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'archived_at' => ['type' => 'DATETIME', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('provider_name');
            $this->forge->addUniqueKey('provider_code');
            $this->forge->createTable('delivery_providers');
        }

        foreach ([
            'delivery_provider_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'delivery_provider_name_snapshot' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
        ] as $field => $definition) {
            if (!$this->db->fieldExists($field, 'medicine_orders')) {
                $this->forge->addColumn('medicine_orders', [$field => $definition]);
            }
        }
        try { $this->db->query('ALTER TABLE medicine_orders ADD INDEX medicine_orders_delivery_provider_id (clinic_id, delivery_provider_id)'); } catch (\Throwable) {}
    }

    public function down(): void
    {
        try { $this->db->query('ALTER TABLE medicine_orders DROP INDEX medicine_orders_delivery_provider_id'); } catch (\Throwable) {}
        foreach (['delivery_provider_id', 'delivery_provider_name_snapshot'] as $field) {
            if ($this->db->fieldExists($field, 'medicine_orders')) $this->forge->dropColumn('medicine_orders', $field);
        }
        if ($this->db->tableExists('delivery_providers')) $this->forge->dropTable('delivery_providers');
    }
}
