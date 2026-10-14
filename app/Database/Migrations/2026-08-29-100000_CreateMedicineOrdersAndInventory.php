<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMedicineOrdersAndInventory extends Migration
{
    public function up()
    {
        $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'medicine_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'batch_number' => ['type' => 'VARCHAR', 'constraint' => 100], 'expiry_date' => ['type' => 'DATE'], 'quantity_available' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0], 'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'active'], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['clinic_id', 'medicine_id', 'batch_number']);
        $this->forge->addKey(['clinic_id', 'medicine_id', 'expiry_date']);
        $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('medicine_id', 'medicines', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('medicine_inventory_batches');
        $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'order_number' => ['type' => 'VARCHAR', 'constraint' => 40], 'prescription_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'patient_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'pending'], 'payment_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'pending'], 'delivery_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'pending'], 'idempotency_key' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true], 'payment_provider' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true], 'payment_reference' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true], 'last_reconciled_at' => ['type' => 'DATETIME', 'null' => true], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('order_number');
        $this->forge->addUniqueKey('idempotency_key');
        $this->forge->addKey(['clinic_id', 'status']);
        $this->forge->addKey(['clinic_id', 'payment_status']);
        $this->forge->addKey(['clinic_id', 'delivery_status']);
        $this->forge->addForeignKey('prescription_id', 'prescriptions', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('patient_id', 'patients', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('medicine_orders');
        $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'order_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'prescription_item_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'medicine_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'medicine_name' => ['type' => 'VARCHAR', 'constraint' => 255], 'batch_number' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true], 'dosage' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true], 'frequency' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true], 'duration' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true], 'duration_unit' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true], 'instructions' => ['type' => 'TEXT', 'null' => true], 'quantity' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'unit_price' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true], 'line_total' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('order_id');
        $this->forge->addKey('prescription_item_id');
        $this->forge->addForeignKey('order_id', 'medicine_orders', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('prescription_item_id', 'prescription_items', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('medicine_id', 'medicines', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('medicine_order_items');
    }

    public function down()
    {
        $this->forge->dropTable('medicine_order_items', true);
        $this->forge->dropTable('medicine_orders', true);
        $this->forge->dropTable('medicine_inventory_batches', true);
    }
}
