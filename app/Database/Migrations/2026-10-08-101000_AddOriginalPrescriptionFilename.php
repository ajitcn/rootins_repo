<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddOriginalPrescriptionFilename extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('patient_documents') && ! $this->db->fieldExists('original_file_name', 'patient_documents')) {
            $this->forge->addColumn('patient_documents', ['original_file_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'file_name']]);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('patient_documents') && $this->db->fieldExists('original_file_name', 'patient_documents')) {
            $this->forge->dropColumn('patient_documents', 'original_file_name');
        }
    }
}
