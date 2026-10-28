<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddPrescriptionCartSyncStatus extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('prescriptions')) {
            $fields = [];
            if (! $this->db->fieldExists('cart_sync_status', 'prescriptions')) $fields['cart_sync_status'] = ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'not_synced'];
            if (! $this->db->fieldExists('cart_pushed_at', 'prescriptions')) $fields['cart_pushed_at'] = ['type' => 'DATETIME', 'null' => true];
            if (! $this->db->fieldExists('cart_sync_failure_reason', 'prescriptions')) $fields['cart_sync_failure_reason'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true];
            if ($fields !== []) $this->forge->addColumn('prescriptions', $fields);
        }

        if ($this->db->tableExists('shopify_prescription_cart_pushes')) {
            $fields = [];
            if (! $this->db->fieldExists('cart_sync_status', 'shopify_prescription_cart_pushes')) $fields['cart_sync_status'] = ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'not_synced'];
            if (! $this->db->fieldExists('cart_sync_failure_reason', 'shopify_prescription_cart_pushes')) $fields['cart_sync_failure_reason'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true];
            if ($fields !== []) $this->forge->addColumn('shopify_prescription_cart_pushes', $fields);
            try {
                $this->forge->modifyColumn('shopify_prescription_cart_pushes', ['cart_pushed_at' => ['type' => 'DATETIME', 'null' => true]]);
            } catch (\Throwable) {
                // Existing installations may already have a compatible nullable column.
            }
            $this->db->query("UPDATE shopify_prescription_cart_pushes SET cart_sync_status = CASE status WHEN 'cart_pushed' THEN 'synced' WHEN 'ordered' THEN 'ordered' WHEN 'paid' THEN 'paid' WHEN 'cancelled' THEN 'cancelled' WHEN 'expired' THEN 'expired' ELSE 'not_synced' END WHERE cart_sync_status IS NULL OR cart_sync_status = 'not_synced'");
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('prescriptions')) {
            foreach (['cart_sync_status', 'cart_pushed_at', 'cart_sync_failure_reason'] as $field) if ($this->db->fieldExists($field, 'prescriptions')) $this->forge->dropColumn('prescriptions', $field);
        }
        if ($this->db->tableExists('shopify_prescription_cart_pushes')) {
            foreach (['cart_sync_status', 'cart_sync_failure_reason'] as $field) if ($this->db->fieldExists($field, 'shopify_prescription_cart_pushes')) $this->forge->dropColumn('shopify_prescription_cart_pushes', $field);
        }
    }
}
