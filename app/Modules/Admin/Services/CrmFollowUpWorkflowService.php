<?php

namespace Admin\Services;

use Admin\Models\CrmLeadFollowUpModel;
use Admin\Models\CrmLeadHistoryModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/** Transactional follow-up actions backed by the immutable CRM lead history. */
class CrmFollowUpWorkflowService
{
    private CrmLeadFollowUpModel $followUps;
    private CrmLeadHistoryModel $history;

    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
        $this->followUps = new CrmLeadFollowUpModel($this->db);
        $this->history = new CrmLeadHistoryModel($this->db);
    }

    public function clinicId(): int
    {
        return (int) ((session('user') ?? [])['clinic_id'] ?? 0);
    }

    public function details(int $id): array
    {
        $followUp = $this->scopedFollowUp($id);
        if (! $followUp) {
            throw new RuntimeException('Follow-up not found.');
        }

        $visibleFields = [
            'lead_number', 'full_name', 'mobile_display', 'service_name', 'follow_up_date', 'follow_up_time',
            'follow_up_type_id', 'follow_up_type_name', 'follow_up_status', 'assigned_user_id',
            'assigned_user_name', 'remarks', 'created_at', 'creator_name', 'updated_at', 'updater_name',
        ];

        return ['follow_up' => array_intersect_key($followUp, array_flip($visibleFields)), 'activity' => $this->activity($id, (int) $followUp['clinic_id'])];
    }

    public function addComment(int $id, array $input, int $actor): array
    {
        $followUp = $this->scopedFollowUp($id);
        if (! $followUp) {
            throw new RuntimeException(json_encode(['follow_up_id' => 'Follow-up not found in your permitted clinic scope.']));
        }
        $comment = trim((string) ($input['comment'] ?? ''));
        if ($comment === '') {
            throw new RuntimeException(json_encode(['comment' => 'Enter a comment.']));
        }
        if (mb_strlen($comment) > 2000) {
            throw new RuntimeException(json_encode(['comment' => 'Comment must not exceed 2000 characters.']));
        }

        $this->db->transBegin();
        try {
            $this->record($followUp, 'follow_up_comment_added', null, ['comment' => $comment], $actor, $comment);
            $this->commitOrFail();
            return $this->details($id);
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function reschedule(int $id, array $input, int $actor): array
    {
        $followUp = $this->scopedFollowUp($id);
        if (! $followUp) {
            throw new RuntimeException(json_encode(['follow_up_id' => 'Follow-up not found in your permitted clinic scope.']));
        }

        $date = $this->date((string) ($input['follow_up_date'] ?? ''));
        $time = trim((string) ($input['follow_up_time'] ?? ''));
        $errors = [];
        if (! $date || $date < date('Y-m-d')) {
            $errors['follow_up_date'] = 'Enter a valid current or future follow-up date.';
        }
        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time)) {
            $errors['follow_up_time'] = 'Enter a valid follow-up time.';
        }

        $typeId = (int) ($input['follow_up_type_id'] ?? $followUp['follow_up_type_id']);
        $type = $this->db->table('follow_up_types')->where(['id' => $typeId, 'status' => 1])->get()->getRowArray();
        if (! $type) {
            $errors['follow_up_type_id'] = 'Select an active follow-up type.';
        }

        $assignedId = (int) ($input['assigned_user_id'] ?? ($followUp['assigned_user_id'] ?? 0));
        $assignedQuery = $this->db->table('users u')->select('u.id')->join('doctors d', 'd.id = u.doctor_id', 'left')->where(['u.id' => $assignedId, 'u.status' => 1, 'u.deleted_at' => null]);
        if ($assignedId > 0 && $this->clinicId() > 0) {
            $assignedQuery->groupStart()->where('d.clinic_id', $this->clinicId())->orWhere('u.doctor_id', null)->groupEnd();
        }
        if ($assignedId > 0 && ! $assignedQuery->get()->getRowArray()) {
            $errors['assigned_user_id'] = 'Select an active assigned user.';
        }

        $conflict = $this->db->table('crm_lead_followups')->where('lead_id', (int) $followUp['lead_id'])->where('id !=', $id)->where('follow_up_date', $date)->where('follow_up_time', strlen($time) === 5 ? $time . ':00' : $time)->whereIn('follow_up_status', ['scheduled', 'rescheduled'])->where('deleted_at', null)->get()->getRowArray();
        if ($conflict) {
            $errors['follow_up_time'] = 'This lead already has an active follow-up at that date and time.';
        }

        $reason = trim((string) ($input['comment'] ?? $input['remarks'] ?? ''));
        if (mb_strlen($reason) > 2000) {
            $errors['comment'] = 'Reason must not exceed 2000 characters.';
        }
        if ($errors) {
            throw new RuntimeException(json_encode($errors));
        }

        $newTime = strlen($time) === 5 ? $time . ':00' : $time;
        $new = ['follow_up_date' => $date, 'follow_up_time' => $newTime, 'follow_up_type_id' => $typeId, 'assigned_user_id' => $assignedId ?: null, 'follow_up_status' => 'rescheduled', 'updated_by' => $actor];
        $this->db->transBegin();
        try {
            $this->followUps->update($id, $new);
            if ((string) $followUp['follow_up_date'] !== $date || substr((string) $followUp['follow_up_time'], 0, 8) !== $newTime) {
                $this->record($followUp, 'follow_up_rescheduled', ['follow_up_date' => $followUp['follow_up_date'], 'follow_up_time' => $followUp['follow_up_time']], ['follow_up_date' => $date, 'follow_up_time' => $newTime], $actor, $reason);
            }
            if ((string) $followUp['follow_up_status'] !== 'rescheduled') {
                $this->record($followUp, 'follow_up_status_changed', ['follow_up_status' => $followUp['follow_up_status']], ['follow_up_status' => 'rescheduled'], $actor, $reason);
            }
            if ((int) ($followUp['follow_up_type_id'] ?? 0) !== $typeId) {
                $this->record($followUp, 'follow_up_type_changed', ['follow_up_type_id' => $followUp['follow_up_type_id']], ['follow_up_type_id' => $typeId], $actor, $reason);
            }
            if ((int) ($followUp['assigned_user_id'] ?? 0) !== $assignedId) {
                $this->record($followUp, 'follow_up_assigned_user_changed', ['assigned_user_id' => $followUp['assigned_user_id']], ['assigned_user_id' => $assignedId ?: null], $actor, $reason);
            }
            $this->commitOrFail();
            return $this->details($id);
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function changeStatus(int $id, string $status, array $input, int $actor): array
    {
        $followUp = $this->scopedFollowUp($id);
        if (! $followUp) {
            throw new RuntimeException('Follow-up not found.');
        }
        $allowed = ['scheduled' => ['completed', 'rescheduled', 'cancelled', 'missed'], 'rescheduled' => ['completed', 'cancelled', 'missed'], 'completed' => [], 'cancelled' => [], 'missed' => []];
        if (! in_array($status, $allowed[$followUp['follow_up_status']] ?? [], true)) {
            throw new RuntimeException('Invalid follow-up status transition.');
        }
        $comment = trim((string) ($input['comment'] ?? $input['remarks'] ?? ''));
        if (mb_strlen($comment) > 2000) {
            throw new RuntimeException(json_encode(['comment' => 'Comment must not exceed 2000 characters.']));
        }
        $new = ['follow_up_status' => $status, 'updated_by' => $actor];
        if ($status === 'completed') {
            $new['completed_at'] = date('Y-m-d H:i:s');
            $new['completed_by'] = $actor;
        }
        $this->db->transBegin();
        try {
            $this->followUps->update($id, $new);
            $this->record($followUp, 'follow_up_status_changed', ['follow_up_status' => $followUp['follow_up_status']], ['follow_up_status' => $status], $actor, $comment);
            $this->commitOrFail();
            return $this->details($id);
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    private function scopedFollowUp(int $id): ?array
    {
        $query = $this->db->table('crm_lead_followups f')->select('f.*, l.clinic_id AS lead_clinic_id, l.lead_number, l.full_name, l.mobile_number, l.country_code, l.interested_service, srv.name AS service_name, COALESCE(l.country_code, tc.normalized_code) AS lead_country_code, ft.name AS follow_up_type_name, au.username AS assigned_user_name, cu.username AS creator_name, uu.username AS updater_name')->join('crm_leads l', 'l.id=f.lead_id')->join('admin_services srv', 'srv.id = l.interested_service AND (srv.clinic_id = l.clinic_id OR srv.clinic_id IS NULL)', 'left')->join('telephone_country_codes tc', 'tc.id=l.telephone_country_code_id', 'left')->join('follow_up_types ft', 'ft.id=f.follow_up_type_id', 'left')->join('users au', 'au.id=f.assigned_user_id', 'left')->join('users cu', 'cu.id=f.created_by', 'left')->join('users uu', 'uu.id=f.updated_by', 'left')->where('f.id', $id)->where('f.deleted_at', null);
        if ($this->clinicId() > 0) {
            $query->where('l.clinic_id', $this->clinicId());
        }
        $row = $query->get()->getRowArray();
        if ($row) {
            // Lead ownership is the scope source for installations where the
            // legacy follow-up table has no clinic_id column.
            $row['clinic_id'] = (int) ($row['lead_clinic_id'] ?? 0);
            $row['mobile_display'] = trim((string) ($row['lead_country_code'] ?? '') . ' ' . (string) ($row['mobile_number'] ?? ''));
        }
        return $row ?: null;
    }

    private function activity(int $followUpId, int $clinicId): array
    {
        return $this->db->table('crm_lead_history h')->select('h.event_type, h.previous_value, h.new_value, h.remarks, h.created_at, u.username AS actor_name')->join('users u', 'u.id=h.actor_user_id', 'left')->where(['h.follow_up_id' => $followUpId, 'h.clinic_id' => $clinicId])->orderBy('h.id', 'ASC')->get()->getResultArray();
    }

    private function record(array $followUp, string $event, ?array $previous, ?array $new, int $actor, ?string $remarks = null): void
    {
        $this->history->insert(['lead_id' => (int) $followUp['lead_id'], 'clinic_id' => (int) $followUp['clinic_id'], 'event_type' => $event, 'previous_value' => $previous ? json_encode($previous) : null, 'new_value' => $new ? json_encode($new) : null, 'remarks' => $remarks ?: null, 'follow_up_id' => (int) $followUp['id'], 'actor_user_id' => $actor, 'created_at' => date('Y-m-d H:i:s')]);
        service('audit')->record('crm.' . $event, ['lead_id' => (int) $followUp['lead_id'], 'clinic_id' => (int) $followUp['clinic_id'], 'follow_up_id' => (int) $followUp['id']]);
    }

    private function commitOrFail(): void
    {
        if (! $this->db->transStatus()) {
            throw new RuntimeException('Follow-up activity could not be saved.');
        }
        $this->db->transCommit();
    }

    private function date(string $value): ?string
    {
        return function_exists('rootins_parse_date') ? rootins_parse_date($value) : null;
    }
}
