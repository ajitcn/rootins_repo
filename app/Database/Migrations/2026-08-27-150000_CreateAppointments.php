<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
class CreateAppointments extends Migration
{
    public function up()
    {
        $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'appointment_number' => ['type' => 'VARCHAR', 'constraint' => 30], 'patient_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'doctor_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'appointment_date' => ['type' => 'DATE'], 'appointment_time' => ['type' => 'TIME'], 'duration_minutes' => ['type' => 'SMALLINT', 'constraint' => 3, 'unsigned' => true, 'default' => 15], 'appointment_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'in_person'], 'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'scheduled'], 'created_by_type' => ['type' => 'VARCHAR', 'constraint' => 30], 'created_by_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'booking_source' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'admin'], 'reason_for_visit' => ['type' => 'TEXT', 'null' => true], 'notes' => ['type' => 'TEXT', 'null' => true], 'cancelled_at' => ['type' => 'DATETIME', 'null' => true], 'cancelled_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], 'cancellation_reason' => ['type' => 'TEXT', 'null' => true], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('appointment_number');
        $this->forge->addKey('patient_id');
        $this->forge->addKey('doctor_id');
        $this->forge->addKey('clinic_id');
        $this->forge->addKey(['doctor_id', 'appointment_date', 'appointment_time']);
        $this->forge->addKey(['patient_id', 'appointment_date']);
        $this->forge->addKey(['clinic_id', 'appointment_date']);
        $this->forge->addKey('status');
        $this->forge->addKey(['created_by_type', 'created_by_id']);
        $this->forge->addKey('cancelled_by');
        $this->forge->addForeignKey('patient_id', 'patients', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('doctor_id', 'doctors', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('appointments');
    }
    public function down()
    {
        $this->forge->dropTable('appointments', true);
    }
}
