<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddPrescriptionDurationDays extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('duration_days', 'prescriptions')) {
            $this->forge->addColumn('prescriptions', [
                'duration_days' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'prescription_date'],
            ]);
        }
        try {
            $this->db->query('ALTER TABLE prescriptions ADD INDEX prescriptions_duration_days (status, duration_days)');
        } catch (\Throwable) {
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('prescriptions') && $this->db->fieldExists('duration_days', 'prescriptions')) {
            $this->forge->dropColumn('prescriptions', 'duration_days');
        }
    }
}
