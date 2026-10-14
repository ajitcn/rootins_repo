<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddOrderLinkToInvoices extends Migration
{
    public function up(): void
    {
        if (!$this->db->fieldExists('order_id', 'invoices')) {
            $this->forge->addColumn('invoices', ['order_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'id']]);
        }
        $uniqueIndexExists = (int) $this->db->query("SHOW INDEX FROM invoices WHERE Key_name = 'invoices_order_id_unique'")->getNumRows() > 0;

        if (!$uniqueIndexExists) {
            $this->db->query('ALTER TABLE invoices ADD UNIQUE KEY invoices_order_id_unique (order_id)');
        }

        try {
            $this->db->query('ALTER TABLE invoices ADD CONSTRAINT invoices_order_id_foreign FOREIGN KEY (order_id) REFERENCES medicine_orders(id) ON DELETE SET NULL ON UPDATE CASCADE');
        } catch (\Throwable) { /* Existing deployments may already have the constraint. */
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('order_id', 'invoices')) {
            try {
                $this->db->query('ALTER TABLE invoices DROP FOREIGN KEY invoices_order_id_foreign');
            } catch (\Throwable) {
            }

            try {
                $this->db->query('ALTER TABLE invoices DROP INDEX invoices_order_id_unique');
            } catch (\Throwable) {
            }
            $this->forge->dropColumn('invoices', 'order_id');
        }
    }
}
