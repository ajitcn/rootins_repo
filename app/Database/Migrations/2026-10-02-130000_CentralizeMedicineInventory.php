<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Makes medicine_inventory_batches a single central stock pool.
 *
 * Existing duplicate clinic rows with the same medicine, batch and expiry are
 * merged once into the lowest id (quantities are summed exactly once); the
 * other rows remain as zero-quantity `merged` history rows. The legacy
 * clinic_id is retained nullable for reporting compatibility, but is not a
 * stock ownership key.
 */
final class CentralizeMedicineInventory extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'inventory_batch_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'medicine_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'batch_number' => ['type' => 'VARCHAR', 'constraint' => 100],
            'adjustment_quantity' => ['type' => 'INT', 'constraint' => 10],
            'previous_quantity' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'new_quantity' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'reason' => ['type' => 'TEXT'],
            'actor_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'actor_clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('inventory_batch_id');
        $this->forge->addKey(['medicine_id', 'batch_number']);
        $this->forge->addForeignKey('inventory_batch_id', 'medicine_inventory_batches', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('medicine_id', 'medicines', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('actor_user_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('actor_clinic_id', 'clinics', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('inventory_stock_movements', true);

        if ($this->db->DBDriver !== 'MySQLi') {
            return;
        }
        $this->db->query('ALTER TABLE medicine_inventory_batches MODIFY clinic_id INT UNSIGNED NULL');
        $groups = $this->db->query('SELECT medicine_id, batch_number, expiry_date, MIN(id) AS keep_id, SUM(quantity_available) AS total_quantity FROM medicine_inventory_batches GROUP BY medicine_id, batch_number, expiry_date HAVING COUNT(*) > 1')->getResultArray();

        foreach ($groups as $group) {
            $this->db->table('medicine_inventory_batches')->where('id', (int) $group['keep_id'])->update(['quantity_available' => (int) $group['total_quantity'], 'clinic_id' => null, 'status' => 'active']);
            $this->db->table('medicine_inventory_batches')->where('medicine_id', (int) $group['medicine_id'])->where('batch_number', $group['batch_number'])->where('expiry_date', $group['expiry_date'])->where('id !=', (int) $group['keep_id'])->update(['quantity_available' => 0, 'clinic_id' => null, 'status' => 'merged']);
        }
        $indexes = $this->db->query('SHOW INDEX FROM medicine_inventory_batches')->getResultArray();
        $grouped = [];

        foreach ($indexes as $index) {
            if ((int) $index['Non_unique'] === 0 && $index['Key_name'] !== 'PRIMARY') {
                $grouped[$index['Key_name']][] = $index['Column_name'];
            }
        }

        foreach ($grouped as $name => $columns) {
            if (count($columns) === 3 && in_array('clinic_id', $columns, true) && in_array('medicine_id', $columns, true) && in_array('batch_number', $columns, true)) {
                $this->db->query('ALTER TABLE medicine_inventory_batches DROP INDEX `' . str_replace('`', '', $name) . '`');
            }
        }
        $this->db->query('ALTER TABLE medicine_inventory_batches ADD UNIQUE KEY central_medicine_batch_expiry (medicine_id, batch_number, expiry_date)');
    }

    public function down(): void
    {
        $this->forge->dropTable('inventory_stock_movements', true);

        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query('ALTER TABLE medicine_inventory_batches DROP INDEX central_medicine_batch_expiry');
            $this->db->query('ALTER TABLE medicine_inventory_batches ADD UNIQUE KEY legacy_clinic_medicine_batch (clinic_id, medicine_id, batch_number)');
            $this->db->query('ALTER TABLE medicine_inventory_batches MODIFY clinic_id INT UNSIGNED NOT NULL');
        }
    }
}
