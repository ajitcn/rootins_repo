<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
class CreatePatientDocuments extends Migration
{
    public function up()
    {
        $this->forge->addField(['id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true], 'patient_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true], 'document_type' => ['type' => 'VARCHAR', 'constraint' => 100], 'title' => ['type' => 'VARCHAR', 'constraint' => 255], 'file_name' => ['type' => 'VARCHAR', 'constraint' => 255], 'file_path' => ['type' => 'VARCHAR', 'constraint' => 500], 'file_size' => ['type' => 'INT', 'constraint' => 10, 'null' => true], 'mime_type' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true], 'created_at' => ['type' => 'DATETIME', 'null' => true], 'updated_at' => ['type' => 'DATETIME', 'null' => true]]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('patient_id');
        $this->forge->addKey('document_type');
        $this->forge->addForeignKey('patient_id', 'patients', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('patient_documents');
    }
    public function down()
    {
        $this->forge->dropTable('patient_documents', true);
    }
}
