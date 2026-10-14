<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class MakeCoreApiCredentialsApplicationWide extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('core_api_credentials')) {
            return;
        }

        if (! $this->db->fieldExists('legacy_clinic_id', 'core_api_credentials')) {
            $this->forge->addColumn('core_api_credentials', ['legacy_clinic_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'secret_encrypted']]);
        }
        if ($this->db->fieldExists('clinic_id', 'core_api_credentials')) {
            $this->db->query('UPDATE core_api_credentials SET legacy_clinic_id = clinic_id WHERE legacy_clinic_id IS NULL');
            foreach ($this->foreignKeys('core_api_credentials', 'clinic_id') as $name) {
                $this->db->query('ALTER TABLE core_api_credentials DROP FOREIGN KEY `' . $name . '`');
            }
            foreach ($this->indexes('core_api_credentials', 'clinic_id') as $name) {
                $this->db->query('ALTER TABLE core_api_credentials DROP INDEX `' . $name . '`');
            }
            $this->forge->dropColumn('core_api_credentials', 'clinic_id');
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('core_api_credentials') || ! $this->db->fieldExists('legacy_clinic_id', 'core_api_credentials')) {
            return;
        }
        if (! $this->db->fieldExists('clinic_id', 'core_api_credentials')) {
            $this->forge->addColumn('core_api_credentials', ['clinic_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'secret_encrypted']]);
            $this->db->query('UPDATE core_api_credentials SET clinic_id = legacy_clinic_id WHERE legacy_clinic_id IS NOT NULL');
            $this->forge->addKey('clinic_id');
            $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'CASCADE', 'CASCADE');
        }
        $this->forge->dropColumn('core_api_credentials', 'legacy_clinic_id');
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
