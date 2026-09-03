<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPatientSourceAndAppointmentService extends Migration
{
    public function up()
    {
        $this->forge->addColumn('patients', [
            'source_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'admin', 'after' => 'status'],
            'source_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'source_type'],
            'source_reference' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'source_user_id'],
        ]);
        $this->forge->addKey('source_type', false, 'patients');
        $this->forge->addKey(['clinic_id', 'source_type'], false, 'patients');
        $this->forge->addColumn('appointments', [
            'service_name' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'appointment_type'],
        ]);
        $this->forge->addKey(['clinic_id', 'doctor_id', 'appointment_date', 'appointment_time'], false, 'appointments');
    }

    public function down()
    {
        $this->forge->dropColumn('appointments', 'service_name');
        $this->forge->dropColumn('patients', ['source_type', 'source_user_id', 'source_reference']);
    }
}
