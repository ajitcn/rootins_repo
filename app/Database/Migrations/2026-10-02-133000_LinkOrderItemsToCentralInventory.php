<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class LinkOrderItemsToCentralInventory extends Migration
{
    public function up(): void
    {
        if ($this->db->DBDriver !== 'MySQLi') {
            return;
        }
        $this->db->query('ALTER TABLE medicine_order_items ADD inventory_batch_id INT UNSIGNED NULL AFTER batch_number');
        $this->db->query('UPDATE medicine_order_items items JOIN medicine_inventory_batches batches ON batches.medicine_id = items.medicine_id AND batches.batch_number = items.batch_number SET items.inventory_batch_id = batches.id WHERE items.batch_number IS NOT NULL AND (SELECT COUNT(*) FROM medicine_inventory_batches matches WHERE matches.medicine_id = items.medicine_id AND matches.batch_number = items.batch_number) = 1');
        $this->db->query('ALTER TABLE medicine_order_items ADD KEY medicine_order_items_inventory_batch_idx (inventory_batch_id)');
        $this->db->query('ALTER TABLE medicine_order_items ADD CONSTRAINT medicine_order_items_inventory_batch_fk FOREIGN KEY (inventory_batch_id) REFERENCES medicine_inventory_batches(id) ON DELETE RESTRICT ON UPDATE RESTRICT');
    }

    public function down(): void
    {
        if ($this->db->DBDriver !== 'MySQLi') {
            return;
        }
        $this->db->query('ALTER TABLE medicine_order_items DROP FOREIGN KEY medicine_order_items_inventory_batch_fk');
        $this->db->query('ALTER TABLE medicine_order_items DROP INDEX medicine_order_items_inventory_batch_idx');
        $this->db->query('ALTER TABLE medicine_order_items DROP COLUMN inventory_batch_id');
    }
}
