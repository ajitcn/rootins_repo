<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Patient ownership is global; clinic_id is retained only as optional legacy/billing metadata. */
final class MakePatientClinicScopeOptional extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('patients') && $this->db->fieldExists('clinic_id', 'patients')) {
            $this->forge->modifyColumn('patients', [
                'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            ]);
        }
    }

    public function down()
    {
        // Keep the column nullable on rollback: existing global patients cannot
        // be assigned a fictitious clinic safely.
    }
}
