<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBreakTimesToDoctorSchedules extends Migration
{
    public function up()
    {
        $this->forge->addColumn('doctor_schedules', [
            'break_start_time' => ['type' => 'TIME', 'null' => true, 'after' => 'end_time'],
            'break_end_time' => ['type' => 'TIME', 'null' => true, 'after' => 'break_start_time'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('doctor_schedules', ['break_start_time', 'break_end_time']);
    }
}
