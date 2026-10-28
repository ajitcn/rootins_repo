<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCompletedPrescriptionStatus extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('prescription_status_options')) {
            return;
        }

        $exists = $this->db->table('prescription_status_options')->where('code', 'completed')->get(1)->getRowArray();
        if ($exists === null) {
            $this->db->table('prescription_status_options')->insert([
                'name' => 'Completed',
                'normalized_name' => 'completed',
                'code' => 'completed',
                'normalized_code' => 'completed',
                'status' => 1,
                'scope_key' => 'global',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('prescription_status_options')) {
            $this->db->table('prescription_status_options')->where('code', 'completed')->delete();
        }
    }
}
