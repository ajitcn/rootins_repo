<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddAiCallbackPreferenceFields extends Migration
{
    public function up(): void
    {
        $table = 'shopify_ai_analysis_callback_requests';
        if (! $this->db->tableExists($table)) {
            return;
        }

        $fields = [
            'preferred_date' => ['type' => 'DATE', 'null' => true],
            'preferred_slot' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
            'preferred_slot_label' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
        ];
        foreach ($fields as $name => $definition) {
            if (! $this->db->fieldExists($name, $table)) {
                $this->forge->addColumn($table, [$name => $definition]);
            }
        }
    }

    public function down(): void
    {
        // Preserve callback history in production.
    }
}
