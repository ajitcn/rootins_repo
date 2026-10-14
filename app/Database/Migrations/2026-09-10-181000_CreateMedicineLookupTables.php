<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMedicineLookupTables extends Migration
{
    public function up()
    {
        $this->createNameTable('medicine_forms', 100);
        $this->createNameTable('medicine_units', 100);
        $this->createNameTable('medicine_manufacturers', 255);
        $this->createStatusTable();

        $this->addMedicineReferences();
        $this->seedDefaults();
        $this->backfillReferences();
    }

    public function down()
    {
        $this->db->query('ALTER TABLE medicines DROP FOREIGN KEY fk_medicines_form_id');
        $this->db->query('ALTER TABLE medicines DROP FOREIGN KEY fk_medicines_unit_id');
        $this->db->query('ALTER TABLE medicines DROP FOREIGN KEY fk_medicines_manufacturer_id');
        $this->db->query('ALTER TABLE medicines DROP FOREIGN KEY fk_medicines_status_id');
        $this->forge->dropColumn('medicines', ['form_id', 'unit_id', 'manufacturer_id', 'status_id']);
        $this->forge->dropTable('medicine_status_options', true);
        $this->forge->dropTable('medicine_manufacturers', true);
        $this->forge->dropTable('medicine_units', true);
        $this->forge->dropTable('medicine_forms', true);
    }

    private function createNameTable(string $table, int $length): void
    {
        if ($this->db->tableExists($table)) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => $length],
            'normalized_name' => ['type' => 'VARCHAR', 'constraint' => $length],
            'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('normalized_name');
        $this->forge->addKey('status');
        $this->forge->createTable($table);
    }

    private function createStatusTable(): void
    {
        if ($this->db->tableExists('medicine_status_options')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 30],
            'name' => ['type' => 'VARCHAR', 'constraint' => 50],
            'normalized_code' => ['type' => 'VARCHAR', 'constraint' => 30],
            'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('normalized_code');
        $this->forge->addKey('status');
        $this->forge->createTable('medicine_status_options');
    }

    private function addMedicineReferences(): void
    {
        $columns = [
            'form_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'dosage_form'],
            'unit_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'unit'],
            'manufacturer_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'manufacturer'],
            'status_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'status'],
        ];

        foreach ($columns as $name => $definition) {
            if (! $this->db->fieldExists($name, 'medicines')) {
                $this->forge->addColumn('medicines', [$name => $definition]);
            }
        }
        $this->db->query('ALTER TABLE medicines ADD INDEX idx_medicines_form_id (form_id), ADD INDEX idx_medicines_unit_id (unit_id), ADD INDEX idx_medicines_manufacturer_id (manufacturer_id), ADD INDEX idx_medicines_status_id (status_id)');
        $this->db->query('ALTER TABLE medicines ADD CONSTRAINT fk_medicines_form_id FOREIGN KEY (form_id) REFERENCES medicine_forms(id) ON UPDATE CASCADE ON DELETE SET NULL');
        $this->db->query('ALTER TABLE medicines ADD CONSTRAINT fk_medicines_unit_id FOREIGN KEY (unit_id) REFERENCES medicine_units(id) ON UPDATE CASCADE ON DELETE SET NULL');
        $this->db->query('ALTER TABLE medicines ADD CONSTRAINT fk_medicines_manufacturer_id FOREIGN KEY (manufacturer_id) REFERENCES medicine_manufacturers(id) ON UPDATE CASCADE ON DELETE SET NULL');
        $this->db->query('ALTER TABLE medicines ADD CONSTRAINT fk_medicines_status_id FOREIGN KEY (status_id) REFERENCES medicine_status_options(id) ON UPDATE CASCADE ON DELETE SET NULL');
    }

    private function seedDefaults(): void
    {
        foreach (['Tablet', 'Capsule', 'Syrup', 'Injection', 'Cream', 'Drops'] as $name) {
            $this->insertNameIfMissing('medicine_forms', $name);
        }

        foreach (['mg', 'g', 'ml', 'tablet', 'capsule', 'piece'] as $name) {
            $this->insertNameIfMissing('medicine_units', $name);
        }
        $this->insertNameIfMissing('medicine_manufacturers', 'Generic');

        foreach ([['active', 'Active'], ['inactive', 'Inactive']] as $status) {
            $normalized = strtolower($status[0]);

            if ($this->db->table('medicine_status_options')->where('normalized_code', $normalized)->countAllResults() === 0) {
                $this->db->table('medicine_status_options')->insert(['code' => $status[0], 'name' => $status[1], 'normalized_code' => $normalized, 'status' => 1]);
            }
        }
    }

    private function insertNameIfMissing(string $table, string $name): void
    {
        $normalized = strtolower(trim($name));

        if ($normalized === '' || $this->db->table($table)->where('normalized_name', $normalized)->countAllResults() > 0) {
            return;
        }
        $this->db->table($table)->insert(['name' => trim($name), 'normalized_name' => $normalized, 'status' => 1]);
    }

    private function backfillReferences(): void
    {
        $map = [
            ['source' => 'dosage_form', 'table' => 'medicine_forms', 'target' => 'form_id'],
            ['source' => 'unit', 'table' => 'medicine_units', 'target' => 'unit_id'],
            ['source' => 'manufacturer', 'table' => 'medicine_manufacturers', 'target' => 'manufacturer_id'],
        ];

        foreach ($this->db->table('medicines')->get()->getResultArray() as $medicine) {
            foreach ($map as $item) {
                $value = trim((string) ($medicine[$item['source']] ?? ''));

                if ($value === '') {
                    continue;
                }
                $normalized = strtolower($value);
                $lookup = $this->db->table($item['table'])->where('normalized_name', $normalized)->get()->getRowArray();

                if ($lookup === null) {
                    $this->insertNameIfMissing($item['table'], $value);
                    $lookup = $this->db->table($item['table'])->where('normalized_name', $normalized)->get()->getRowArray();
                }

                if ($lookup !== null) {
                    $this->db->table('medicines')->where('id', (int) $medicine['id'])->update([$item['target'] => (int) $lookup['id']]);
                }
            }
            $status = strtolower(trim((string) ($medicine['status'] ?? 'active')));
            $statusLookup = $this->db->table('medicine_status_options')->where('normalized_code', $status)->get()->getRowArray();

            if ($statusLookup !== null) {
                $this->db->table('medicines')->where('id', (int) $medicine['id'])->update(['status_id' => (int) $statusLookup['id']]);
            }
        }
    }
}
