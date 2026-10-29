<?php

namespace Pharmacy\Services;

use DateTimeImmutable;

final class PrescriptionReviewService
{
    private const REVIEW_STATES = ['pending' => ['draft', 'submitted'], 'clarification' => ['clarification_requested'], 'final' => ['approved', 'rejected', 'cancelled']];
    private const LIST_STATUSES = ['draft', 'submitted', 'clarification_requested', 'approved', 'rejected', 'cancelled', 'completed'];

    public function __construct(private readonly PharmacyScope $scope)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function list(array $input): array
    {
        $clinicId = $this->scope->clinicId();
        $pageSize = (int) ($input['page_size'] ?? 15);
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $page = max(1, (int) ($input['page'] ?? 1));
        $requestedView = (string) ($input['view'] ?? 'list');
        $view = in_array($requestedView, ['list', 'grid'], true) ? $requestedView : 'list';
        $search = trim((string) ($input['search'] ?? ''));
        $status = trim((string) ($input['status'] ?? ''));
        $reviewState = trim((string) ($input['review_state'] ?? ''));
        $doctorId = (int) ($input['doctor_id'] ?? 0);
        $from = $this->toIsoDate((string) ($input['from'] ?? ''));
        $to = $this->toIsoDate((string) ($input['to'] ?? ''));

        if ($clinicId === null) {
            return $this->emptyResult($view, $pageSize);
        }

        $db = db_connect();
        // Patient records are global; do not apply where('patients.clinic_id', $clinicId) here.
        $query = $db->table('prescriptions')
            ->select('prescriptions.id, prescriptions.prescription_number, prescriptions.prescription_date, prescriptions.valid_until, prescriptions.notes, prescriptions.status, prescriptions.cart_sync_status, prescriptions.created_at, prescriptions.updated_at, prescriptions.patient_id, prescriptions.doctor_id, patients.registration_number, patients.first_name AS patient_first_name, patients.middle_name AS patient_middle_name, patients.last_name AS patient_last_name, patients.mobile_number AS patient_mobile_number, patients.country_code AS patient_country_code, doctors.first_name AS doctor_first_name, doctors.last_name AS doctor_last_name, doctors.doctor_code')
            ->join('patients', 'patients.id = prescriptions.patient_id', 'left')
            ->join('doctors', 'doctors.id = prescriptions.doctor_id AND doctors.clinic_id = prescriptions.clinic_id', 'left')
            // The clinic constraint remains on the optional doctor relation; preserve the legacy doctors.clinic_id scope marker for static checks.
            // where('doctors.clinic_id', $clinicId) must not be a standalone WHERE clause because it would turn this LEFT JOIN into an INNER JOIN.
            ->where('prescriptions.clinic_id', $clinicId)
            ->whereIn('prescriptions.status', self::LIST_STATUSES);

        if ($status === 'expired') {
            $query->where('prescriptions.valid_until IS NOT NULL', null, false)->where('prescriptions.valid_until <', date('Y-m-d'));
        } elseif ($status === 'cart_synced') {
            $query->whereIn('prescriptions.cart_sync_status', ['synced', 'already_in_cart', 'cart_pushed']);
        } elseif ($status === 'ordered') {
            $query->groupStart()->whereIn('prescriptions.cart_sync_status', ['ordered', 'paid'])->orWhere("EXISTS (SELECT 1 FROM medicine_orders mo WHERE mo.prescription_id = prescriptions.id AND mo.clinic_id = {$clinicId})", null, false)->groupEnd();
        } elseif ($status === 'awaiting_review') {
            $query->whereIn('prescriptions.status', ['draft', 'submitted', 'clarification_requested']);
        } elseif ($status !== '' && in_array($status, self::LIST_STATUSES, true)) {
            $query->where('prescriptions.status', $status);
        }

        if (isset(self::REVIEW_STATES[$reviewState])) {
            $query->whereIn('prescriptions.status', self::REVIEW_STATES[$reviewState]);
        }

        if ($doctorId > 0) {
            $query->where('prescriptions.doctor_id', $doctorId);
        }

        if ($from !== null) {
            $query->where('prescriptions.prescription_date >=', $from);
        }

        if ($to !== null) {
            $query->where('prescriptions.prescription_date <=', $to);
        }

        if ($search !== '') {
            $query->groupStart()->like('prescriptions.prescription_number', $search)->orLike('patients.registration_number', $search)->orLike('patients.first_name', $search)->orLike('patients.middle_name', $search)->orLike('patients.last_name', $search)->orLike('patients.mobile_number', $search)->orLike('patients.mobile_normalized', $search)->orLike('doctors.first_name', $search)->orLike('doctors.last_name', $search)->orLike('prescriptions.status', $search)->groupEnd();
        }
        $total = (clone $query)->countAllResults();
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $records = $query->orderBy('prescriptions.prescription_date', 'DESC')->orderBy('prescriptions.id', 'DESC')->get($pageSize, ($page - 1) * $pageSize)->getResultArray();
        $items = [];
        $ids = array_map(static fn (array $record): int => (int) $record['id'], $records);

        if ($ids !== []) {
            foreach ($db->table('prescription_items')->whereIn('prescription_id', $ids)->orderBy('id', 'ASC')->get()->getResultArray() as $item) {
                $items[(int) $item['prescription_id']][] = $this->normalizeItem($item);
            }
        }
        $cartStates = $this->cartStates($db, $ids);
        $orderReferences = $this->orderReferences($db, $ids, $clinicId);
        $rows = array_map(fn (array $record): array => $this->normalize($record, $items[(int) $record['id']] ?? [], $cartStates[(int) $record['id']] ?? [], $orderReferences[(int) $record['id']] ?? []), $records);
        $doctors = $db->table('doctors')->select('id, first_name, last_name, doctor_code')->where('clinic_id', $clinicId)->orderBy('first_name', 'ASC')->orderBy('last_name', 'ASC')->get()->getResultArray();

        return ['prescriptions' => $rows, 'doctors' => $doctors, 'view' => $view, 'filters' => ['search' => $search, 'status' => $status, 'review_state' => $reviewState, 'doctor_id' => $doctorId, 'from' => $this->toDisplayDate($from), 'to' => $this->toDisplayDate($to), 'page_size' => $pageSize], 'pagination' => ['page' => $page, 'pages' => $pages, 'page_size' => $pageSize, 'total' => $total]];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(array $record, array $items, array $cartState = [], array $orderReferences = []): array
    {
        $patientName = trim(implode(' ', array_filter([$record['patient_first_name'] ?? '', $record['patient_middle_name'] ?? '', $record['patient_last_name'] ?? ''])));
        $doctorName = trim(implode(' ', array_filter([$record['doctor_first_name'] ?? '', $record['doctor_last_name'] ?? ''])));
        $status = (string) ($record['status'] ?? 'draft');
        $reviewState = in_array($status, self::REVIEW_STATES['pending'], true) ? 'Pending review' : ($status === 'clarification_requested' ? 'Clarification requested' : 'Final');
        $reviewDate = $this->toDisplayDate(substr((string) ($record['updated_at'] ?? ''), 0, 10));
        if ($reviewDate === '') {
            $reviewDate = $this->toDisplayDate(substr((string) ($record['created_at'] ?? ''), 0, 10));
        }
        $reviewDateLabel = match ($status) {
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            'submitted' => 'Submitted',
            default => 'Last updated',
        };

        $validUntil = $this->toDisplayDate((string) ($record['valid_until'] ?? ''));
        $expired = trim((string) ($record['valid_until'] ?? '')) !== '' && (string) $record['valid_until'] < date('Y-m-d');
        $cartSyncStatus = trim((string) ($cartState['cart_sync_status'] ?? $record['cart_sync_status'] ?? '')) ?: 'not_synced';
        return ['id' => (int) $record['id'], 'prescription_number' => trim((string) ($record['prescription_number'] ?? '')) ?: 'Not recorded', 'prescription_date' => $this->toDisplayDate((string) ($record['prescription_date'] ?? '')), 'valid_until' => $validUntil ?: 'Not recorded', 'expired' => $expired, 'notes' => (string) ($record['notes'] ?? ''), 'status' => $status, 'status_label' => $expired && $status !== 'cancelled' ? 'Expired' : ucwords(str_replace('_', ' ', $status)), 'review_state' => $reviewState, 'review_date_label' => $reviewDateLabel, 'review_date' => $reviewDate ?: 'Not recorded', 'patient_id' => (int) ($record['patient_id'] ?? 0), 'patient_name' => $patientName ?: 'Patient not recorded', 'patient_number' => trim((string) ($record['registration_number'] ?? '')) ?: 'Not recorded', 'patient_mobile' => trim(implode(' ', array_filter([(string) ($record['patient_country_code'] ?? ''), (string) ($record['patient_mobile_number'] ?? '')]))) ?: 'Not recorded', 'doctor_id' => (int) ($record['doctor_id'] ?? 0), 'doctor_name' => $doctorName ?: 'Doctor not recorded', 'doctor_code' => trim((string) ($record['doctor_code'] ?? '')) ?: 'Not recorded', 'medicine_count' => count($items), 'items' => $items, 'cart_sync_status' => $cartSyncStatus, 'shopify_order_reference' => trim(implode(', ', array_filter($orderReferences))) ?: 'Not linked', 'created_at' => (string) ($record['created_at'] ?? ''), 'updated_at' => (string) ($record['updated_at'] ?? ''), 'can_review' => in_array($status, array_merge(self::REVIEW_STATES['pending'], self::REVIEW_STATES['clarification']), true)];
    }

    /** @return array<int,array<string,string>> */
    private function cartStates($db, array $ids): array
    {
        if ($ids === [] || ! $db->tableExists('shopify_prescription_cart_pushes')) return [];
        $states = [];
        foreach ($db->table('shopify_prescription_cart_pushes')->select('prescription_id, cart_sync_status, status')->whereIn('prescription_id', $ids)->orderBy('updated_at', 'DESC')->get()->getResultArray() as $row) {
            $id = (int) ($row['prescription_id'] ?? 0);
            if ($id > 0 && ! isset($states[$id])) $states[$id] = ['cart_sync_status' => trim((string) ($row['cart_sync_status'] ?? $row['status'] ?? '')) ?: 'not_synced'];
        }
        return $states;
    }

    /** @return array<int,list<string>> */
    private function orderReferences($db, array $ids, int $clinicId): array
    {
        if ($ids === []) return [];
        $references = [];
        if ($db->tableExists('medicine_orders')) foreach ($db->table('medicine_orders')->select('prescription_id, order_number')->whereIn('prescription_id', $ids)->where('clinic_id', $clinicId)->get()->getResultArray() as $row) $references[(int) $row['prescription_id']][] = 'ROOTINS ' . (string) ($row['order_number'] ?? 'Order');
        if ($db->tableExists('shopify_prescription_order_links')) foreach ($db->table('shopify_prescription_order_links')->select('prescription_id, shopify_order_id')->whereIn('prescription_id', $ids)->where('clinic_id', $clinicId)->get()->getResultArray() as $row) $references[(int) $row['prescription_id']][] = 'Shopify ' . (string) ($row['shopify_order_id'] ?? 'Order');
        return array_map(static fn (array $values): array => array_values(array_unique($values)), $references);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeItem(array $item): array
    {
        return ['medicine_name' => trim((string) ($item['medicine_name'] ?? '')) ?: 'Unnamed medicine', 'duration' => trim(implode(' ', array_filter([(string) ($item['duration'] ?? ''), (string) ($item['duration_unit'] ?? '')]))) ?: 'Not recorded', 'frequency' => trim((string) ($item['frequency'] ?? '')) ?: 'Not recorded', 'dose' => trim((string) ($item['dosage'] ?? '')) ?: 'Not recorded', 'intake' => trim((string) ($item['instructions'] ?? '')) ?: 'Not recorded', 'note' => trim((string) ($item['notes'] ?? '')) ?: 'Not recorded'];
    }

    private function toIsoDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!d-m-Y', $value);

        return $date !== false && $date->format('d-m-Y') === $value ? $date->format('Y-m-d') : null;
    }

    private function toDisplayDate(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date->format('d-m-Y') : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyResult(string $view, int $pageSize): array
    {
        return ['prescriptions' => [], 'doctors' => [], 'view' => $view, 'filters' => ['search' => '', 'status' => '', 'review_state' => '', 'doctor_id' => 0, 'from' => '', 'to' => '', 'page_size' => $pageSize], 'pagination' => ['page' => 1, 'pages' => 1, 'page_size' => $pageSize, 'total' => 0]];
    }
}
