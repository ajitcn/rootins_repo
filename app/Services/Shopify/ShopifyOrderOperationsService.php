<?php

namespace App\Services\Shopify;

use Admin\Models\MedicineModel;
use Admin\Models\PatientModel;
use App\Modules\Shopify\Models\ShopifyMedicineMappingModel;
use App\Modules\Shopify\Models\ShopifyMappingIgnoreModel;
use App\Modules\Shopify\Models\ShopifyOrderImportLineModel;
use App\Modules\Shopify\Models\ShopifyOrderImportModel;
use App\Modules\Shopify\Models\ShopifyPatientIdentityLinkModel;
use InvalidArgumentException;

/** Admin-only operations surface for Shopify order reconciliation. */
final class ShopifyOrderOperationsService
{
    /** @return array<string, mixed> */
    public function mappingDashboard(array $filters = []): array
    {
        $db = db_connect();
        $store = strtolower(trim((string) ($filters['store_domain'] ?? (new ShopifyConfiguration())->storeDomain())));
        $mappedRows = $this->mappings(array_merge($filters, ['store_domain' => $store]));
        $mappedKeys = [];
        foreach ($mappedRows as $row) $mappedKeys[$row['shopify_product_id'] . ':' . ($row['shopify_variant_id'] ?? '')] = true;

        $items = $db->table('shopify_order_import_lines l')
            ->select('i.store_domain, l.shopify_product_id, l.shopify_variant_id, l.title_snapshot, l.shopify_sku, l.shopify_barcode, l.product_image_url, COUNT(DISTINCT l.import_id) AS pending_orders, MIN(i.created_at) AS earliest_order_date, SUM(CASE WHEN i.status IN (\'quarantined\', \'pending_mapping\', \'failed\') THEN 1 ELSE 0 END) AS quarantined_orders')
            ->join('shopify_order_imports i', 'i.id = l.import_id', 'inner')
            ->where('i.store_domain', $store)
            ->groupStart()->where('l.medicine_id', null)->orWhere('l.status', 'pending_mapping')->groupEnd()
            ->groupBy('i.store_domain, l.shopify_product_id, l.shopify_variant_id, l.title_snapshot, l.shopify_sku, l.shopify_barcode, l.product_image_url')
            ->orderBy('earliest_order_date', 'ASC')
            ->get(250)->getResultArray();
        $ignoreModel = new ShopifyMappingIgnoreModel();
        foreach ($items as &$item) {
            $item['shopify_product_id'] = (string) ($item['shopify_product_id'] ?? '');
            $item['shopify_variant_id'] = (string) ($item['shopify_variant_id'] ?? '');
            $item['key'] = $item['shopify_product_id'] . ':' . $item['shopify_variant_id'];
            $item['ignored'] = $ignoreModel->where('store_domain', $store)->where('shopify_product_id', $item['shopify_product_id'])->where('shopify_variant_id', $item['shopify_variant_id'])->first() !== null;
            $item['sku'] = (string) ($item['shopify_sku'] ?? '');
            $item['barcode'] = (string) ($item['shopify_barcode'] ?? '');
            $item['image_url'] = (string) ($item['product_image_url'] ?? '');
            $item['prescription_required'] = true;
            $item['suggestions'] = $this->suggestions((string) ($item['title_snapshot'] ?? ''), '', '');
        }
        unset($item);
        $items = array_values(array_filter($items, static fn (array $item): bool => empty($item['ignored'])));

        $total = count($mappedRows) + count($items);
        $inactive = 0;
        foreach ($mappedRows as $row) if (($row['medicine_status'] ?? 'active') !== 'active') $inactive++;
        $conflicts = $db->table('shopify_medicine_mappings')->select('store_domain, shopify_product_id, shopify_variant_id')->groupBy('store_domain, shopify_product_id, shopify_variant_id')->having('COUNT(*) >', 1)->countAllResults();

        return [
            'mappings' => $mappedRows,
            'items' => $items,
            'medicines' => $this->activeMedicines(),
            'filters' => $filters,
            'summary' => ['total_products' => $total, 'mapped' => count($mappedRows), 'unmapped' => count($items), 'conflicts' => $conflicts, 'inactive' => $inactive],
            'store_domain' => $store,
        ];
    }

    public function mappings(array $filters = []): array
    {
        $query = db_connect()->table('shopify_medicine_mappings m')
            ->select('m.*, med.medicine_name, med.generic_name, med.strength, med.unit, med.status AS medicine_status')
            ->join('medicines med', 'med.id = m.medicine_id', 'left')
            ->orderBy('m.updated_at', 'DESC');
        if (($filters['store_domain'] ?? '') !== '') $query->where('m.store_domain', strtolower(trim((string) $filters['store_domain'])));
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
            'shopify_barcode' => trim((string) ($input['shopify_barcode'] ?? '')) ?: null,
            'product_image_url' => filter_var((string) ($input['product_image_url'] ?? ''), FILTER_VALIDATE_URL) ? (string) $input['product_image_url'] : null,
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
        return (new MedicineModel())->select('id, medicine_name, generic_name, strength, unit, manufacturer, medicine_code, status')->orderBy('medicine_name', 'ASC')->findAll(500);
    }

    /** @return array<int, array<string, mixed>> */
    public function searchMedicines(string $query): array
    {
        $query = trim($query);
        $model = new MedicineModel();
        if ($query !== '') $model->groupStart()->like('medicine_name', $query)->orLike('strength', $query)->orLike('unit', $query)->orLike('manufacturer', $query)->orLike('medicine_code', $query)->groupEnd();
        return $model->select('id, medicine_name, strength, unit, manufacturer, medicine_code, status')->orderBy('medicine_name')->findAll(50);
    }

    /** @return array<int, array<string, mixed>> */
    private function suggestions(string $title, string $sku, string $barcode): array
    {
        $normalized = $this->normalized($title);
        $suggestions = [];
        foreach ((new MedicineModel())->where('status', 'active')->findAll(500) as $medicine) {
            $name = $this->normalized((string) ($medicine['medicine_name'] ?? ''));
            $strength = $this->normalized((string) ($medicine['strength'] ?? ''));
            $score = 0; $confidence = 'Manual selection required';
            if ($sku !== '' && $sku === (string) ($medicine['medicine_code'] ?? '')) { $score = 100; $confidence = 'Exact match'; }
            elseif ($normalized !== '' && $normalized === $name) { $score = 90; $confidence = 'Strong match'; }
            elseif ($normalized !== '' && $strength !== '' && str_contains($normalized, $name) && str_contains($normalized, $strength)) { $score = 75; $confidence = 'Possible match'; }
            if ($score > 0) $suggestions[] = ['medicine' => $medicine, 'score' => $score, 'confidence' => $confidence];
        }
        usort($suggestions, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        return array_slice($suggestions, 0, 5);
    }

    private function normalized(string $value): string { return trim((string) preg_replace('/[^a-z0-9]+/i', ' ', strtolower($value))); }

    /** @return array<string, mixed> */
    public function bulkSaveMappings(array $input, int $actor): array
    {
        $variants = $input['variants'] ?? $input['variant_ids'] ?? [];
        if (is_string($variants)) $variants = json_decode($variants, true) ?: array_filter(array_map('trim', explode(',', $variants)));
        if (! is_array($variants) || $variants === []) throw new InvalidArgumentException('Select at least one Shopify variant.');
        $medicineId = (int) ($input['medicine_id'] ?? 0);
        if ($medicineId < 1 || (new MedicineModel())->where('id', $medicineId)->where('status', 'active')->first() === null) throw new InvalidArgumentException('Select an active ROOTINS medicine.');
        $saved = 0;
        foreach ($variants as $variant) {
            $productId = is_array($variant) ? (string) ($variant['product_id'] ?? '') : '';
            $variantId = is_array($variant) ? (string) ($variant['variant_id'] ?? '') : (string) $variant;
            if ($variantId === '') continue;
            $this->saveMapping(['store_domain' => $input['store_domain'] ?? '', 'shopify_product_id' => $productId, 'shopify_variant_id' => $variantId, 'shopify_product_title' => is_array($variant) ? ($variant['title'] ?? '') : '', 'shopify_variant_title' => is_array($variant) ? ($variant['variant_title'] ?? '') : '', 'shopify_sku' => is_array($variant) ? ($variant['sku'] ?? '') : '', 'medicine_id' => $medicineId], null);
            $saved++;
        }
        service('audit')->record('shopify.order_mapping_bulk_created', ['actor' => $actor, 'count' => $saved, 'medicine_id' => $medicineId]);
        return ['saved' => $saved];
    }

    public function ignoreMapping(array $input, int $actor): bool
    {
        $store = strtolower(trim((string) ($input['store_domain'] ?? (new ShopifyConfiguration())->storeDomain())));
        $product = trim((string) ($input['shopify_product_id'] ?? '')); $variant = trim((string) ($input['shopify_variant_id'] ?? '')); $reason = trim((string) ($input['reason'] ?? ''));
        if ($store === '' || $product === '' || $variant === '' || $reason === '') throw new InvalidArgumentException('Product, variant, and an ignore reason are required.');
        $model = new ShopifyMappingIgnoreModel();
        $existing = $model->where('store_domain', $store)->where('shopify_product_id', $product)->where('shopify_variant_id', $variant)->first();
        $data = ['store_domain' => $store, 'shopify_product_id' => $product, 'shopify_variant_id' => $variant, 'reason' => $reason, 'created_by' => $actor, 'updated_at' => date('Y-m-d H:i:s')];
        if ($existing) $model->update((int) $existing['id'], $data); else { $data['created_at'] = date('Y-m-d H:i:s'); $model->insert($data); }
        service('audit')->record('shopify.order_mapping_ignored', ['store_domain' => $store, 'product_id' => $product, 'variant_id' => $variant, 'actor' => $actor]);
        return true;
    }

    /** @return array<string, int> */
    public function recheckMappings(array $input): array
    {
        $store = strtolower(trim((string) ($input['store_domain'] ?? ''))); $variant = trim((string) ($input['shopify_variant_id'] ?? ''));
        if ($store === '' || $variant === '') throw new InvalidArgumentException('A store and Shopify variant are required.');
        $query = db_connect()->table('shopify_order_import_lines l')->select('l.import_id')->join('shopify_order_imports i', 'i.id = l.import_id')->where('i.store_domain', $store);
        if ($variant !== 'all') $query->where('l.shopify_variant_id', $variant);
        $imports = $query->groupBy('l.import_id')->get()->getResultArray();
        $service = new ShopifyOrderImportService(); $processed = 0;
        foreach ($imports as $import) { $service->importExisting((int) $import['import_id'], null); $processed++; }
        service('audit')->record('shopify.order_mapping_recheck', ['store_domain' => $store, 'variant_id' => $variant, 'count' => $processed]);
        return ['processed' => $processed];
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
