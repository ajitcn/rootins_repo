<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class NormalizePatientRegistrationSource extends Migration
{
    public function up()
    {
        // Existing values are intentionally preserved. Blank and legacy
        // values remain displayable as Unknown until a safe backfill exists.
        $this->forge->modifyColumn('patients', [
            'source_type' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
                'default' => null,
                'after' => 'status',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->modifyColumn('patients', [
            'source_type' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'admin',
                'after' => 'status',
            ],
        ]);
    }
}
