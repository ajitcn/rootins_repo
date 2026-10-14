<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateFollowUpTypes extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('follow_up_types')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
                'name' => ['type' => 'VARCHAR', 'constraint' => 30],
                'normalized_name' => ['type' => 'VARCHAR', 'constraint' => 30],
                'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'scope_key' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'global'],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['clinic_id', 'status']);
            $this->forge->addUniqueKey(['scope_key', 'normalized_name']);
            $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'CASCADE', 'SET NULL', 'follow_up_types_clinic_fk');
            $this->forge->createTable('follow_up_types');
        }

        if (! $this->db->fieldExists('follow_up_type_id', 'consultations')) {
            $this->forge->addColumn('consultations', [
                'follow_up_type_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'follow_up_type'],
            ]);
            $this->db->query('ALTER TABLE consultations ADD INDEX consultations_follow_up_type_id_idx (follow_up_type_id)');
            $this->db->query('ALTER TABLE consultations ADD CONSTRAINT consultations_follow_up_type_fk FOREIGN KEY (follow_up_type_id) REFERENCES follow_up_types(id) ON UPDATE CASCADE ON DELETE SET NULL');
        }

        $this->backfillExistingTypes();
    }

    public function down()
    {
        if ($this->db->fieldExists('follow_up_type_id', 'consultations')) {
            $this->db->query('ALTER TABLE consultations DROP FOREIGN KEY consultations_follow_up_type_fk');
            $this->db->query('ALTER TABLE consultations DROP INDEX consultations_follow_up_type_id_idx');
            $this->forge->dropColumn('consultations', 'follow_up_type_id');
        }

        $this->forge->dropTable('follow_up_types', true);
    }

    private function backfillExistingTypes(): void
    {
        if (! $this->db->tableExists('consultations') || ! $this->db->fieldExists('follow_up_type_id', 'consultations')) {
            return;
        }

        $rows = $this->db->table('consultations')
            ->select('id, follow_up_type')
            ->where('follow_up_type IS NOT NULL', null, false)
            ->where('follow_up_type !=', '')
            ->where('follow_up_type_id IS NULL', null, false)
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $name = trim((string) ($row['follow_up_type'] ?? ''));

            if ($name === '') {
                continue;
            }
            $normalized = mb_strtolower($name);
            $type = $this->db->table('follow_up_types')
                ->where('scope_key', 'global')
                ->where('normalized_name', $normalized)
                ->get()
                ->getRowArray();

            if ($type === null) {
                $now = date('Y-m-d H:i:s');
                $this->db->table('follow_up_types')->insert([
                    'name' => $name,
                    'normalized_name' => $normalized,
                    'status' => 1,
                    'scope_key' => 'global',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $type = $this->db->table('follow_up_types')
                    ->where('scope_key', 'global')
                    ->where('normalized_name', $normalized)
                    ->get()
                    ->getRowArray();
            }

            if ($type !== null) {
                $this->db->table('consultations')
                    ->where('id', (int) $row['id'])
                    ->update(['follow_up_type_id' => (int) $type['id']]);
            }
        }
    }
}
