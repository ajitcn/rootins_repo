<?php

namespace App\Services\Shopify;

use App\Modules\Shopify\Models\ShopifyOrderNotificationModel;
use InvalidArgumentException;

/** Pharmacist review state machine. It deliberately does not replace import/payment logic. */
final class ShopifyOrderReviewService
{
    public const LINE_STATUSES = ['mapped', 'out_of_stock', 'prescription_required', 'prescription_missing', 'prescription_invalid', 'awaiting_review', 'approved', 'rejected', 'substituted', 'refunded', 'partially_refunded', 'fulfilled', 'partially_fulfilled', 'unmapped', 'inactive'];
    public const ORDER_STATUSES = ['received', 'awaiting_review', 'awaiting_customer_action', 'partially_approved', 'approved', 'partially_fulfilled', 'fulfilled', 'partially_refunded', 'refunded', 'rejected', 'quarantined'];

    /** @return array<string,mixed> */
    public function reviewLine(int $importId, string $lineId, string $action, array $input, int $actorId, ?int $clinicId = null): array
    {
        $db = db_connect();
        $action = $this->normalizeReviewAction($action);
        $import = $this->import($importId, $clinicId);
        $lineId = trim($lineId);
        $externalLineId = trim((string) ($input['shopify_line_item_id'] ?? ''));
        // The hidden line_id is the canonical local identity. The visible
        // Shopify line ID is a fallback only; it can be formatted differently
        // by the payload/UI while the local line row remains valid.
        $line = null;
        if ($lineId !== '' && ctype_digit($lineId)) {
            $line = $db->table('shopify_order_import_lines')
                ->where('id', (int) $lineId)
                ->where('import_id', $importId)
                ->get(1)
                ->getRowArray();
        }

        $externalCandidates = array_values(array_unique(array_filter([$externalLineId, $lineId], static fn (string $value): bool => $value !== '')));
        $internalLineMatch = $line !== null;
        $externalLineMatch = false;
        if ($line === null && $externalCandidates !== []) {
            $line = $db->table('shopify_order_import_lines')
                ->where('import_id', $importId)
                ->whereIn('shopify_line_item_id', $externalCandidates)
                ->get(1)
                ->getRowArray();
            $externalLineMatch = $line !== null;

            // Shopify can deliver the same line identity as a plain numeric
            // ID or as a GraphQL GID. Resolve both forms, but only among lines
            // already belonging to this import.
            if ($line === null) {
                $candidateKeys = array_fill_keys(array_map([$this, 'normalizeLineIdentity'], $externalCandidates), true);
                foreach ($db->table('shopify_order_import_lines')
                    ->where('import_id', $importId)
                    ->get()
                    ->getResultArray() as $candidateLine) {
                    $storedKey = $this->normalizeLineIdentity((string) ($candidateLine['shopify_line_item_id'] ?? ''));
                    if ($storedKey !== '' && isset($candidateKeys[$storedKey])) {
                        $line = $candidateLine;
                        $externalLineMatch = true;
                        break;
                    }
                }
            }
        }
        if ($import === null) {
            log_message('warning', 'Shopify pharmacist import scope lookup failed ' . json_encode([
                'import_id' => $importId,
                'clinic_scope' => $clinicId,
                'failure_category' => 'SHOPIFY_ORDER_IMPORT_SCOPE_NOT_FOUND',
            ], JSON_UNESCAPED_SLASHES));
            throw new InvalidArgumentException('The Shopify order was not found in the permitted scope.');
        }
        if ($line === null) {
            $lineCount = (int) $db->table('shopify_order_import_lines')->where('import_id', $importId)->countAllResults();
            log_message('warning', 'Shopify pharmacist line scope lookup failed ' . json_encode([
                'import_id' => $importId,
                'submitted_line_id' => $lineId,
                'submitted_shopify_line_id' => $externalLineId !== '' ? hash('sha256', $externalLineId) : null,
                'import_found' => true,
                'import_clinic_id' => (int) ($import['clinic_id'] ?? 0),
                'clinic_scope' => $clinicId,
                'line_count_for_import' => $lineCount,
                'internal_line_match' => $internalLineMatch,
                'external_line_match' => $externalLineMatch,
                'failure_category' => 'SHOPIFY_ORDER_LINE_SCOPE_NOT_FOUND',
            ], JSON_UNESCAPED_SLASHES));
            throw new InvalidArgumentException('The Shopify order line was not found in the permitted scope.');
        }
        $internalLineId = (int) $line['id'];
        log_message('debug', 'Shopify pharmacist line scope resolved ' . json_encode([
            'import_id' => $importId,
            'line_id' => $internalLineId,
            'submitted_line_id_type' => $lineId !== '' && ctype_digit($lineId) ? 'numeric' : ($lineId !== '' ? 'external_or_gid' : 'empty'),
            'external_line_match' => $externalLineMatch,
            'clinic_scope' => $clinicId,
        ], JSON_UNESCAPED_SLASHES));
        $next = $this->lineState($action);
        $previous = (string) ($line['review_status'] ?: $line['status'] ?: 'awaiting_review');
        if ($previous === $next && in_array($next, ['approved', 'rejected', 'out_of_stock', 'prescription_invalid'], true)) throw new InvalidArgumentException('This pharmacist decision has already been applied.');
        // A pharmacist decision is corrective and reversible. The previous
        // review status is not a permanent business fact: stock may be
        // replenished, a prescription may be corrected, or a prior decision
        // may simply have been entered in error. Approval still validates the
        // current medicine mapping, quantity, and prescription eligibility.
        if ($next === 'approved' && (int) ($line['medicine_id'] ?? 0) <= 0) throw new InvalidArgumentException('This medicine line is not eligible for approval.');
        $substituteNote = trim((string) ($input['substitute_note'] ?? $input['reason'] ?? ''));
        if ($next === 'substituted' && $substituteNote === '') throw new InvalidArgumentException('A substitute note is required.');
        $data = ['review_status' => $next, 'review_reason' => trim((string) ($input['reason'] ?? '')) ?: null, 'reviewed_by' => $actorId > 0 ? $actorId : null, 'reviewed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')];
        if ($next === 'substituted') { $data['substitute_medicine_id'] = (int) ($input['substitute_medicine_id'] ?? 0) ?: null; $data['substitute_note'] = $substituteNote; }
        if ($next === 'approved') {
            $approvedQuantity = max(0, (int) ($input['approved_quantity'] ?? $line['quantity'] ?? 0));
            $this->validateApprovedQuantity($import, $line, $approvedQuantity);
            $data['approved_quantity'] = $approvedQuantity;
        }
        $db->transStart();
        $builder = $db->table('shopify_order_import_lines')->where('id', $internalLineId)->where('import_id', $importId);
        $builder->where('review_status', $line['review_status'] ?? null)->update($data);
        if ($db->affectedRows() < 1) throw new InvalidArgumentException('The line changed while it was being reviewed. Refresh and retry.');
        $this->recomputeOrder($importId, $import, $actorId);
        $db->transComplete();
        if (! $db->transStatus()) throw new \RuntimeException('The pharmacist decision could not be saved.');
        $this->audit($import, $line, 'line_' . $action, $previous, $next, $actorId);
        $this->queue($import, $this->notificationType($action), $actorId);
        return ['import_id' => $importId, 'line_id' => $internalLineId, 'previous_status' => $previous, 'status' => $next];
    }

    /** @return array<string,mixed> */
    public function reviewOrder(int $importId, string $action, array $input, int $actorId, ?int $clinicId = null): array
    {
        $import = $this->import($importId, $clinicId);
        if ($import === null) throw new InvalidArgumentException('The Shopify order was not found in the permitted scope.');
        $lines = db_connect()->table('shopify_order_import_lines')->where('import_id', $importId)->get()->getResultArray();
        if ($lines === []) throw new InvalidArgumentException('The order has no imported medicine lines.');
        $states = array_map(static fn (array $line): string => (string) ($line['review_status'] ?: $line['status'] ?: 'awaiting_review'), $lines);
        if ((string) ($import['review_status'] ?? '') === ($action === 'reject' ? 'rejected' : ($action === 'approve_partial' ? 'partially_approved' : 'approved'))) throw new InvalidArgumentException('This pharmacist decision has already been applied.');
        if ($action === 'approve_complete' && array_intersect($states, ['prescription_missing', 'prescription_invalid', 'out_of_stock', 'rejected', 'unmapped', 'inactive'])) throw new InvalidArgumentException('Every blocking line must be resolved before complete approval.');
        if (! in_array($action, ['approve_complete', 'approve_partial', 'reject'], true)) throw new InvalidArgumentException('This order review action is invalid.');
        $next = $action === 'reject' ? 'rejected' : ($action === 'approve_complete' ? 'approved' : 'partially_approved');
        $db = db_connect();
        $db->table('shopify_order_imports')->where('id', $importId)->update(['review_status' => $next, 'review_reason' => trim((string) ($input['reason'] ?? '')) ?: null, 'reviewed_by' => $actorId ?: null, 'reviewed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        $this->audit($import, null, 'order_' . $action, (string) ($import['review_status'] ?? 'awaiting_review'), $next, $actorId);
        $this->queue($import, $action === 'reject' ? 'full_cancellation_refund' : ($action === 'approve_partial' ? 'partial_approval' : 'order_approved'), $actorId);
        return ['import_id' => $importId, 'status' => $next];
    }

    private function normalizeReviewAction(string $action): string
    {
        $normalized = strtolower(trim($action));
        $normalized = preg_replace('/[\\s-]+/', '_', $normalized) ?? $normalized;
        return match ($normalized) {
            'approve' => 'approve',
            'reject' => 'reject',
            'out_of_stock' => 'out_of_stock',
            'request_prescription' => 'request_prescription',
            'prescription_invalid', 'invalid_prescription' => 'invalid_prescription',
            'substitute' => 'substitute',
            'refunded' => 'refunded',
            'fulfilled' => 'fulfilled',
            default => $normalized,
        };
    }

    private function lineState(string $action): string
    { return match ($this->normalizeReviewAction($action)) { 'approve' => 'approved', 'reject' => 'rejected', 'out_of_stock' => 'out_of_stock', 'request_prescription' => 'prescription_missing', 'invalid_prescription' => 'prescription_invalid', 'substitute' => 'substituted', 'refunded' => 'refunded', 'fulfilled' => 'fulfilled', default => throw new InvalidArgumentException('This line review action is invalid.') }; }

    private function notificationType(string $action): string
    { return match ($action) { 'request_prescription' => 'new_prescription_required', 'invalid_prescription' => 'prescription_rejected', 'out_of_stock' => 'medicine_unavailable', 'substitute' => 'substitute_required', 'refunded' => 'partial_refund', 'fulfilled' => 'partial_fulfilment', 'reject' => 'full_cancellation_refund', default => 'pharmacist_review_updated' }; }

    private function import(int $id, ?int $clinicId): ?array
    { $q = db_connect()->table('shopify_order_imports')->where('id', $id); if ($clinicId !== null) $q->where('clinic_id', $clinicId); return $q->get(1)->getRowArray() ?: null; }

    private function normalizeLineIdentity(string $value): string
    {
        $value = trim($value);
        if ($value === '') return '';
        if (preg_match('/(?:^|\\/)(?:LineItem|line_item)\\/(\\d+)$/', $value, $matches)) return $matches[1];
        return ctype_digit($value) ? ltrim($value, '0') ?: '0' : $value;
    }

    private function validateApprovedQuantity(array $import, array $line, int $quantity): void
    {
        if ($quantity > (int) ($line['quantity'] ?? 0)) throw new InvalidArgumentException('Approved quantity cannot exceed the Shopify ordered quantity.');
        $reference = trim((string) ($import['prescription_reference'] ?? ''));
        if ($reference === '') return;
        $db = db_connect();
        $patientId = (int) ($import['patient_id'] ?? 0);
        $clinicId = (int) ($import['clinic_id'] ?? 0);

        // Shopify-uploaded prescriptions are document reviews, not structured
        // doctor prescriptions. An accepted uploaded document authorizes the
        // order without requiring a prescriptions row or duration_days.
        if ($db->tableExists('patient_documents')) {
            $documentQuery = $db->table('patient_documents')
                ->where('source_type', 'shopify')
                ->where('patient_id', $patientId)
                ->where('clinic_id', $clinicId)
                ->where('shopify_store_domain', (string) ($import['store_domain'] ?? ''));
            if ($reference !== '') $documentQuery->where('external_upload_reference', $reference);
            $uploaded = $documentQuery->orderBy('id', 'DESC')->get(1)->getRowArray();
            if ($uploaded === null && $db->tableExists('shopify_prescription_order_links')) {
                $link = $db->table('shopify_prescription_order_links')
                    ->where('import_id', (int) ($import['id'] ?? 0))
                    ->where('patient_id', $patientId)
                    ->where('clinic_id', $clinicId)
                    ->get(1)
                    ->getRowArray();
                if ((int) ($link['document_id'] ?? 0) > 0) {
                    $uploaded = $db->table('patient_documents')
                        ->where('id', (int) $link['document_id'])
                        ->where('source_type', 'shopify')
                        ->where('patient_id', $patientId)
                        ->where('clinic_id', $clinicId)
                        ->where('shopify_store_domain', (string) ($import['store_domain'] ?? ''))
                        ->get(1)
                        ->getRowArray();
                }
            }
            if ($uploaded !== null) {
                log_message('debug', 'Shopify pharmacist uploaded prescription eligibility ' . json_encode([
                    'import_id' => (int) ($import['id'] ?? 0),
                    'document_id' => (int) ($uploaded['id'] ?? 0),
                    'upload_status' => (string) ($uploaded['upload_status'] ?? ''),
                    'patient_id' => $patientId,
                    'clinic_id' => $clinicId,
                ], JSON_UNESCAPED_SLASHES));
                if ((string) ($uploaded['upload_status'] ?? '') === 'accepted') return;
            }
        }

        $prescription = $db->table('prescriptions p')->select('p.id, p.status, p.duration_days, p.valid_until')->join('doctors d', 'd.id = p.doctor_id AND d.clinic_id = p.clinic_id AND d.status = 1')->join('clinics c', 'c.id = p.clinic_id AND c.status = 1')->where('p.prescription_number', $reference)->where('p.patient_id', $patientId)->where('p.clinic_id', $clinicId)->get(1)->getRowArray();
        if ($prescription === null || ! ShopifyPrescriptionEligibility::isOrderable($prescription)) {
            log_message('warning', 'Shopify pharmacist structured prescription eligibility failed ' . json_encode([
                'import_id' => (int) ($import['id'] ?? 0),
                'prescription_found' => $prescription !== null,
                'prescription_id' => (int) ($prescription['id'] ?? 0),
                'status' => (string) ($prescription['status'] ?? ''),
                'duration_days' => $prescription['duration_days'] ?? null,
                'valid_until' => (string) ($prescription['valid_until'] ?? ''),
                'today' => ShopifyPrescriptionEligibility::today(),
                'patient_id' => $patientId,
                'clinic_id' => $clinicId,
            ], JSON_UNESCAPED_SLASHES));
            throw new InvalidArgumentException(ShopifyPrescriptionEligibility::REASON);
        }
        $item = db_connect()->table('prescription_items')->select('quantity')->where('prescription_id', (int) $prescription['id'])->where('medicine_id', (int) ($line['medicine_id'] ?? 0))->get(1)->getRowArray();
        $prescribedQuantity = $item === null ? null : ShopifyPrescriptionQuantity::normalize($item['quantity'] ?? null);
        if ($prescribedQuantity === null || $prescribedQuantity < 1 || $quantity > $prescribedQuantity) throw new InvalidArgumentException('Approved quantity cannot exceed the prescribed quantity.');
    }

    private function recomputeOrder(int $importId, array $import, int $actorId): void
    { $states = array_column(db_connect()->table('shopify_order_import_lines')->select('review_status')->where('import_id', $importId)->get()->getResultArray(), 'review_status'); $blocking = array_intersect($states, ['prescription_missing', 'prescription_invalid']); $approved = count(array_filter($states, static fn ($s) => in_array($s, ['approved', 'substituted', 'fulfilled'], true))); $status = $blocking ? 'awaiting_customer_action' : ($approved === 0 ? 'awaiting_review' : ($approved < count($states) ? 'partially_approved' : 'approved')); db_connect()->table('shopify_order_imports')->where('id', $importId)->update(['review_status' => $status, 'reviewed_by' => $actorId ?: null, 'reviewed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]); }

    private function audit(array $import, ?array $line, string $action, string $previous, string $next, int $actorId): void
    { service('audit')->record('shopify.pharmacist_review', ['correlation_id' => bin2hex(random_bytes(12)), 'import_id' => (int) $import['id'], 'shopify_order_id' => (string) ($import['shopify_order_id'] ?? ''), 'patient_reference' => (string) ($import['patient_id'] ?? ''), 'line_id' => $line ? (int) $line['id'] : null, 'action' => $action, 'previous_status' => $previous, 'new_status' => $next, 'actor' => $actorId]); }

    private function queue(array $import, string $type, int $actorId): void
    { if (! db_connect()->tableExists('shopify_order_notifications')) return; $model = new ShopifyOrderNotificationModel(); $contacts = ['email' => (string) ($import['customer_email_snapshot'] ?? ''), 'sms' => (string) ($import['customer_mobile_snapshot'] ?? ''), 'whatsapp' => (string) ($import['customer_mobile_snapshot'] ?? '')]; foreach ($contacts as $channel => $address) { if (trim($address) === '') continue; try { $model->insert(['store_domain' => (string) ($import['store_domain'] ?? ''), 'import_id' => (int) $import['id'], 'patient_id' => (int) ($import['patient_id'] ?? 0) ?: null, 'shopify_customer_id' => (string) ($import['shopify_customer_id'] ?? ''), 'shopify_order_id' => (string) ($import['shopify_order_id'] ?? ''), 'notification_type' => $type, 'channel' => $channel, 'template_key' => 'shopify.' . $type, 'safe_payload_json' => json_encode(['order_id' => (string) ($import['shopify_order_number'] ?? $import['shopify_order_id'] ?? ''), 'recipient' => $address, 'actor_id' => $actorId], JSON_UNESCAPED_SLASHES), 'status' => 'pending', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]); } catch (\Throwable $e) { log_message('warning', 'Shopify notification outbox enqueue failed: ' . get_class($e)); } } }
}
