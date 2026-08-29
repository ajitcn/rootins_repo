<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePatientMedicalHistories extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'patient_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'details' => [
                'type' => 'TEXT',
            ],
            'history_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('patient_id');
        $this->forge->addKey('history_date');

        $this->forge->addForeignKey(
            'patient_id',
            'patients',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        $this->forge->createTable('patient_medical_histories');
    }

    public function down()
    {
        $this->forge->dropTable('patient_medical_histories', true);
    }
}
