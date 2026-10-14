<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class MakeShopifyConfigurationApplicationWide extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('shopify_configurations')) {
            return;
        }
        if (! $this->db->fieldExists('legacy_clinic_id', 'shopify_configurations')) {
            $this->forge->addColumn('shopify_configurations', ['legacy_clinic_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'id']]);
        }
        if ($this->db->fieldExists('clinic_id', 'shopify_configurations')) {
            $this->db->query('UPDATE shopify_configurations SET legacy_clinic_id = clinic_id WHERE legacy_clinic_id IS NULL');
            foreach ($this->foreignKeys('shopify_configurations', 'clinic_id') as $name) {
                $this->db->query('ALTER TABLE shopify_configurations DROP FOREIGN KEY `' . $name . '`');
            }
            foreach ($this->indexes('shopify_configurations', 'clinic_id') as $name) {
                $this->db->query('ALTER TABLE shopify_configurations DROP INDEX `' . $name . '`');
            }
            $this->forge->dropColumn('shopify_configurations', 'clinic_id');
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('shopify_configurations') || ! $this->db->fieldExists('legacy_clinic_id', 'shopify_configurations')) {
            return;
        }
        if (! $this->db->fieldExists('clinic_id', 'shopify_configurations')) {
            $this->forge->addColumn('shopify_configurations', ['clinic_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'id']]);
            $this->db->query('UPDATE shopify_configurations SET clinic_id = legacy_clinic_id WHERE legacy_clinic_id IS NOT NULL');
            $this->forge->addUniqueKey('clinic_id');
            $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'RESTRICT', 'CASCADE');
        }
        $this->forge->dropColumn('shopify_configurations', 'legacy_clinic_id');
    }

    /** @return list<string> */
    private function foreignKeys(string $table, string $column): array
    {
        $rows = $this->db->query('SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL', [$table, $column])->getResultArray();

        return array_values(array_filter(array_map(static fn (array $row): string => (string) ($row['CONSTRAINT_NAME'] ?? ''), $rows)));
    }

    /** @return list<string> */
    private function indexes(string $table, string $column): array
    {
        $rows = $this->db->query('SHOW INDEX FROM `' . $table . '` WHERE Column_name = ?', [$column])->getResultArray();

        return array_values(array_unique(array_filter(array_map(static fn (array $row): string => (string) ($row['Key_name'] ?? ''), $rows), static fn (string $name): bool => $name !== 'PRIMARY')));
    }
}
