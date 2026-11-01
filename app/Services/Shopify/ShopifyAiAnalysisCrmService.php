<?php

namespace App\Services\Shopify;

use RuntimeException;

final class ShopifyAiAnalysisCrmService
{
    public function ensureLead(string $type, array $lead): int
    {
        if (! empty($lead['crm_lead_id'])) {
            $leadId = (int) $lead['crm_lead_id'];
            if ($this->dbLeadExists($leadId)) {
                $this->syncLeadProfile($leadId, $lead);
                return $leadId;
            }
        }

        $db = db_connect();
        $status = $db->table('crm_lead_statuses')->where('code', 'new')->get()->getRowArray();
        $source = $db->table('crm_lead_sources')->where('code', 'other')->get()->getRowArray();
        if (! $status || ! $source) {
            throw new RuntimeException('CRM_LEAD_MASTER_DATA_MISSING');
        }

        $email = trim((string) ($lead['email'] ?? ''));
        $mobile = trim((string) ($lead['mobile_number'] ?? ''));
        $normalized = $mobile !== '' ? preg_replace('/\D+/', '', $mobile) : null;
        if ($email !== '' || $normalized !== null) {
            $query = $db->table('crm_leads')->where('deleted_at', null)->groupStart();
            if ($email !== '') $query->where('email', $email);
            if ($email !== '' && $normalized !== null) $query->orWhere('mobile_normalized', $normalized);
            elseif ($normalized !== null) $query->where('mobile_normalized', $normalized);
            $existing = $query->groupEnd()->orderBy('id', 'ASC')->get()->getRowArray();
            if ($existing) {
                $this->syncLeadProfile((int) $existing['id'], $lead);
                return (int) $existing['id'];
            }
        }

        $name = trim(trim((string) ($lead['first_name'] ?? '')) . ' ' . trim((string) ($lead['last_name'] ?? '')));
        $name = $name !== '' ? $name : 'Shopify customer ' . (string) ($lead['shopify_customer_id'] ?? '');
        $now = date('Y-m-d H:i:s');
        $id = $db->table('crm_leads')->insert([
            'lead_number' => 'TEMP-' . bin2hex(random_bytes(5)), 'clinic_id' => null, 'full_name' => $name,
            'mobile_number' => $mobile ?: null, 'mobile_normalized' => $normalized, 'country_code' => trim((string) ($lead['country_code'] ?? '')) ?: null,
            'email' => $email ?: null, 'gender' => trim((string) ($lead['gender'] ?? '')) ?: null, 'date_of_birth' => $lead['date_of_birth'] ?? null,
            'source_id' => (int) $source['id'], 'interested_service' => 'AI ' . ucfirst($type) . ' Analysis', 'lead_status_id' => (int) $status['id'],
            'priority' => 'high', 'remarks' => 'AI ' . ucfirst($type) . ' Analysis lead from Shopify.', 'tags' => '#AI ' . ucfirst($type) . ' Analysis, #Important', 'created_at' => $now, 'updated_at' => $now,
        ], true);
        if (! $id) {
            throw new RuntimeException('CRM_LEAD_CREATE_FAILED');
        }
        $db->table('crm_leads')->where('id', (int) $id)->update(['lead_number' => 'LEAD-' . str_pad((string) $id, 6, '0', '0')]);
        service('audit')->record('shopify.ai_analysis_crm_lead_created', ['crm_lead_id' => (int) $id, 'analysis_type' => $type, 'lead_reference' => $lead['lead_reference'] ?? null]);
        return (int) $id;
    }

    private function dbLeadExists(int $id): bool
    {
        return (bool) $this->db->table('crm_leads')->select('id')->where('id', $id)->where('deleted_at', null)->get()->getRowArray();
    }

    private function syncLeadProfile(int $id, array $lead): void
    {
        $profile = [];
        $first = trim((string) ($lead['first_name'] ?? ''));
        $last = trim((string) ($lead['last_name'] ?? ''));
        $name = trim($first . ' ' . $last);
        $mobile = trim((string) ($lead['mobile_number'] ?? ''));
        $email = trim((string) ($lead['email'] ?? ''));
        if ($name !== '') $profile['full_name'] = $name;
        if ($mobile !== '') { $profile['mobile_number'] = $mobile; $profile['mobile_normalized'] = preg_replace('/\\D+/', '', $mobile) ?: null; }
        if ($email !== '') $profile['email'] = $email;
        foreach (['country_code', 'gender', 'date_of_birth'] as $field) {
            $value = trim((string) ($lead[$field] ?? ''));
            if ($value !== '') $profile[$field] = $value;
        }
        if ($profile !== []) {
            $profile['updated_at'] = date('Y-m-d H:i:s');
            $this->db->table('crm_leads')->where('id', $id)->update($profile);
        }
    }

    public function createCallbackFollowUp(int $crmLeadId, string $type, ?string $preferred, ?string $note, ?string $preferredDate = null, ?string $preferredSlot = null, ?string $preferredSlotLabel = null): int
    {
        $db = db_connect();
        $schema = [
            'callback_crm_lead_id' => $db->fieldExists('crm_lead_id', 'shopify_ai_analysis_callback_requests'),
            'callback_follow_up_id' => $db->fieldExists('follow_up_id', 'shopify_ai_analysis_callback_requests'),
            'callback_preferred_date' => $db->fieldExists('preferred_date', 'shopify_ai_analysis_callback_requests'),
            'callback_preferred_slot' => $db->fieldExists('preferred_slot', 'shopify_ai_analysis_callback_requests'),
            'callback_preferred_slot_label' => $db->fieldExists('preferred_slot_label', 'shopify_ai_analysis_callback_requests'),
            'follow_up_priority' => $db->fieldExists('priority', 'crm_lead_followups'),
        ];
        if (in_array(false, $schema, true)) {
            log_message('error', 'Shopify AI callback schema incomplete ' . json_encode(['category' => 'AI_ANALYSIS_CRM_LINKAGE_MIGRATION_REQUIRED', 'schema' => $schema], JSON_UNESCAPED_SLASHES));
            throw new RuntimeException('AI_ANALYSIS_CRM_LINKAGE_MIGRATION_REQUIRED');
        }

        $clinic = $db->table('clinics')->select('id')->where('status', 1)->orderBy('id', 'ASC')->get()->getRow('id');
        if (! $clinic) {
            $clinic = $db->table('clinics')->select('id')->orderBy('id', 'ASC')->get()->getRow('id');
        }
        if (! $clinic) {
            throw new RuntimeException('CRM_CLINIC_NOT_CONFIGURED');
        }
        $typeRows = $db->table('follow_up_types')->where('status', 1)->orderBy('id', 'ASC')->get()->getResultArray();
        $typeRow = null;
        foreach ($typeRows as $row) {
            $code = strtolower(trim((string) ($row['code'] ?? '')));
            $name = strtolower(trim((string) ($row['normalized_name'] ?? $row['name'] ?? '')));
            if ($code === 'callback_request' || $name === 'callback request' || $name === 'other') { $typeRow = $row; break; }
        }
        $statusRows = $db->table('follow_up_statuses')->where('status', 1)->orderBy('id', 'ASC')->get()->getResultArray();
        $status = null;
        foreach ($statusRows as $row) {
            $code = strtolower(trim((string) ($row['code'] ?? $row['normalized_code'] ?? $row['name'] ?? '')));
            if ($code === 'scheduled') { $status = $row; break; }
        }
        if (! $typeRow && $typeRows !== []) {
            $typeRow = $typeRows[0];
        }
        if (! $status && $statusRows !== []) {
            $status = $statusRows[0];
        }
        if (! $typeRow || ! $status) {
            throw new RuntimeException('CRM_FOLLOWUP_MASTER_DATA_MISSING');
        }

        $followUpDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $preferredDate) ? $preferredDate : date('Y-m-d');
        $followUpTime = $this->slotStartTime($preferredSlot) ?? $this->slotStartTime($preferred);
        $followUpTime = $followUpTime ?? date('H:i:s');
        $remarks = 'HIGH PRIORITY AI ' . strtoupper($type) . ' CALLBACK REQUEST.';
        $remarks .= ' Preferred date: ' . $followUpDate . '.';
        if ($preferredSlotLabel) $remarks .= ' Preferred slot: ' . $preferredSlotLabel . '.';
        if ($preferredSlot) $remarks .= ' Slot value: ' . $preferredSlot . '.';
        if ($preferred) $remarks .= ' Preferred time: ' . $preferred . '.';
        if ($note) $remarks .= ' Customer note: ' . $note;

        $now = date('Y-m-d H:i:s');
        $followUpData = [
            'follow_up_number' => 'TEMP-' . bin2hex(random_bytes(5)), 'lead_id' => $crmLeadId, 'clinic_id' => (int) $clinic, 'patient_id' => null,
            'follow_up_date' => $followUpDate, 'follow_up_time' => $followUpTime, 'follow_up_type_id' => (int) $typeRow['id'],
            'follow_up_status' => (string) ($status['code'] ?? $status['normalized_code'] ?? 'scheduled'), 'priority' => 'high', 'remarks' => $remarks, 'tags' => '#AI ' . ucfirst($type) . ' Analysis, #Important',
            'created_at' => $now, 'updated_at' => $now,
        ];
        $followUpFields = $db->getFieldNames('crm_lead_followups');
        $followUpData = array_intersect_key($followUpData, array_flip($followUpFields));
        $id = $db->table('crm_lead_followups')->insert($followUpData, true);
        if (! $id) throw new RuntimeException('CRM_FOLLOWUP_CREATE_FAILED');
        $db->table('crm_lead_followups')->where('id', (int) $id)->update(['follow_up_number' => 'FUP-' . str_pad((string) $id, 6, '0', '0')]);
        service('audit')->record('shopify.ai_analysis_callback_followup_created', ['follow_up_id' => (int) $id, 'crm_lead_id' => $crmLeadId, 'priority' => 'high', 'follow_up_date' => $followUpDate, 'follow_up_time' => $followUpTime]);
        return (int) $id;
    }

    private function slotStartTime(?string $value): ?string
    {
        if (! $value || ! preg_match('/(?:^|\s)(\d{1,2}):(\d{2})/', $value, $match)) return null;
        $hour = (int) $match[1]; $minute = (int) $match[2];
        return $hour > 23 || $minute > 59 ? null : sprintf('%02d:%02d:00', $hour, $minute);
    }
}
