<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDoctorDocuments extends Migration
{
    public function up()
    {
        $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'doctor_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'document_type' => ['type' => 'VARCHAR', 'constraint' => 100], 'document_name' => ['type' => 'VARCHAR', 'constraint' => 255], 'file_name' => ['type' => 'VARCHAR', 'constraint' => 255], 'file_path' => ['type' => 'VARCHAR', 'constraint' => 500], 'file_size' => ['type' => 'INT', 'constraint' => 10, 'null' => true], 'mime_type' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true], 'remarks' => ['type' => 'TEXT', 'null' => true], 'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true], 'deleted_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('doctor_id');
        $this->forge->addKey('document_type');
        $this->forge->addForeignKey('doctor_id', 'doctors', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('doctor_documents');
    }

    public function down()
    {
        $this->forge->dropTable('doctor_documents');
    }
}
