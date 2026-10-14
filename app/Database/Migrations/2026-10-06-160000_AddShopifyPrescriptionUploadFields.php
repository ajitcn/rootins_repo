<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds Shopify provenance and review metadata to the existing private document
 * table without moving or exposing existing patient files.
 */
final class AddShopifyPrescriptionUploadFields extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('patient_documents')) {
            return;
        }

        $columns = [
            'clinic_id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true,
                'after' => 'patient_id',
            ],
            'source_type' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'default' => 'patient',
                'after' => 'document_type',
            ],
            'shopify_store_domain' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'source_type',
            ],
            'shopify_customer_id' => [
                'type' => 'VARCHAR',
                'constraint' => 191,
                'null' => true,
                'after' => 'shopify_store_domain',
            ],
            'external_upload_reference' => [
                'type' => 'VARCHAR',
                'constraint' => 191,
                'null' => true,
                'after' => 'shopify_customer_id',
            ],
            'upload_status' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
                'default' => 'uploaded',
                'after' => 'external_upload_reference',
            ],
            'retention_expires_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'upload_status',
            ],
            'uploaded_by_type' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'null' => true,
                'after' => 'retention_expires_at',
            ],
            'uploaded_by_id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true,
                'after' => 'uploaded_by_type',
            ],
        ];

        foreach ($columns as $name => $definition) {
            if (! $this->db->fieldExists($name, 'patient_documents')) {
                $this->forge->addColumn('patient_documents', [$name => $definition]);
            }
        }

        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query('UPDATE patient_documents AS documents INNER JOIN patients ON patients.id = documents.patient_id SET documents.clinic_id = patients.clinic_id WHERE documents.clinic_id IS NULL');
        }

        $this->addIndexIfMissing('patient_documents', 'patient_documents_clinic_status_idx', ['clinic_id', 'upload_status', 'created_at']);
        $this->addIndexIfMissing('patient_documents', 'patient_documents_source_idx', ['source_type', 'shopify_store_domain', 'external_upload_reference']);
        $this->addUniqueIndexIfMissing('patient_documents', 'patient_documents_shopify_reference_unique', ['source_type', 'shopify_store_domain', 'external_upload_reference']);

        if ($this->db->fieldExists('clinic_id', 'patient_documents') && ! $this->foreignKeyExists('patient_documents', 'patient_documents_clinic_fk')) {
            $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'SET NULL', 'CASCADE', 'patient_documents_clinic_fk');
            $this->forge->processIndexes('patient_documents');
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('patient_documents')) {
            return;
        }

        $this->dropForeignKeyIfPresent('patient_documents', 'patient_documents_clinic_fk');
        $this->dropIndexIfPresent('patient_documents', 'patient_documents_shopify_reference_unique');
        $this->dropIndexIfPresent('patient_documents', 'patient_documents_source_idx');
        $this->dropIndexIfPresent('patient_documents', 'patient_documents_clinic_status_idx');

        $columns = [
            'clinic_id',
            'source_type',
            'shopify_store_domain',
            'shopify_customer_id',
            'external_upload_reference',
            'upload_status',
            'retention_expires_at',
            'uploaded_by_type',
            'uploaded_by_id',
        ];

        foreach ($columns as $column) {
            if ($this->db->fieldExists($column, 'patient_documents')) {
                $this->forge->dropColumn('patient_documents', $column);
            }
        }
    }

    private function addIndexIfMissing(string $table, string $name, array $columns): void
    {
        if ($this->db->DBDriver !== 'MySQLi') {
            $this->forge->addKey($columns, false, $name);
            $this->forge->processIndexes($table);

            return;
        }

        if (! $this->indexExists($table, $name)) {
            $this->db->query('ALTER TABLE ' . $table . ' ADD INDEX ' . $name . ' (' . implode(', ', $columns) . ')');
        }
    }

    private function addUniqueIndexIfMissing(string $table, string $name, array $columns): void
    {
        if ($this->db->DBDriver !== 'MySQLi') {
            $this->forge->addKey($columns, true, $name);
            $this->forge->processIndexes($table);

            return;
        }

        if (! $this->indexExists($table, $name)) {
            $this->db->query('ALTER TABLE ' . $table . ' ADD UNIQUE INDEX ' . $name . ' (' . implode(', ', $columns) . ')');
        }
    }

    private function dropIndexIfPresent(string $table, string $name): void
    {
        if ($this->indexExists($table, $name)) {
            $this->db->query('ALTER TABLE ' . $table . ' DROP INDEX ' . $name);
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        if ($this->db->DBDriver !== 'MySQLi') {
            return false;
        }

        return (bool) $this->db->query('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?', [$name])->getRowArray();
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        if ($this->db->DBDriver !== 'MySQLi') {
            return false;
        }

        return (bool) $this->db->query('SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?', [$table, $name])->getRowArray();
    }

    private function dropForeignKeyIfPresent(string $table, string $name): void
    {
        if ($this->foreignKeyExists($table, $name)) {
            $this->db->query('ALTER TABLE ' . $table . ' DROP FOREIGN KEY ' . $name);
        }
    }
}
