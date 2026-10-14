<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateDoctorVideoConsultationSessions extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'appointment_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'patient_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'doctor_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'provider' => ['type' => 'VARCHAR', 'constraint' => 40, 'default' => 'provider_boundary'],
            'room_reference' => ['type' => 'VARCHAR', 'constraint' => 96],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'waiting'],
            'started_at' => ['type' => 'DATETIME', 'null' => true],
            'ended_at' => ['type' => 'DATETIME', 'null' => true],
            'failure_reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'recording_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'recording_consent_state' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'not_requested'],
            'provider_recording_id' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'recording_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'recording_duration_seconds' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'recording_retention_expires_at' => ['type' => 'DATETIME', 'null' => true],
            'recording_reference' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('appointment_id');
        $this->forge->addKey(['patient_id', 'clinic_id']);
        $this->forge->addKey(['doctor_id', 'clinic_id']);
        $this->forge->addKey('status');
        $this->forge->addForeignKey('appointment_id', 'appointments', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('patient_id', 'patients', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('doctor_id', 'doctors', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('doctor_video_consultation_sessions');
    }

    public function down()
    {
        $this->forge->dropTable('doctor_video_consultation_sessions', true);
    }
}
