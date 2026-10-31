<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

final class MakeCrmLeadClinicOptional extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('crm_leads')) return;
        // Forge::modifyColumn() is not supported reliably by the deployed CI4/MySQL combination.
        // MODIFY COLUMN preserves the existing indexes and clinic foreign key.
        $this->db->query('ALTER TABLE `crm_leads` MODIFY COLUMN `clinic_id` INT UNSIGNED NULL');
        $this->db->query('ALTER TABLE `crm_leads` MODIFY COLUMN `mobile_number` VARCHAR(30) NULL');
    }
    public function down(): void
    {
        if (! $this->db->tableExists('crm_leads')) return;
        // Existing global leads must be assigned before making this column required again.
        $nulls = $this->db->table('crm_leads')->where('clinic_id', null)->countAllResults();
        if ($nulls > 0) throw new \RuntimeException('Cannot make crm_leads.clinic_id required while global CRM leads exist.');
        $this->db->query('ALTER TABLE `crm_leads` MODIFY COLUMN `clinic_id` INT UNSIGNED NOT NULL');
        $this->db->query('ALTER TABLE `crm_leads` MODIFY COLUMN `mobile_number` VARCHAR(30) NOT NULL');
    }
}
