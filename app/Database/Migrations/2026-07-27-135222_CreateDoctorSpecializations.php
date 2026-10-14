<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDoctorSpecializations extends Migration
{
    public function up()
    {
        $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'specialization_name' => ['type' => 'VARCHAR', 'constraint' => 100], 'description' => ['type' => 'TEXT', 'null' => true], 'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true], 'deleted_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('specialization_name');
        $this->forge->createTable('doctor_specializations');
    }

    public function down()
    {
        $this->forge->dropTable('doctor_specializations');
    }
}
