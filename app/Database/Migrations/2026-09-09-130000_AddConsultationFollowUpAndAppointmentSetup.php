<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddConsultationFollowUpAndAppointmentSetup extends Migration
{
    public function up()
    {
        $this->forge->addColumn('consultations', [
            'follow_up_after_days' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true, 'after' => 'follow_up_date'],
            'follow_up_time' => ['type' => 'TIME', 'null' => true, 'after' => 'follow_up_after_days'],
            'follow_up_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'follow_up_time'],
            'follow_up_remarks' => ['type' => 'TEXT', 'null' => true, 'after' => 'follow_up_type'],
            'next_appointment_after_days' => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true, 'after' => 'follow_up_remarks'],
            'next_appointment_date' => ['type' => 'DATE', 'null' => true, 'after' => 'next_appointment_after_days'],
            'next_appointment_time' => ['type' => 'TIME', 'null' => true, 'after' => 'next_appointment_date'],
            'next_appointment_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'next_appointment_time'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('consultations', [
            'follow_up_after_days',
            'follow_up_time',
            'follow_up_type',
            'follow_up_remarks',
            'next_appointment_after_days',
            'next_appointment_date',
            'next_appointment_time',
            'next_appointment_type',
        ]);
    }
}
