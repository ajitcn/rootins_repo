<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateCrmMasterData extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('crm_lead_priorities')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'code' => ['type' => 'VARCHAR', 'constraint' => 40],
                'name' => ['type' => 'VARCHAR', 'constraint' => 80],
                'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'sort_order' => ['type' => 'INT', 'constraint' => 10, 'default' => 0],
                'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('code');
            $this->forge->addUniqueKey('name');
            $this->forge->createTable('crm_lead_priorities');
        }

        $this->addColumnIfMissing('follow_up_types', 'code', ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true]);
        $this->addColumnIfMissing('follow_up_types', 'description', ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true]);
        $this->addColumnIfMissing('follow_up_types', 'sort_order', ['type' => 'INT', 'constraint' => 10, 'default' => 0]);
        $this->addColumnIfMissing('follow_up_statuses', 'description', ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true]);
        $this->addColumnIfMissing('follow_up_statuses', 'sort_order', ['type' => 'INT', 'constraint' => 10, 'default' => 0]);

        foreach ([
            ['code' => 'low', 'name' => 'Low', 'sort_order' => 10],
            ['code' => 'normal', 'name' => 'Normal', 'sort_order' => 20],
            ['code' => 'high', 'name' => 'High', 'sort_order' => 30],
            ['code' => 'urgent', 'name' => 'Urgent', 'sort_order' => 40],
        ] as $row) {
            if (! $this->db->table('crm_lead_priorities')->where('code', $row['code'])->countAllResults()) {
                $row['created_at'] = date('Y-m-d H:i:s');
                $row['updated_at'] = $row['created_at'];
                $this->db->table('crm_lead_priorities')->insert($row);
            }
        }
        $this->backfillCodes('follow_up_types');
        $this->backfillCodes('follow_up_statuses');
    }

    public function down()
    {
        $this->forge->dropTable('crm_lead_priorities', true);
        foreach (['follow_up_types' => ['code', 'description', 'sort_order'], 'follow_up_statuses' => ['description', 'sort_order']] as $table => $columns) {
            foreach ($columns as $column) {
                if ($this->db->fieldExists($column, $table)) $this->forge->dropColumn($table, $column);
            }
        }
    }

    private function addColumnIfMissing(string $table, string $column, array $definition): void
    {
        if ($this->db->tableExists($table) && ! $this->db->fieldExists($column, $table)) $this->forge->addColumn($table, [$column => $definition]);
    }

    private function backfillCodes(string $table): void
    {
        if (! $this->db->tableExists($table) || ! $this->db->fieldExists('code', $table)) return;
        foreach ($this->db->table($table)->where('code IS NULL', null, false)->get()->getResultArray() as $row) {
            $code = strtolower(trim((string) ($row['normalized_code'] ?? $row['normalized_name'] ?? $row['name'] ?? '')));
            $code = preg_replace('/[^a-z0-9]+/', '_', $code) ?: 'master_' . (int) $row['id'];
            $this->db->table($table)->where('id', (int) $row['id'])->update(['code' => substr($code, 0, 40)]);
        }
    }
}
