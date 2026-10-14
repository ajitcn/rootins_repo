<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddDeliveryFieldsToMedicineOrders extends Migration
{
    public function up(): void
    {
        $fields = [
            'delivery_provider' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'tracking_reference' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'delivery_address_snapshot' => ['type' => 'TEXT', 'null' => true],
            'delivery_contact_snapshot' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'dispatched_at' => ['type' => 'DATETIME', 'null' => true],
            'delivered_at' => ['type' => 'DATETIME', 'null' => true],
            'failure_reason' => ['type' => 'TEXT', 'null' => true],
            'return_reason' => ['type' => 'TEXT', 'null' => true],
        ];

        foreach ($fields as $name => $definition) {
            if (!$this->db->fieldExists($name, 'medicine_orders')) {
                $this->forge->addColumn('medicine_orders', [$name => $definition]);
            }
        }
        $this->db->query('ALTER TABLE medicine_orders ADD INDEX medicine_orders_delivery_provider (clinic_id, delivery_provider)');
        $this->db->query('ALTER TABLE medicine_orders ADD INDEX medicine_orders_tracking_reference (clinic_id, tracking_reference)');
    }

    public function down(): void
    {
        try {
            $this->db->query('ALTER TABLE medicine_orders DROP INDEX medicine_orders_delivery_provider');
        } catch (\Throwable) {
        }

        try {
            $this->db->query('ALTER TABLE medicine_orders DROP INDEX medicine_orders_tracking_reference');
        } catch (\Throwable) {
        }

        foreach (['delivery_provider', 'tracking_reference', 'delivery_address_snapshot', 'delivery_contact_snapshot', 'dispatched_at', 'delivered_at', 'failure_reason', 'return_reason'] as $field) {
            if ($this->db->fieldExists($field, 'medicine_orders')) {
                $this->forge->dropColumn('medicine_orders', $field);
            }
        }
    }
}
