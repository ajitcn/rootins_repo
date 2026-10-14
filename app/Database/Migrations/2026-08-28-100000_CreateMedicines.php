<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMedicines extends Migration
{
    public function up()
    {
        $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'medicine_name' => ['type' => 'VARCHAR', 'constraint' => 255], 'generic_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true], 'medicine_code' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true], 'dosage_form' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true], 'strength' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true], 'manufacturer' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true], 'description' => ['type' => 'TEXT', 'null' => true], 'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'active'], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('medicine_name');
        $this->forge->addKey('generic_name');
        $this->forge->addKey('dosage_form');
        $this->forge->addKey('manufacturer');
        $this->forge->addKey('status');
        $this->forge->addUniqueKey('medicine_code');
        $this->forge->createTable('medicines');
    }

    public function down()
    {
        $this->forge->dropTable('medicines', true);
    }
}
