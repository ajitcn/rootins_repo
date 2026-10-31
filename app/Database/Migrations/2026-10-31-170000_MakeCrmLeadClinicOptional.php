<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

final class MakeCrmLeadClinicOptional extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('crm_leads')) return;
        $this->forge->modifyColumn('crm_leads', 'clinic_id', ['type'=>'INT','constraint'=>10,'unsigned'=>true,'null'=>true]);
        $this->forge->modifyColumn('crm_leads', 'mobile_number', ['type'=>'VARCHAR','constraint'=>30,'null'=>true]);
    }
    public function down(): void
    {
        if (! $this->db->tableExists('crm_leads')) return;
        // Existing global leads must be assigned before making this column required again.
        $this->forge->modifyColumn('crm_leads', 'clinic_id', ['type'=>'INT','constraint'=>10,'unsigned'=>true,'null'=>false]);
        $this->forge->modifyColumn('crm_leads', 'mobile_number', ['type'=>'VARCHAR','constraint'=>30,'null'=>false]);
    }
}
