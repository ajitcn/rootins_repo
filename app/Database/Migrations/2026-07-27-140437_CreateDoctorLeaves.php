<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
class CreateDoctorLeaves extends Migration
{
    public function up()
    {
        $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'doctor_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'leave_type' => ['type' => 'VARCHAR', 'constraint' => 50], 'start_date' => ['type' => 'DATE'], 'end_date' => ['type' => 'DATE'], 'reason' => ['type' => 'TEXT', 'null' => true], 'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'comment' => '1=Approved,0=Cancelled'], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('doctor_id');
        $this->forge->addKey(['doctor_id', 'start_date']);
        $this->forge->addKey(['doctor_id', 'end_date']);
        $this->forge->addForeignKey('doctor_id', 'doctors', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('doctor_leaves');
    }
    public function down()
    {
        $this->forge->dropTable('doctor_leaves');
    }
}
