<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePrescriptionItems extends Migration
{
    public function up()
    {
        $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'prescription_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'medicine_name' => ['type' => 'VARCHAR', 'constraint' => 255], 'dosage' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true], 'frequency' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true], 'duration' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true], 'duration_unit' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true], 'instructions' => ['type' => 'TEXT', 'null' => true], 'quantity' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'unsigned' => true, 'null' => true], 'notes' => ['type' => 'TEXT', 'null' => true], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('prescription_id');
        $this->forge->addKey('medicine_name');
        $this->forge->addForeignKey('prescription_id', 'prescriptions', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('prescription_items');
    }

    public function down()
    {
        $this->forge->dropTable('prescription_items', true);
    }
}
