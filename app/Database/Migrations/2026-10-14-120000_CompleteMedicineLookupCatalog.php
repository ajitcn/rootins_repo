<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Complete the clinic-aware medicine lookup catalog without removing legacy values. */
class CompleteMedicineLookupCatalog extends Migration
{
    public function up(): void
    {
        $this->createStrengthsTable();
        $this->addMedicineStrengthReference();
        $this->backfillStrengths();
    }

    public function down(): void
    {
        if ($this->db->fieldExists('strength_id', 'medicines')) {
            $this->dropForeignKeyIfPresent('medicines', 'fk_medicines_strength_id');
            $this->dropIndexIfPresent('medicines', 'idx_medicines_strength_id');
            $this->forge->dropColumn('medicines', 'strength_id');
        }

        $this->forge->dropTable('medicine_strengths', true);
    }

    private function createStrengthsTable(): void
    {
        if ($this->db->tableExists('medicine_strengths')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'normalized_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('status');
        $this->forge->addUniqueKey('normalized_name');
        $this->forge->createTable('medicine_strengths');
    }

    private function addMedicineStrengthReference(): void
    {
        if (! $this->db->fieldExists('strength_id', 'medicines')) {
            $this->forge->addColumn('medicines', [
                'strength_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'strength'],
            ]);
        }
        $this->addIndexIfMissing('medicines', 'idx_medicines_strength_id', ['strength_id']);
        $this->addForeignKeyIfMissing('medicines', 'fk_medicines_strength_id', 'strength_id', 'medicine_strengths', 'id', 'SET NULL', 'CASCADE');
    }

    private function backfillStrengths(): void
    {
        foreach ($this->db->table('medicines')->where('strength IS NOT NULL')->where('strength !=', '')->get()->getResultArray() as $medicine) {
            $name = trim((string) $medicine['strength']);
            $normalized = strtolower($name);
            $lookup = $this->db->table('medicine_strengths')->where('normalized_name', $normalized)->get()->getRowArray();

            if ($lookup === null) {
                $this->db->table('medicine_strengths')->insert(['name' => $name, 'normalized_name' => $normalized, 'status' => 1]);
                $lookup = $this->db->table('medicine_strengths')->where('normalized_name', $normalized)->get()->getRowArray();
            }

            if ($lookup !== null && empty($medicine['strength_id'])) {
                $this->db->table('medicines')->where('id', (int) $medicine['id'])->update(['strength_id' => (int) $lookup['id']]);
            }
        }
    }

    private function addIndexIfMissing(string $table, string $index, array $columns): void
    {
        if (! $this->hasIndex($table, $index)) {
            $this->forge->addKey($columns, false, $index);
            $this->forge->processIndexes($table);
        }
    }

    private function addUniqueIndexIfMissing(string $table, string $index, array $columns): void
    {
        if (! $this->hasIndex($table, $index)) {
            $this->forge->addUniqueKey($columns, $index);
            $this->forge->processIndexes($table);
        }
    }

    private function addForeignKeyIfMissing(string $table, string $name, string $column, string $referenceTable, string $referenceColumn, string $onDelete, string $onUpdate): void
    {
        if (! $this->hasForeignKey($table, $name)) {
            $this->db->query("ALTER TABLE {$table} ADD CONSTRAINT {$name} FOREIGN KEY ({$column}) REFERENCES {$referenceTable}({$referenceColumn}) ON DELETE {$onDelete} ON UPDATE {$onUpdate}");
        }
    }

    private function dropIndexIfPresent(string $table, string $name): void
    {
        if ($this->hasIndex($table, $name)) {
            $this->db->query("ALTER TABLE {$table} DROP INDEX {$name}");
        }
    }

    private function dropForeignKeyIfPresent(string $table, string $name): void
    {
        if ($this->hasForeignKey($table, $name)) {
            $this->db->query("ALTER TABLE {$table} DROP FOREIGN KEY {$name}");
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        foreach ($this->db->query("SHOW INDEX FROM {$table}")->getResultArray() as $index) {
            if ((string) ($index['Key_name'] ?? '') === $name) {
                return true;
            }
        }

        return false;
    }

    private function hasForeignKey(string $table, string $name): bool
    {
        return $this->db->query('SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?', [$table, $name])->getRowArray() !== null;
    }
}
