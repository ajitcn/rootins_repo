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
    public function reviewLine(int $importId, int $lineId, string $action, array $input, int $actorId, ?int $clinicId = null): array
    {
        $db = db_connect();
        $import = $this->import($importId, $clinicId);
        $line = $db->table('shopify_order_import_lines')->where(['id' => $lineId, 'import_id' => $importId])->get(1)->getRowArray();
        if ($import === null || $line === null) throw new InvalidArgumentException('The Shopify order line was not found in the permitted scope.');
        $next = $this->lineState($action);
        $previous = (string) ($line['review_status'] ?: $line['status'] ?: 'awaiting_review');
        if ($previous === $next && in_array($next, ['approved', 'rejected', 'out_of_stock', 'prescription_invalid'], true)) throw new InvalidArgumentException('This pharmacist decision has already been applied.');
        if ($next === 'approved' && ((int) ($line['medicine_id'] ?? 0) <= 0 || in_array($previous, ['prescription_missing', 'prescription_invalid', 'out_of_stock', 'unmapped', 'inactive'], true))) throw new InvalidArgumentException('This medicine line is not eligible for approval.');
        if ($next === 'substituted' && trim((string) ($input['substitute_note'] ?? '')) === '') throw new InvalidArgumentException('A substitute note is required.');
        $data = ['review_status' => $next, 'review_reason' => trim((string) ($input['reason'] ?? '')) ?: null, 'reviewed_by' => $actorId > 0 ? $actorId : null, 'reviewed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')];
        if ($next === 'substituted') { $data['substitute_medicine_id'] = (int) ($input['substitute_medicine_id'] ?? 0) ?: null; $data['substitute_note'] = trim((string) $input['substitute_note']); }
        if ($next === 'approved') {
            $approvedQuantity = max(0, (int) ($input['approved_quantity'] ?? $line['quantity'] ?? 0));
            $this->validateApprovedQuantity($import, $line, $approvedQuantity);
            $data['approved_quantity'] = $approvedQuantity;
        }
        $db->transStart();
        $builder = $db->table('shopify_order_import_lines')->where('id', $lineId)->where('import_id', $importId);
        $builder->where('review_status', $line['review_status'] ?? null)->update($data);
        if ($db->affectedRows() < 1) throw new InvalidArgumentException('The line changed while it was being reviewed. Refresh and retry.');
        $this->recomputeOrder($importId, $import, $actorId);
        $db->transComplete();
        if (! $db->transStatus()) throw new \RuntimeException('The pharmacist decision could not be saved.');
        $this->audit($import, $line, 'line_' . $action, $previous, $next, $actorId);
        $this->queue($import, $this->notificationType($action), $actorId);
        return ['import_id' => $importId, 'line_id' => $lineId, 'previous_status' => $previous, 'status' => $next];
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

    private function lineState(string $action): string
    { return match ($action) { 'approve' => 'approved', 'reject' => 'rejected', 'out_of_stock' => 'out_of_stock', 'request_prescription' => 'prescription_missing', 'invalid_prescription' => 'prescription_invalid', 'substitute' => 'substituted', 'refunded' => 'refunded', 'fulfilled' => 'fulfilled', default => throw new InvalidArgumentException('This line review action is invalid.') }; }

    private function notificationType(string $action): string
    { return match ($action) { 'request_prescription' => 'new_prescription_required', 'invalid_prescription' => 'prescription_rejected', 'out_of_stock' => 'medicine_unavailable', 'substitute' => 'substitute_required', 'refunded' => 'partial_refund', 'fulfilled' => 'partial_fulfilment', 'reject' => 'full_cancellation_refund', default => 'pharmacist_review_updated' }; }

    private function import(int $id, ?int $clinicId): ?array
    { $q = db_connect()->table('shopify_order_imports')->where('id', $id); if ($clinicId !== null) $q->where('clinic_id', $clinicId); return $q->get(1)->getRowArray() ?: null; }

    private function validateApprovedQuantity(array $import, array $line, int $quantity): void
    {
        if ($quantity > (int) ($line['quantity'] ?? 0)) throw new InvalidArgumentException('Approved quantity cannot exceed the Shopify ordered quantity.');
        $reference = trim((string) ($import['prescription_reference'] ?? ''));
        if ($reference === '') return;
        $prescription = db_connect()->table('prescriptions p')->select('p.id, p.status, p.valid_until')->join('doctors d', 'd.id = p.doctor_id AND d.clinic_id = p.clinic_id AND d.status = 1')->join('clinics c', 'c.id = p.clinic_id AND c.status = 1')->where('p.prescription_number', $reference)->where('p.patient_id', (int) ($import['patient_id'] ?? 0))->where('p.clinic_id', (int) ($import['clinic_id'] ?? 0))->whereIn('p.status', ShopifyPrescriptionEligibility::ORDERABLE_STATUSES)->where('p.valid_until IS NOT NULL', null, false)->where('p.valid_until >=', ShopifyPrescriptionEligibility::today())->get(1)->getRowArray();
        if ($prescription === null || ! ShopifyPrescriptionEligibility::isOrderable($prescription)) throw new InvalidArgumentException(ShopifyPrescriptionEligibility::REASON);
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
