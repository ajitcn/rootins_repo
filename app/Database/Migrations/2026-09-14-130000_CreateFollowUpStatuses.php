<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateFollowUpStatuses extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('follow_up_statuses')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
                'code' => ['type' => 'VARCHAR', 'constraint' => 30],
                'name' => ['type' => 'VARCHAR', 'constraint' => 50],
                'normalized_code' => ['type' => 'VARCHAR', 'constraint' => 30],
                'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'scope_key' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'global'],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['clinic_id', 'status']);
            $this->forge->addUniqueKey(['scope_key', 'normalized_code']);
            $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'CASCADE', 'SET NULL', 'follow_up_statuses_clinic_fk');
            $this->forge->createTable('follow_up_statuses');
        }

        if (! $this->db->fieldExists('follow_up_status_id', 'consultations')) {
            $this->forge->addColumn('consultations', [
                'follow_up_status_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'status'],
            ]);
            $this->db->query('ALTER TABLE consultations ADD INDEX consultations_follow_up_status_id_idx (follow_up_status_id)');
            $this->db->query('ALTER TABLE consultations ADD CONSTRAINT consultations_follow_up_status_fk FOREIGN KEY (follow_up_status_id) REFERENCES follow_up_statuses(id) ON UPDATE CASCADE ON DELETE SET NULL');
        }

        $this->seedAndBackfillStatuses();
    }

    public function down()
    {
        if ($this->db->fieldExists('follow_up_status_id', 'consultations')) {
            $this->db->query('ALTER TABLE consultations DROP FOREIGN KEY consultations_follow_up_status_fk');
            $this->db->query('ALTER TABLE consultations DROP INDEX consultations_follow_up_status_id_idx');
            $this->forge->dropColumn('consultations', 'follow_up_status_id');
        }

        $this->forge->dropTable('follow_up_statuses', true);
    }

    private function seedAndBackfillStatuses(): void
    {
        $defaults = [
            ['code' => 'draft', 'name' => 'Draft'],
            ['code' => 'in_progress', 'name' => 'In progress'],
            ['code' => 'completed', 'name' => 'Completed'],
            ['code' => 'cancelled', 'name' => 'Cancelled'],
        ];

        foreach ($defaults as $default) {
            $this->ensureStatus($default['code'], $default['name']);
        }

        if (! $this->db->tableExists('consultations')) {
            return;
        }
        $rows = $this->db->table('consultations')
            ->select('id, status, follow_up_status_id')
            ->where('status IS NOT NULL', null, false)
            ->where('status !=', '')
            ->where('follow_up_status_id IS NULL', null, false)
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $code = strtolower(trim((string) ($row['status'] ?? '')));

            if ($code === '') {
                continue;
            }
            $name = ucwords(str_replace('_', ' ', $code));
            $status = $this->ensureStatus($code, $name);

            if ($status !== null) {
                $this->db->table('consultations')->where('id', (int) $row['id'])->update(['follow_up_status_id' => (int) $status['id']]);
            }
        }
    }

    private function ensureStatus(string $code, string $name): ?array
    {
        $normalized = strtolower(trim($code));
        $status = $this->db->table('follow_up_statuses')->where('scope_key', 'global')->where('normalized_code', $normalized)->get()->getRowArray();

        if ($status !== null) {
            return $status;
        }
        $now = date('Y-m-d H:i:s');
        $this->db->table('follow_up_statuses')->insert([
            'code' => substr($code, 0, 30),
            'name' => substr($name, 0, 50),
            'normalized_code' => substr($normalized, 0, 30),
            'status' => 1,
            'scope_key' => 'global',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->db->table('follow_up_statuses')->where('scope_key', 'global')->where('normalized_code', $normalized)->get()->getRowArray() ?: null;
    }
}
