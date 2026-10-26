<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddPrescriptionValidity extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('valid_until', 'prescriptions')) {
            $this->forge->addColumn('prescriptions', ['valid_until' => ['type' => 'DATE', 'null' => true, 'after' => 'prescription_date']]);
        }
        try { $this->db->query('ALTER TABLE prescriptions ADD INDEX prescriptions_valid_until (status, patient_id, valid_until)'); } catch (\Throwable) {}
    }

    public function down(): void
    {
        if ($this->db->tableExists('prescriptions') && $this->db->fieldExists('valid_until', 'prescriptions')) $this->forge->dropColumn('prescriptions', 'valid_until');
    }
}
