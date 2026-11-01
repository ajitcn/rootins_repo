<?php

namespace App\Services\Shopify;

final class ShopifyAiAnalysisRegistryService
{
    public function sync(string $type, array $row): void
    {
        $db = db_connect();
        if (! $db->tableExists('shopify_ai_analysis_records')) return;
        $data = [];
        foreach (['lead_reference','store_domain','shopify_customer_id','idempotency_key','first_name','last_name','age','gender','email','mobile_number','country_code','date_of_birth','concern','health_list','questionnaire_json','status','result_json','error_code','error_message','patient_id','crm_lead_id','processed_at','created_at','updated_at'] as $field) {
            if (array_key_exists($field, $row)) $data[$field] = $row[$field];
        }
        $data['analysis_type'] = $type;
        $data['legacy_record_id'] = (int) ($row['id'] ?? 0);
        $existing = $db->table('shopify_ai_analysis_records')->where('lead_reference', (string) ($row['lead_reference'] ?? ''))->get()->getRowArray();
        if ($existing) {
            $db->table('shopify_ai_analysis_records')->where('id', (int) $existing['id'])->update($data);
        } else {
            $db->table('shopify_ai_analysis_records')->insert($data);
        }
    }
}
