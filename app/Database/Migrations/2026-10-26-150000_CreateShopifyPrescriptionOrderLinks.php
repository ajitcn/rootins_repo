<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateShopifyPrescriptionOrderLinks extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('shopify_prescription_order_links')) return;

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'store_domain' => ['type' => 'VARCHAR', 'constraint' => 255],
            'shopify_order_id' => ['type' => 'VARCHAR', 'constraint' => 100],
            'import_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'patient_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'prescription_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'prescription_reference' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'bundle_reference' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'document_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'upload_reference' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'linked'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['store_domain', 'shopify_order_id', 'import_id'], 'shopify_prescription_order_link_identity');
        $this->forge->addKey(['import_id', 'document_id'], false, 'shopify_prescription_order_link_document');
        $this->forge->addForeignKey('import_id', 'shopify_order_imports', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('shopify_prescription_order_links', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('shopify_prescription_order_links', true);
    }
}
