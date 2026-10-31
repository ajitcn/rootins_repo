<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddShopifyHairAnalysisLeadPatientFields extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('shopify_hair_analysis_leads')) return;
        $fields = $this->db->getFieldNames('shopify_hair_analysis_leads');
        $add = [];
        if (! in_array('last_name', $fields, true)) $add['last_name'] = ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true];
        if (! in_array('email', $fields, true)) $add['email'] = ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true];
        if (! in_array('mobile_number', $fields, true)) $add['mobile_number'] = ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true];
        if (! in_array('country_code', $fields, true)) $add['country_code'] = ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true];
        if (! in_array('date_of_birth', $fields, true)) $add['date_of_birth'] = ['type' => 'DATE', 'null' => true];
        if ($add !== []) $this->forge->addColumn('shopify_hair_analysis_leads', $add);
    }

    public function down(): void
    {
        if (! $this->db->tableExists('shopify_hair_analysis_leads')) return;
        foreach (['last_name', 'email', 'mobile_number', 'country_code', 'date_of_birth'] as $field) {
            if (in_array($field, $this->db->getFieldNames('shopify_hair_analysis_leads'), true)) $this->forge->dropColumn('shopify_hair_analysis_leads', $field);
        }
    }
}
