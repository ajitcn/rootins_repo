<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateConsultations extends Migration
{
    public function up()
    {
        $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'consultation_number' => ['type' => 'VARCHAR', 'constraint' => 30], 'patient_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'doctor_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'appointment_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'consultation_date' => ['type' => 'DATE'], 'consultation_time' => ['type' => 'TIME', 'null' => true], 'chief_complaint' => ['type' => 'TEXT', 'null' => true], 'clinical_notes' => ['type' => 'TEXT', 'null' => true], 'diagnosis' => ['type' => 'TEXT', 'null' => true], 'treatment_plan' => ['type' => 'TEXT', 'null' => true], 'follow_up_date' => ['type' => 'DATE', 'null' => true], 'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'draft'], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('consultation_number');
        $this->forge->addKey('patient_id');
        $this->forge->addKey('doctor_id');
        $this->forge->addKey('clinic_id');
        $this->forge->addKey('appointment_id');
        $this->forge->addKey(['doctor_id', 'consultation_date']);
        $this->forge->addKey(['patient_id', 'consultation_date']);
        $this->forge->addKey(['clinic_id', 'consultation_date']);
        $this->forge->addKey('status');
        $this->forge->addForeignKey('patient_id', 'patients', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('doctor_id', 'doctors', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('appointment_id', 'appointments', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('consultations');
    }

    public function down()
    {
        $this->forge->dropTable('consultations', true);
    }
}
