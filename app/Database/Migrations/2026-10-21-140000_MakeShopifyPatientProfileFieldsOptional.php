<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class MakeShopifyPatientProfileFieldsOptional extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('patients')) return;

        $this->forge->modifyColumn('patients', [
            'date_of_birth' => ['type' => 'DATE', 'null' => true],
            'gender' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
        ]);
    }

    public function down()
    {
        // Existing rows may contain blank profile fields; a rollback must not
        // silently make those records invalid.
        if ($this->db->tableExists('patients')) {
            $this->forge->modifyColumn('patients', [
                'date_of_birth' => ['type' => 'DATE', 'null' => true],
                'gender' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            ]);
        }
    }
}
