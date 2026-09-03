<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
class CreateDoctorSchedules extends Migration
{
    public function up()
    {
        $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'doctor_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'day_of_week' => ['type' => 'TINYINT', 'constraint' => 1, 'comment' => '1=Monday ... 7=Sunday'], 'shift_name' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true], 'start_time' => ['type' => 'TIME'], 'end_time' => ['type' => 'TIME'], 'slot_duration' => ['type' => 'SMALLINT', 'constraint' => 3, 'default' => 15, 'comment' => 'Minutes'], 'max_patients' => ['type' => 'SMALLINT', 'constraint' => 5, 'default' => 0], 'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true], 'deleted_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('doctor_id');
        $this->forge->addKey(['doctor_id', 'day_of_week']);
        $this->forge->addForeignKey('doctor_id', 'doctors', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('doctor_schedules');
    }
    public function down()
    {
        $this->forge->dropTable('doctor_schedules');
    }
}
