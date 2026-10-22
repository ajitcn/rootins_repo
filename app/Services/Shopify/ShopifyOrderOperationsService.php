<?php

namespace App\Services\Shopify;

use Admin\Models\MedicineModel;
use Admin\Models\PatientModel;
use App\Modules\Shopify\Models\ShopifyMedicineMappingModel;
use App\Modules\Shopify\Models\ShopifyOrderImportLineModel;
use App\Modules\Shopify\Models\ShopifyOrderImportModel;
use App\Modules\Shopify\Models\ShopifyPatientIdentityLinkModel;
use InvalidArgumentException;

/** Admin-only operations surface for Shopify order reconciliation. */
final class ShopifyOrderOperationsService
{
    public function mappings(array $filters = []): array
    {
        $query = db_connect()->table('shopify_medicine_mappings m')
            ->select('m.*, med.medicine_name, med.generic_name, med.strength, med.unit, med.status AS medicine_status')
            ->join('medicines med', 'med.id = m.medicine_id', 'left')
            ->orderBy('m.updated_at', 'DESC');
        $this->like($query, 'm.shopify_product_title', $filters['product'] ?? '');
        $this->like($query, 'm.shopify_variant_title', $filters['variant'] ?? '');
        $this->like($query, 'm.shopify_sku', $filters['sku'] ?? '');
        $this->like($query, 'med.medicine_name', $filters['medicine'] ?? '');
        if (in_array(($filters['status'] ?? ''), ['active', 'inactive'], true)) $query->where('m.status', $filters['status']);

        return $query->get(250)->getResultArray();
    }

    public function mapping(int $id): ?array
    {
        return (new ShopifyMedicineMappingModel())->find($id) ?: null;
    }

    public function saveMapping(array $input, ?int $id = null): array
    {
        $store = strtolower(trim((string) ($input['store_domain'] ?? (new ShopifyConfiguration())->storeDomain())));
        $productId = trim((string) ($input['shopify_product_id'] ?? ''));
        $variantId = trim((string) ($input['shopify_variant_id'] ?? ''));
        $medicineId = (int) ($input['medicine_id'] ?? 0);
        if ($store === '' || $productId === '' || $variantId === '' || $medicineId < 1) throw new InvalidArgumentException('Store, product, variant, and an active ROOTINS medicine are required.');
        $medicine = (new MedicineModel())->where('id', $medicineId)->where('status', 'active')->first();
        if ($medicine === null) throw new InvalidArgumentException('The selected ROOTINS medicine does not exist or is inactive.');

        $model = new ShopifyMedicineMappingModel();
        $duplicate = $model->where('store_domain', $store)->where('shopify_product_id', $productId)->where('shopify_variant_id', $variantId);
        if ($id !== null) $duplicate->where('id !=', $id);
        if ($duplicate->first() !== null) throw new InvalidArgumentException('This Shopify product/variant is already mapped.');

        $data = [
            'store_domain' => $store,
            'shopify_product_id' => $productId,
            'shopify_variant_id' => $variantId,
            'shopify_product_title' => trim((string) ($input['shopify_product_title'] ?? '')) ?: null,
            'shopify_variant_title' => trim((string) ($input['shopify_variant_title'] ?? '')) ?: null,
            'shopify_sku' => trim((string) ($input['shopify_sku'] ?? '')) ?: null,
            'medicine_id' => $medicineId,
            'status' => ($input['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($id === null) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $model->insert($data);
            $id = (int) $model->getInsertID();
            $event = 'shopify.order_mapping_created';
        } else {
            if ($model->find($id) === null || ! $model->update($id, $data)) throw new InvalidArgumentException('The Shopify mapping could not be updated.');
            $event = 'shopify.order_mapping_updated';
        }
        service('audit')->record($event, ['mapping_id' => $id, 'store_domain' => $store, 'product_id' => $productId, 'variant_id' => $variantId, 'medicine_id' => $medicineId]);
        return $model->find($id) ?? $data + ['id' => $id];
    }

    public function toggleMapping(int $id): bool
    {
        $model = new ShopifyMedicineMappingModel();
        $mapping = $model->find($id);
        if ($mapping === null) throw new InvalidArgumentException('Mapping not found.');
        $status = ($mapping['status'] ?? '') === 'active' ? 'inactive' : 'active';
        if ($status === 'active' && (new MedicineModel())->where('id', (int) $mapping['medicine_id'])->where('status', 'active')->first() === null) throw new InvalidArgumentException('An inactive medicine cannot be activated in a mapping.');
        $saved = $model->update($id, ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
        if ($saved) service('audit')->record('shopify.order_mapping_status_changed', ['mapping_id' => $id, 'status' => $status]);
        return (bool) $saved;
    }

    public function activeMedicines(): array
    {
        return (new MedicineModel())->select('id, medicine_name, generic_name, strength, unit')->where('status', 'active')->orderBy('medicine_name', 'ASC')->findAll(500);
    }

    public function readiness(array $filters = []): array
    {
        $query = db_connect()->table('shopify_patient_identity_links l')
            ->select('l.*, p.registration_number, p.first_name, p.last_name, p.email AS patient_email, p.mobile_number, p.status AS patient_status, p.date_of_birth')
            ->join('patients p', 'p.id = l.patient_id', 'left')->orderBy('l.updated_at', 'DESC');
        if (($filters['store'] ?? '') !== '') $query->where('l.shopify_store_domain', strtolower(trim((string) $filters['store'])));
        if (($filters['status'] ?? '') !== '') $query->where('l.link_status', trim((string) $filters['status']));
        $rows = $query->get(500)->getResultArray();
        foreach ($rows as &$row) {
            $missing = [];
            foreach (['first_name' => 'first name', 'last_name' => 'last name', 'date_of_birth' => 'date of birth', 'mobile_number' => 'mobile'] as $field => $label) if (trim((string) ($row[$field] ?? '')) === '') $missing[] = $label;
            $row['missing_fields'] = $missing;
            $row['readiness_status'] = $this->readinessStatus($row);
            $row['patient_public_reference'] = (string) ($row['registration_number'] ?? '');
        }
        unset($row);
        return $rows;
    }

    public function queue(array $filters = []): array
    {
        $query = db_connect()->table('shopify_order_imports i')
            ->select('i.*, p.registration_number, p.first_name, p.last_name, p.status AS rootins_patient_status, l.link_status')
            ->join('patients p', 'p.id = i.patient_id', 'left')
            ->join('shopify_patient_identity_links l', 'l.shopify_store_domain = i.store_domain AND l.shopify_customer_id = i.shopify_customer_id', 'left')
            ->orderBy('i.updated_at', 'DESC');
        foreach (['store_domain' => 'i.store_domain', 'status' => 'i.status', 'prescription_status' => 'i.prescription_status', 'mapping_status' => 'i.mapping_status', 'financial_status' => 'i.financial_status', 'fulfillment_status' => 'i.fulfillment_status', 'patient_status' => 'i.patient_status'] as $key => $column) if (($filters[$key] ?? '') !== '') $query->where($column, trim((string) $filters[$key]));
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') $query->groupStart()->like('i.shopify_order_number', $search)->orLike('i.shopify_order_id', $search)->orLike('i.customer_name_snapshot', $search)->orLike('i.customer_email_snapshot', $search)->orLike('p.registration_number', $search)->orLike('i.shopify_customer_id', $search)->groupEnd();
        return $query->get(500)->getResultArray();
    }

    public function counters(): array
    {
        $db = db_connect();
        $today = date('Y-m-d');
        $result = [];
        foreach (['orders_received_today' => ['created_at >=' => $today], 'orders_awaiting_patient' => ['patient_status' => 'missing'], 'orders_awaiting_prescription_review' => ['prescription_status' => 'awaiting_review'], 'orders_with_unmapped_medicines' => ['mapping_status' => 'unmapped'], 'orders_ready_to_import' => ['status' => 'ready_to_import'], 'failed_imports' => ['status' => 'failed'], 'quarantined_orders' => ['status' => 'quarantined'], 'successfully_imported' => ['status' => 'imported'], 'cancelled_refunded_orders' => ['status' => 'cancelled']] as $name => $conditions) { $q = $db->table('shopify_order_imports'); foreach ($conditions as $field => $value) $q->where($field, $value); $result[$name] = $q->countAllResults(); }
        return $result;
    }

    public function detail(int $id): ?array
    {
        $import = db_connect()->table('shopify_order_imports i')->select('i.*, p.registration_number, p.first_name, p.last_name, p.email, p.mobile_number, p.status AS rootins_patient_status, l.link_status')->join('patients p', 'p.id = i.patient_id', 'left')->join('shopify_patient_identity_links l', 'l.shopify_store_domain = i.store_domain AND l.shopify_customer_id = i.shopify_customer_id', 'left')->where('i.id', $id)->get(1)->getRowArray();
        if ($import === null) return null;
        $lines = db_connect()->table('shopify_order_import_lines l')->select('l.*, m.medicine_name, m.strength, m.unit')->join('medicines m', 'm.id = l.medicine_id', 'left')->where('l.import_id', $id)->orderBy('l.id', 'ASC')->get()->getResultArray();
        $history = db_connect()->table('shopify_order_import_history')->where('import_id', $id)->orderBy('created_at', 'DESC')->get()->getResultArray();
        $event = ! empty($import['last_event_id']) ? (new ShopifyWebhookEventService())->find((int) $import['last_event_id']) : null;
        $webhookHistory = db_connect()->table('shopify_webhook_events')->select('id, external_event_id, topic, status, error_message, retry_count, created_at, updated_at')->where('store_domain', $import['store_domain'])->like('payload_json', (string) $import['shopify_order_id'])->orderBy('created_at', 'DESC')->get(100)->getResultArray();
        $auditHistory = db_connect()->table('audit_logs')->select('id, action, route, occurred_at, context')->like('context', '"import_id":' . $id)->orderBy('occurred_at', 'DESC')->get(100)->getResultArray();
        $prescription = ! empty($import['prescription_reference']) ? db_connect()->table('patient_documents')->select('id')->where('source_type', 'shopify')->where('external_upload_reference', $import['prescription_reference'])->where('shopify_store_domain', $import['store_domain'])->where('shopify_customer_id', $import['shopify_customer_id'])->get(1)->getRowArray() : null;
        $import['prescription_document_id'] = $prescription['id'] ?? null;
        $import['lines'] = $lines; $import['history'] = $history; $import['last_webhook'] = $event; $import['webhook_history'] = $webhookHistory; $import['audit_history'] = $auditHistory;
        return $import;
    }

    public function action(int $id, string $action, array $input = [], int $actor = 0): array
    {
        $import = (new ShopifyOrderImportModel())->find($id);
        if ($import === null) throw new InvalidArgumentException('Shopify order was not found.');
        $old = (string) ($import['status'] ?? '');
        $model = new ShopifyOrderImportModel();
        if ($action === 'reprocess_paid_appointment') {
            $result = (new ShopifyAppointmentIntentService())->reprocessPaidOrder((string) $import['store_domain'], (string) $import['shopify_order_id']);
            service('audit')->record('shopify.order_operation_reprocess_paid_appointment', ['import_id' => $id, 'shopify_order_id' => (string) $import['shopify_order_id']]);
            return $result;
        }
        if ($action === 'retry_validation' || $action === 'retry_import' || $action === 'reprocess_webhook') {
            $result = (new ShopifyOrderImportService())->importExisting($id, null);
            $this->history($id, $action, $old, (string) ($model->find($id)['status'] ?? ''), null, (string) ($result['body']['msg'] ?? ''), $actor);
            service('audit')->record('shopify.order_operation_' . $action, ['import_id' => $id]);
            return $result;
        }
        if (in_array($action, ['quarantine', 'release_quarantine', 'mark_ready'], true)) {
            $status = $action === 'quarantine' ? 'quarantined' : ($action === 'mark_ready' ? 'ready_to_import' : 'received');
            $reason = trim((string) ($input['reason'] ?? '')) ?: null;
            $model->update($id, ['status' => $status, 'quarantine_reason' => $reason, 'failure_reason' => $reason, 'updated_at' => date('Y-m-d H:i:s')]);
            $this->history($id, $action, $old, $status, $reason, null, $actor);
            service('audit')->record('shopify.order_operation_' . $action, ['import_id' => $id, 'reason' => $reason]);
            return ['status' => $status, 'import_id' => $id];
        }
        if ($action === 'select_patient') {
            $linkId = (int) ($input['link_id'] ?? 0); $patientId = (int) ($input['patient_id'] ?? 0);
            $link = (new ShopifyPatientIdentityLinkModel())->where('id', $linkId)->where('shopify_store_domain', $import['store_domain'])->where('shopify_customer_id', $import['shopify_customer_id'])->first();
            $patient = (new PatientModel())->where('id', $patientId)->where('status', 1)->first();
            if ($link === null || $patient === null) throw new InvalidArgumentException('The selected patient identity is invalid.');
            (new ShopifyPatientIdentityLinkModel())->update($linkId, ['patient_id' => $patientId, 'link_status' => 'linked', 'matched_by' => 'admin', 'active_patient_key' => 'shopify:' . $patientId, 'linked_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
            (new ShopifyOrderImportModel())->update($id, ['patient_id' => $patientId, 'patient_status' => 'linked', 'status' => 'received', 'updated_at' => date('Y-m-d H:i:s')]);
            $this->history($id, $action, $old, 'received', null, null, $actor); service('audit')->record('shopify.order_patient_selected', ['import_id' => $id]); return ['status' => 'received', 'import_id' => $id];
        }
        if ($action === 'select_prescription') { return (new ShopifyOrderImportService())->importExisting($id, (int) ($input['prescription_id'] ?? 0)); }
        if ($action === 'map_line') {
            $line = (new ShopifyOrderImportLineModel())->where('id', (int) ($input['line_id'] ?? 0))->where('import_id', $id)->first();
            if ($line === null) throw new InvalidArgumentException('The selected Shopify line is invalid.');
            $mapping = (new ShopifyMedicineMappingModel())->where('id', (int) ($input['mapping_id'] ?? 0))->where('store_domain', $import['store_domain'])->where('status', 'active')->first();
            if ($mapping === null) throw new InvalidArgumentException('The selected medicine mapping is invalid or inactive.');
            (new ShopifyOrderImportLineModel())->update((int) $line['id'], ['medicine_id' => (int) $mapping['medicine_id'], 'status' => 'mapped', 'updated_at' => date('Y-m-d H:i:s')]);
            (new ShopifyOrderImportModel())->update($id, ['mapping_status' => 'mapped', 'status' => 'received', 'updated_at' => date('Y-m-d H:i:s')]);
            $this->history($id, $action, $old, 'received', null, null, $actor); service('audit')->record('shopify.order_line_mapped', ['import_id' => $id, 'line_id' => (int) $line['id']]); return ['status' => 'received', 'import_id' => $id];
        }
        if ($action === 'cancel_import') {
            if (in_array($old, ['imported', 'completed'], true)) throw new InvalidArgumentException('An imported order cannot be cancelled from reconciliation.');
            $model->update($id, ['status' => 'cancelled', 'quarantine_reason' => 'manual_cancelled', 'updated_at' => date('Y-m-d H:i:s')]); $this->history($id, $action, $old, 'cancelled', 'Manual cancellation', null, $actor); service('audit')->record('shopify.order_import_cancelled', ['import_id' => $id]); return ['status' => 'cancelled', 'import_id' => $id];
        }
        throw new InvalidArgumentException('Unsupported Shopify order operation.');
    }

    private function readinessStatus(array $row): string
    {
        if (in_array((string) ($row['link_status'] ?? ''), ['ambiguous', 'conflict'], true)) return 'Cross-store conflict';
        if ((string) ($row['patient_status'] ?? '') === '0' || (int) ($row['patient_status'] ?? 0) === 0 && ! empty($row['patient_id'])) return 'Inactive';
        if (($row['link_status'] ?? '') === 'linked' && empty($row['missing_fields'])) return 'Linked';
        if (($row['link_status'] ?? '') === 'linked') return 'Incomplete';
        return 'Not registered';
    }

    private function history(int $id, string $action, string $from, string $to, ?string $reason, ?string $error, int $actor): void
    { if (db_connect()->tableExists('shopify_order_import_history')) db_connect()->table('shopify_order_import_history')->insert(['import_id' => $id, 'action' => $action, 'from_status' => $from ?: null, 'to_status' => $to ?: null, 'reason' => $reason, 'error_message' => $error, 'actor_user_id' => $actor > 0 ? $actor : null, 'created_at' => date('Y-m-d H:i:s')]); }

    private function like(object $query, string $column, mixed $value): void { if (trim((string) $value) !== '') $query->like($column, trim((string) $value)); }
}
