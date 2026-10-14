<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOpdConsultationFields extends Migration
{
    public function up()
    {
        $this->forge->addColumn('consultations', [
            'diagnostic_tests' => ['type' => 'TEXT', 'null' => true, 'after' => 'treatment_plan'],
            'diet_plan' => ['type' => 'TEXT', 'null' => true, 'after' => 'diagnostic_tests'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('consultations', ['diagnostic_tests', 'diet_plan']);
    }
}
