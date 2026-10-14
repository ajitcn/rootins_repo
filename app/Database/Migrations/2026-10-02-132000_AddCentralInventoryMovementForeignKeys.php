<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCentralInventoryMovementForeignKeys extends Migration
{
    public function up(): void
    {
        if ($this->db->DBDriver !== 'MySQLi') {
            return;
        }
        $constraints = [
            'inventory_movements_batch_fk' => 'ALTER TABLE inventory_stock_movements ADD CONSTRAINT inventory_movements_batch_fk FOREIGN KEY (inventory_batch_id) REFERENCES medicine_inventory_batches(id) ON DELETE CASCADE ON UPDATE RESTRICT',
            'inventory_movements_medicine_fk' => 'ALTER TABLE inventory_stock_movements ADD CONSTRAINT inventory_movements_medicine_fk FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE ON UPDATE RESTRICT',
            'inventory_movements_user_fk' => 'ALTER TABLE inventory_stock_movements ADD CONSTRAINT inventory_movements_user_fk FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE',
            'inventory_movements_clinic_fk' => 'ALTER TABLE inventory_stock_movements ADD CONSTRAINT inventory_movements_clinic_fk FOREIGN KEY (actor_clinic_id) REFERENCES clinics(id) ON DELETE SET NULL ON UPDATE CASCADE',
        ];

        foreach ($constraints as $name => $sql) {
            $exists = $this->db->query('SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = \'inventory_stock_movements\' AND CONSTRAINT_NAME = ?', [$name])->getRowArray();

            if ($exists === null) {
                $this->db->query($sql);
            }
        }
    }

    public function down(): void
    {
        if ($this->db->DBDriver !== 'MySQLi') {
            return;
        }

        foreach (['inventory_movements_batch_fk', 'inventory_movements_medicine_fk', 'inventory_movements_user_fk', 'inventory_movements_clinic_fk'] as $constraint) {
            $this->db->query('ALTER TABLE inventory_stock_movements DROP FOREIGN KEY ' . $constraint);
        }
    }
}
