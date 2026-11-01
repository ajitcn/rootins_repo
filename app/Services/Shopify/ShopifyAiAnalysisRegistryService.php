<?php

namespace App\Services\Shopify;

final class ShopifyAiAnalysisRegistryService
{
    public function sync(string $type, array $row): void
    {
        $db = db_connect();
        if (! $db->tableExists('shopify_ai_analysis_records')) {
            throw new \RuntimeException('SHOPIFY_AI_ANALYSIS_REGISTRY_TABLE_MISSING');
        }
        $data = [];
        foreach (['lead_reference','store_domain','shopify_customer_id','idempotency_key','first_name','last_name','age','gender','email','mobile_number','country_code','date_of_birth','concern','health_list','questionnaire_json','status','result_json','error_code','error_message','patient_id','crm_lead_id','processed_at','created_at','updated_at'] as $field) {
            if (array_key_exists($field, $row)) $data[$field] = $row[$field];
        }
        $data['analysis_type'] = $type;
        $data['legacy_record_id'] = (int) ($row['id'] ?? 0);
        $existing = $db->table('shopify_ai_analysis_records')->where('lead_reference', (string) ($row['lead_reference'] ?? ''))->get()->getRowArray();
        try {
            if ($existing) {
                $db->table('shopify_ai_analysis_records')->where('id', (int) $existing['id'])->update($data);
            } else {
                $db->table('shopify_ai_analysis_records')->insert($data);
            }
            $stored = $db->table('shopify_ai_analysis_records')->where('lead_reference', (string) ($row['lead_reference'] ?? ''))->get()->getRowArray();
            if (! $stored) throw new \RuntimeException('SHOPIFY_AI_ANALYSIS_REGISTRY_WRITE_FAILED');
        } catch (\Throwable $exception) {
            log_message('error', 'Shopify AI analysis registry sync failed ' . json_encode([
                'analysis_type' => $type,
                'lead_reference' => (string) ($row['lead_reference'] ?? ''),
                'stage' => 'common_registry_sync',
                'exception_class' => get_class($exception),
                'exception_message' => preg_replace('/[^A-Za-z0-9_ .:-]/', '', $exception->getMessage()),
            ], JSON_UNESCAPED_SLASHES));
            throw $exception;
        }
    }
}
