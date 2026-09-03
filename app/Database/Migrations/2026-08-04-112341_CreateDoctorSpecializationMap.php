<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
class CreateDoctorSpecializationMap extends Migration
{
    public function up()
    {
        $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'doctor_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'specialization_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true], 'deleted_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        // Prevent duplicate mappings
        $this->forge->addUniqueKey(['doctor_id', 'specialization_id']);
        $this->forge->addKey('doctor_id');
        $this->forge->addKey('specialization_id');
        $this->forge->addForeignKey('doctor_id', 'doctors', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('specialization_id', 'doctor_specializations', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('doctor_specialization_map');
    }
    public function down()
    {
        $this->forge->dropTable('doctor_specialization_map');
    }
}
