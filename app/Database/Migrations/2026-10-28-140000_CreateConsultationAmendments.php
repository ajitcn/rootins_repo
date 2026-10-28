<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateConsultationAmendments extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('consultation_amendments')) return;
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'original_consultation_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'revision_number' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'patient_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'doctor_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'clinic_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'appointment_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'prescription_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'revision_json' => ['type' => 'TEXT'],
            'amendment_reason' => ['type' => 'TEXT'],
            'order_review_required' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'review_status' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'not_required'],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('original_consultation_id');
        $this->forge->addKey(['clinic_id', 'patient_id']);
        $this->forge->addUniqueKey(['original_consultation_id', 'revision_number']);
        $this->forge->createTable('consultation_amendments');
    }

    public function down(): void
    {
        $this->forge->dropTable('consultation_amendments', true);
    }
}
