<?php

namespace App\Services\Crm;

final class CrmAiAnalysisReferenceService
{
    public function forLead(int $leadId): array
    {
        $db = db_connect();
        $result = [];
        foreach ([['shopify_hair_analysis_leads', 'hair', 'shopify_hair_analysis_images'], ['shopify_skin_analysis_leads', 'skin', 'shopify_skin_analysis_images']] as [$table, $type, $imageTable]) {
            if (! $db->tableExists($table)) continue;
            foreach ($db->table($table)->where('crm_lead_id', $leadId)->orderBy('id', 'DESC')->get()->getResultArray() as $row) {
                $questionnaire = json_decode((string) ($row['questionnaire_json'] ?? ''), true);
                $analysis = json_decode((string) ($row['result_json'] ?? ''), true);
                $result[] = [
                    'type' => $type,
                    'reference' => (string) ($row['lead_reference'] ?? ''),
                    'status' => (string) ($row['status'] ?? ''),
                    'first_name' => $row['first_name'] ?? null,
                    'last_name' => $row['last_name'] ?? null,
                    'age' => $row['age'] ?? null,
                    'gender' => $row['gender'] ?? null,
                    'email' => $row['email'] ?? null,
                    'mobile_number' => $row['mobile_number'] ?? null,
                    'country_code' => $row['country_code'] ?? null,
                    'concern' => $row['concern'] ?? null,
                    'health_list' => $row['health_list'] ?? null,
                    'questionnaire' => is_array($questionnaire) ? $questionnaire : [],
                    'result' => is_array($analysis) ? $analysis : null,
                    'image_count' => $db->table($imageTable)->where('lead_id', (int) $row['id'])->countAllResults(),
                    'created_at' => $row['created_at'] ?? null,
                    'processed_at' => $row['processed_at'] ?? null,
                    'detail_url' => base_url('admin/integrations/shopify/ai-' . $type . '-analysis/leads/' . (int) $row['id']),
                ];
            }
        }
        return $result;
    }
}
