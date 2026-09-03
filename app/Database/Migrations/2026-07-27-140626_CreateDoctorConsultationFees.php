<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
class CreateDoctorConsultationFees extends Migration
{
    public function up()
    {
        $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'doctor_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'consultation_type' => ['type' => 'ENUM', 'constraint' => ['NEW', 'FOLLOWUP', 'ALL'], 'default' => 'NEW'], 'consultation_mode' => ['type' => 'ENUM', 'constraint' => ['OFFLINE', 'ONLINE', 'HOME_VISIT'], 'default' => 'OFFLINE'], 'fee' => ['type' => 'DECIMAL', 'constraint' => '10,2'], 'effective_from' => ['type' => 'DATE'], 'effective_to' => ['type' => 'DATE', 'null' => true], 'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('doctor_id');
        $this->forge->addKey('effective_from');
        $this->forge->addForeignKey('doctor_id', 'doctors', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('doctor_consultation_fees');
    }
    public function down()
    {
        $this->forge->dropTable('doctor_consultation_fees');
    }
}
