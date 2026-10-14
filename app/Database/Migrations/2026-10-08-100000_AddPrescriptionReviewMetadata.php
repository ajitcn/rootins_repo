<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddPrescriptionReviewMetadata extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('patient_documents')) {
            return;
        }

        if (! $this->db->fieldExists('review_message', 'patient_documents')) {
            $this->forge->addColumn('patient_documents', ['review_message' => ['type' => 'TEXT', 'null' => true, 'after' => 'upload_status']]);
        }
        if (! $this->db->fieldExists('reviewed_at', 'patient_documents')) {
            $this->forge->addColumn('patient_documents', ['reviewed_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'review_message']]);
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('patient_documents')) {
            return;
        }

        foreach (['review_message', 'reviewed_at'] as $column) {
            if ($this->db->fieldExists($column, 'patient_documents')) {
                $this->forge->dropColumn('patient_documents', $column);
            }
        }
    }
}
