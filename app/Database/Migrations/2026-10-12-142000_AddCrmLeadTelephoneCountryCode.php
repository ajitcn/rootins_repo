<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCrmLeadTelephoneCountryCode extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('crm_leads')) {
            return;
        }

        if (! $this->db->fieldExists('telephone_country_code_id', 'crm_leads')) {
            $this->forge->addColumn('crm_leads', [
                'telephone_country_code_id' => [
                    'type'       => 'SMALLINT',
                    'constraint' => 5,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'mobile_number',
                ],
            ]);
        } else {
            // Match the SMALLINT UNSIGNED master primary key on installations
            // where an interrupted first run added the wider integer column.
            $this->forge->modifyColumn('crm_leads', [
                'telephone_country_code_id' => [
                    'type'       => 'SMALLINT',
                    'constraint' => 5,
                    'unsigned'   => true,
                    'null'       => true,
                ],
            ]);
        }

        if (! $this->db->fieldExists('country_code', 'crm_leads')) {
            $this->forge->addColumn('crm_leads', [
                'country_code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 8,
                    'null'       => true,
                    'after'      => 'telephone_country_code_id',
                ],
            ]);
        }

        $indexes = $this->db->query('SHOW INDEX FROM `crm_leads`')->getResultArray();
        $hasMobileIndex = false;
        foreach ($indexes as $index) {
            if (($index['Key_name'] ?? '') === 'crm_leads_country_mobile_idx') {
                $hasMobileIndex = true;
                break;
            }
        }

        if (! $hasMobileIndex) {
            $this->forge->addKey(['clinic_id', 'country_code', 'mobile_normalized'], false, false, 'crm_leads_country_mobile_idx');
        }

        $hasCountryForeignKey = false;
        foreach ($this->db->query('SHOW CREATE TABLE `crm_leads`')->getRowArray() ?: [] as $definition) {
            if (is_string($definition) && preg_match('/FOREIGN KEY .*telephone_country_code_id/i', $definition)) {
                $hasCountryForeignKey = true;
                break;
            }
        }

        if (! $hasCountryForeignKey && $this->db->tableExists('telephone_country_codes')) {
            $this->forge->addForeignKey('telephone_country_code_id', 'telephone_country_codes', 'id', 'CASCADE', 'SET NULL');
        }

        // Forge applies pending keys when the table is altered; this is kept
        // separate from the column additions so repeated runs remain safe.
        if (! $hasMobileIndex || ! $hasCountryForeignKey) {
            $this->forge->processIndexes('crm_leads');
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('crm_leads')) {
            return;
        }

        foreach ($this->db->query('SHOW CREATE TABLE `crm_leads`')->getRowArray() ?: [] as $definition) {
            if (is_string($definition) && preg_match('/CONSTRAINT `([^`]+)` FOREIGN KEY .*telephone_country_code_id/i', $definition, $match)) {
                try {
                    $this->forge->dropForeignKey('crm_leads', $match[1]);
                } catch (\Throwable $e) {
                    // The generated constraint name can differ between MySQL versions.
                }
                break;
            }
        }

        $indexes = $this->db->query('SHOW INDEX FROM `crm_leads`')->getResultArray();
        foreach ($indexes as $index) {
            if (($index['Key_name'] ?? '') === 'crm_leads_country_mobile_idx') {
                $this->forge->dropKey('crm_leads', 'crm_leads_country_mobile_idx');
                break;
            }
        }

        foreach (['country_code', 'telephone_country_code_id'] as $field) {
            if ($this->db->fieldExists($field, 'crm_leads')) {
                $this->forge->dropColumn('crm_leads', $field);
            }
        }
    }
}
