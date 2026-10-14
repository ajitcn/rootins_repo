<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTimesToDoctorLeaves extends Migration
{
    public function up()
    {
        $this->forge->addColumn('doctor_leaves', [
            'start_time' => ['type' => 'TIME', 'null' => true, 'after' => 'end_date'],
            'end_time' => ['type' => 'TIME', 'null' => true, 'after' => 'start_time'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('doctor_leaves', ['start_time', 'end_time']);
    }
}
