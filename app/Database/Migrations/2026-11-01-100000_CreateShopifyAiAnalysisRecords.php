<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateShopifyAiAnalysisRecords extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('shopify_ai_analysis_records')) return;
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'analysis_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'legacy_record_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'lead_reference' => ['type' => 'VARCHAR', 'constraint' => 40],
            'store_domain' => ['type' => 'VARCHAR', 'constraint' => 191],
            'shopify_customer_id' => ['type' => 'VARCHAR', 'constraint' => 64],
            'idempotency_key' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'first_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'last_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'age' => ['type' => 'INT', 'constraint' => 3, 'null' => true],
            'gender' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'email' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'mobile_number' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'country_code' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'date_of_birth' => ['type' => 'DATE', 'null' => true],
            'concern' => ['type' => 'TEXT', 'null' => true],
            'health_list' => ['type' => 'TEXT', 'null' => true],
            'questionnaire_json' => ['type' => 'LONGTEXT', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30],
            'result_json' => ['type' => 'LONGTEXT', 'null' => true],
            'error_code' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'error_message' => ['type' => 'TEXT', 'null' => true],
            'patient_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'crm_lead_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'processed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('lead_reference');
        $this->forge->addKey(['store_domain', 'shopify_customer_id', 'analysis_type']);
        $this->forge->addKey('crm_lead_id');
        $this->forge->addForeignKey('crm_lead_id', 'crm_leads', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('shopify_ai_analysis_records', true);

        foreach ([['shopify_hair_analysis_leads', 'hair'], ['shopify_skin_analysis_leads', 'skin']] as [$table, $type]) {
            if (! $this->db->tableExists($table)) continue;
            foreach ($this->db->table($table)->get()->getResultArray() as $row) {
                $data = [];
                foreach (['lead_reference','store_domain','shopify_customer_id','idempotency_key','first_name','last_name','age','gender','email','mobile_number','country_code','date_of_birth','concern','health_list','questionnaire_json','status','result_json','error_code','error_message','patient_id','crm_lead_id','processed_at','created_at','updated_at'] as $field) {
                    if (array_key_exists($field, $row)) $data[$field] = $row[$field];
                }
                $data['analysis_type'] = $type;
                $data['legacy_record_id'] = (int) ($row['id'] ?? 0);
                $this->db->table('shopify_ai_analysis_records')->ignore(true)->insert($data);
            }
        }
        foreach ($this->db->table('shopify_ai_analysis_records')->where('crm_lead_id IS NULL', null, false)->get()->getResultArray() as $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $mobile = preg_replace('/\D+/', '', (string) ($row['mobile_number'] ?? ''));
            if ($email === '' && $mobile === '') continue;
            $leads = $this->db->table('crm_leads')->where('deleted_at', null);
            $leads->groupStart();
            if ($email !== '') $leads->where('LOWER(email)', $email, false);
            if ($email !== '' && $mobile !== '') $leads->orWhere('mobile_normalized', $mobile);
            elseif ($mobile !== '') $leads->where('mobile_normalized', $mobile);
            $lead = $leads->groupEnd()->orderBy('id', 'ASC')->get()->getRowArray();
            if (! $lead) continue;
            $leadId = (int) $lead['id'];
            $this->db->table('shopify_ai_analysis_records')->where('id', (int) $row['id'])->update(['crm_lead_id' => $leadId]);
            $legacyTable = ($row['analysis_type'] ?? '') === 'skin' ? 'shopify_skin_analysis_leads' : 'shopify_hair_analysis_leads';
            if ($this->db->tableExists($legacyTable) && $this->db->fieldExists('crm_lead_id', $legacyTable)) {
                $this->db->table($legacyTable)->where('id', (int) ($row['legacy_record_id'] ?? 0))->update(['crm_lead_id' => $leadId]);
            }
        }
        foreach ($this->db->table('shopify_ai_analysis_records')->where('crm_lead_id IS NOT NULL', null, false)->get()->getResultArray() as $row) {
            $crmLeadId = (int) ($row['crm_lead_id'] ?? 0);
            if ($crmLeadId < 1) continue;
            $lead = $this->db->table('crm_leads')->select('mobile_number,email,country_code')->where('id', $crmLeadId)->get()->getRowArray();
            if (! $lead) continue;
            $mobile = trim((string) ($row['mobile_number'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            $updates = [];
            if (($lead['mobile_number'] ?? '') === '' && $mobile !== '') {
                $updates['mobile_number'] = $mobile;
                $updates['mobile_normalized'] = preg_replace('/\D+/', '', $mobile) ?: null;
            }
            if (($lead['email'] ?? '') === '' && $email !== '') $updates['email'] = $email;
            if (($lead['country_code'] ?? '') === '' && trim((string) ($row['country_code'] ?? '')) !== '') $updates['country_code'] = trim((string) $row['country_code']);
            if ($updates !== []) $this->db->table('crm_leads')->where('id', $crmLeadId)->update($updates);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('shopify_ai_analysis_records', true);
    }
}
