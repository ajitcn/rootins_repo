<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateShopifyPrescriptionCartPushes extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('shopify_prescription_cart_pushes')) return;

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'store_domain' => ['type' => 'VARCHAR', 'constraint' => 190],
            'prescription_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'prescription_reference' => ['type' => 'VARCHAR', 'constraint' => 191],
            'patient_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'shopify_customer_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'variant_ids_json' => ['type' => 'TEXT'],
            'cart_pushed_at' => ['type' => 'DATETIME'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'cart_pushed'],
            'shopify_order_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'last_event_topic' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'last_event_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'ordered_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['store_domain', 'prescription_id', 'shopify_customer_id'], 'shopify_prescription_cart_push_identity');
        $this->forge->addKey(['store_domain', 'prescription_reference'], false, 'shopify_prescription_cart_push_reference');
        $this->forge->addForeignKey('prescription_id', 'prescriptions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('patient_id', 'patients', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('last_event_id', 'shopify_webhook_events', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('shopify_prescription_cart_pushes', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('shopify_prescription_cart_pushes', true);
    }
}
