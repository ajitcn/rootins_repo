<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddAgeToCrmLeads extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('crm_leads') || $this->db->fieldExists('age', 'crm_leads')) {
            return;
        }

        $this->forge->addColumn('crm_leads', [
            'age' => ['type' => 'SMALLINT', 'constraint' => 3, 'unsigned' => true, 'null' => true, 'after' => 'full_name'],
        ]);
    }

    public function down(): void
    {
        if ($this->db->tableExists('crm_leads') && $this->db->fieldExists('age', 'crm_leads')) {
            $this->forge->dropColumn('crm_leads', 'age');
        }
    }
}
