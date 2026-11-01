<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCrmTags extends Migration
{
    public function up(): void
    {
        foreach (['crm_leads', 'crm_lead_followups'] as $table) {
            if ($this->db->tableExists($table) && ! $this->db->fieldExists('tags', $table)) {
                $this->forge->addColumn($table, ['tags' => ['type' => 'TEXT', 'null' => true]]);
            }
        }
    }

    public function down(): void
    {
        // Preserve CRM history in production.
    }
}
