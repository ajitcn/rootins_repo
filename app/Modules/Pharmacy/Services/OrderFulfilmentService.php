<?php

namespace Pharmacy\Services;

use Admin\Models\MedicineModel;
use Admin\Models\MedicineOrderItemModel;
use Admin\Models\MedicineOrderModel;
use Admin\Models\InvoiceItemModel;
use Admin\Models\InvoiceModel;
use Admin\Models\PrescriptionItemModel;
use Admin\Models\PrescriptionModel;
use DateTimeImmutable;

final class OrderFulfilmentService
{
    public function __construct(
        private readonly PharmacyScope $scope,
        private readonly ?int $clinicOverride = null,
    )
    {
    }

    /**
     * Order creation owns the approved-prescription check, FEFO allocation,
     * invoice linkage, and rollback boundary for the complete fulfilment flow.
     */
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
        $filters = ['search' => trim((string) ($input['search'] ?? '')), 'status' => trim((string) ($input['status'] ?? '')), 'payment_status' => trim((string) ($input['payment_status'] ?? '')), 'delivery_status' => trim((string) ($input['delivery_status'] ?? '')), 'from' => $this->toIsoDate((string) ($input['from'] ?? '')), 'to' => $this->toIsoDate((string) ($input['to'] ?? '')), 'page_size' => $pageSize];

        if ($clinicId === null) {
            return ['orders' => [], 'approvedPrescriptions' => [], 'view' => $view, 'filters' => $this->displayFilters($filters), 'pagination' => ['page' => 1, 'pages' => 1, 'page_size' => $pageSize, 'total' => 0]];
        }
        $query = db_connect()->table('medicine_orders AS orders')
            ->select('orders.id, orders.order_number, orders.status, orders.payment_status, orders.payment_provider, payment_providers.provider_name AS payment_provider_name, orders.payment_reference, orders.delivery_status, orders.delivery_provider, orders.delivery_provider_id, orders.delivery_provider_name_snapshot, providers.provider_name, providers.tracking_url_template, orders.tracking_reference, orders.delivery_address_snapshot, orders.delivery_contact_snapshot, orders.dispatched_at, orders.delivered_at, orders.failure_reason, orders.return_reason, orders.created_at, orders.updated_at, orders.prescription_id, orders.patient_id, prescriptions.prescription_number, prescriptions.prescription_date, patients.registration_number, patients.first_name, patients.middle_name, patients.last_name, patients.mobile_number, patients.country_code, (SELECT prescription_status FROM shopify_order_imports WHERE shopify_order_imports.rootins_order_id = orders.id ORDER BY shopify_order_imports.id DESC LIMIT 1) AS shopify_prescription_status, (SELECT invoice_number FROM invoices WHERE invoices.order_id = orders.id AND invoices.clinic_id = orders.clinic_id ORDER BY invoices.id DESC LIMIT 1) AS invoice_number, (SELECT COUNT(*) FROM medicine_order_items count_items WHERE count_items.order_id = orders.id) AS medicine_count, (SELECT GROUP_CONCAT(summary_items.medicine_name ORDER BY summary_items.id SEPARATOR ", ") FROM medicine_order_items summary_items WHERE summary_items.order_id = orders.id) AS medicine_summary, (SELECT COALESCE(SUM(total_items.line_total), 0) FROM medicine_order_items total_items WHERE total_items.order_id = orders.id) AS total_amount')
            ->join('delivery_providers providers', 'providers.id = orders.delivery_provider_id', 'left')
            ->join('payment_providers', 'payment_providers.provider_code = orders.payment_provider', 'left')
            ->join('patients', 'patients.id = orders.patient_id')
            ->join('prescriptions', 'prescriptions.id = orders.prescription_id', 'left')
            ->where('orders.clinic_id', $clinicId)
            ->groupStart()
                ->where('prescriptions.clinic_id', $clinicId)
                ->orWhere('prescriptions.id', null)
            ->groupEnd();

        if ($filters['status'] !== '' && in_array($filters['status'], ['pending', 'fulfilled', 'cancelled'], true)) {
            $query->where('orders.status', $filters['status']);
        }

        if ($filters['payment_status'] !== '' && in_array($filters['payment_status'], ['pending', 'authorized', 'paid', 'failed', 'refunded'], true)) {
            $query->where('orders.payment_status', $filters['payment_status']);
        }

        if ($filters['delivery_status'] !== '' && in_array($filters['delivery_status'], array_keys(TransitionContract::DELIVERY), true)) {
            $query->where('orders.delivery_status', $filters['delivery_status']);
        }

        if ($filters['from'] !== null) {
            $query->where('orders.created_at >=', $filters['from'] . ' 00:00:00');
        }

        if ($filters['to'] !== null) {
            $query->where('orders.created_at <=', $filters['to'] . ' 23:59:59');
        }

        if ($filters['search'] !== '') {
            $query->groupStart()->like('orders.order_number', $filters['search'])->orLike('patients.first_name', $filters['search'])->orLike('patients.middle_name', $filters['search'])->orLike('patients.last_name', $filters['search'])->orLike('patients.registration_number', $filters['search'])->orLike('patients.mobile_number', $filters['search'])->orLike('patients.mobile_normalized', $filters['search'])->orLike('prescriptions.prescription_number', $filters['search'])->orLike('orders.payment_status', $filters['search'])->orLike('orders.delivery_status', $filters['search'])->orLike('orders.status', $filters['search'])->groupEnd();
        }
        $total = (clone $query)->countAllResults();
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $records = $query->orderBy('orders.id', 'DESC')->get($pageSize, ($page - 1) * $pageSize)->getResultArray();
        $orders = array_map(fn (array $record): array => $this->normalize($record), $records);

        return ['orders' => $orders, 'approvedPrescriptions' => $this->approvedPrescriptions($clinicId), 'view' => $view, 'filters' => $this->displayFilters($filters), 'pagination' => ['page' => $page, 'pages' => $pages, 'page_size' => $pageSize, 'total' => $total]];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(int $orderId): ?array
    {
        $clinicId = $this->scope->clinicId();

        if ($clinicId === null || $orderId <= 0) {
            return null;
        }
        $order = db_connect()->table('medicine_orders AS orders')->select('orders.*, payment_providers.provider_name AS payment_provider_name, providers.provider_name, providers.tracking_url_template, clinics.clinic_name, prescriptions.prescription_number, prescriptions.prescription_date, patients.registration_number, patients.first_name, patients.middle_name, patients.last_name, patients.mobile_number, patients.country_code, (SELECT invoice_number FROM invoices WHERE invoices.order_id = orders.id AND invoices.clinic_id = orders.clinic_id ORDER BY invoices.id DESC LIMIT 1) AS invoice_number')->join('clinics', 'clinics.id = orders.clinic_id')->join('patients', 'patients.id = orders.patient_id')->join('prescriptions', 'prescriptions.id = orders.prescription_id', 'left')->join('delivery_providers providers', 'providers.id = orders.delivery_provider_id', 'left')->join('payment_providers', 'payment_providers.provider_code = orders.payment_provider', 'left')->where('orders.id', $orderId)->where('orders.clinic_id', $clinicId)->where('clinics.id', $clinicId)->groupStart()->where('prescriptions.clinic_id', $clinicId)->orWhere('prescriptions.id', null)->groupEnd()->get()->getRowArray();

        if ($order === null) {
            return null;
        }
        $items = db_connect()->table('medicine_order_items')->where('order_id', $orderId)->orderBy('id', 'ASC')->get()->getResultArray();
        $order['patient_name'] = trim(implode(' ', array_filter([$order['first_name'] ?? '', $order['middle_name'] ?? '', $order['last_name'] ?? '']))) ?: 'Patient not recorded';
        $order['patient_mobile'] = trim(implode(' ', array_filter([(string) ($order['country_code'] ?? ''), (string) ($order['mobile_number'] ?? '')])) ?: 'Not recorded');
        $order['prescription_date_display'] = $this->displayDate((string) ($order['prescription_date'] ?? ''));
        $order['created_at_display'] = $this->displayDate((string) ($order['created_at'] ?? ''));
        $order['updated_at_display'] = $this->displayDate((string) ($order['updated_at'] ?? ''));
        $order['dispatched_at_display'] = $this->displayDate((string) ($order['dispatched_at'] ?? ''));
        $order['delivered_at_display'] = $this->displayDate((string) ($order['delivered_at'] ?? ''));
        $order['items'] = array_map(fn (array $item): array => ['medicine_name' => (string) ($item['medicine_name'] ?? 'Unnamed medicine'), 'batch_number' => (string) ($item['batch_number'] ?? 'Not recorded'), 'quantity' => (int) ($item['quantity'] ?? 0), 'unit_price' => (float) ($item['unit_price'] ?? 0), 'line_total' => (float) ($item['line_total'] ?? 0), 'dosage' => (string) ($item['dosage'] ?? ''), 'frequency' => (string) ($item['frequency'] ?? ''), 'instructions' => (string) ($item['instructions'] ?? '')], $items);
        $order['medicine_count'] = count($items);
        $order['total_amount'] = array_sum(array_map(static fn (array $item): float => (float) ($item['line_total'] ?? 0), $items));
        $order['delivery_address'] = trim((string) ($order['delivery_address_snapshot'] ?? '')) ?: 'Not recorded';
        $order['delivery_contact'] = trim((string) ($order['delivery_contact_snapshot'] ?? '')) ?: $order['patient_mobile'];
        $order['delivery_provider'] = trim((string) ($order['delivery_provider_name_snapshot'] ?? $order['provider_name'] ?? $order['delivery_provider'] ?? '')) ?: 'Not recorded';
        $invoice = db_connect()->table('invoices')->where('order_id', $orderId)->where('clinic_id', $clinicId)->orderBy('id', 'DESC')->get()->getRowArray() ?: null;
        $order['invoice'] = $invoice === null ? null : array_intersect_key($invoice, array_flip(['invoice_number', 'status', 'currency', 'subtotal', 'tax', 'discount', 'total', 'payment_status', 'created_at', 'updated_at']));
        $order['audit_history'] = [];

        if (db_connect()->tableExists('audit_logs')) {
            $order['audit_history'] = db_connect()->table('audit_logs')
                ->select('action, occurred_at')
                ->where('clinic_id', $clinicId)
                ->like('context', '"order_id":' . $orderId)
                ->orderBy('occurred_at', 'DESC')
                ->get(25)
                ->getResultArray();
        }

        foreach (['id', 'clinic_id', 'patient_id', 'prescription_id', 'idempotency_key', 'source_type', 'external_store_domain', 'external_order_id', 'external_order_number', 'created_by', 'updated_by'] as $privateField) {
            unset($order[$privateField]);
        }

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    public function create(int $prescriptionId, array $external = []): array
    {
        $clinicId = $this->clinicOverride ?? $this->scope->clinicId();

        if ($clinicId === null) {
            return $this->result(403, 'Pharmacy clinic scope is not assigned.');
        }

        if ($prescriptionId <= 0) {
            return $this->result(422, 'A valid prescription is required.', ['prescription_id' => 'Prescription is required.']);
        }
        $prescription = $this->loadPrescription($prescriptionId, $clinicId);

        if ($prescription === null || ($prescription['status'] ?? '') !== 'approved') {
            return $this->result(422, 'Only an approved prescription in the assigned clinic can create a pharmacy order.', ['prescription_id' => 'Prescription must be approved.']);
        }
        // Shopify imports are idempotent by the imported order record.
        $idempotencyKey = trim((string) ($external['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = 'prescription:' . $prescriptionId;
        }
        $db = db_connect();
        $today = $this->today();
        $db->transBegin();

        try {
            $existing = $db->query('SELECT id, order_number FROM medicine_orders WHERE idempotency_key = ? AND clinic_id = ? LIMIT 1 FOR UPDATE', [$idempotencyKey, $clinicId])->getRowArray();

            if ($existing !== null) {
                $db->transRollback();

                return [
                    'http' => 200,
                    'body' => [
                        'status' => true,
                        'msg' => 'The pharmacy order already exists and was reused safely.',
                        'data' => [
                            'order_id' => (int) $existing['id'],
                            'order_number' => (string) $existing['order_number'],
                            'idempotent_reuse' => true,
                        ],
                    ],
                ];
            }
            $items = db_connect()->table('prescription_items')->where('prescription_id', $prescriptionId)->orderBy('id', 'ASC')->get()->getResultArray();

            if ($items === []) {
                $db->transRollback();

                return $this->result(422, 'Order cannot be created without prescription medicines.', ['items' => 'At least one medicine is required.']);
            }
            $medicineRows = db_connect()->table('medicines')->where('status', 'active')->get()->getResultArray();
            $medicineMap = [];

            foreach ($medicineRows as $medicine) {
                $medicineMap[strtolower(trim((string) $medicine['medicine_name']))] = $medicine;
            }
            $allocations = [];
            $reserved = [];

            foreach ($items as $index => $item) {
                $name = strtolower(trim((string) ($item['medicine_name'] ?? '')));
                $quantity = $this->resolveQuantity($item);

                if (!isset($medicineMap[$name])) {
                    $db->transRollback();

                    return $this->result(422, 'Order cannot be created because a medicine is inactive or missing.', ['items.' . $index . '.medicine_name' => 'Medicine is missing or inactive.']);
                }

                if ($quantity === null || $quantity <= 0) {
                    $db->transRollback();

                    return $this->result(422, 'Order cannot be created because a quantity is invalid.', ['items.' . $index . '.quantity' => 'Quantity is missing or cannot be derived from the prescription.']);
                }
                $medicine = $medicineMap[$name];
                $batches = $db->query('SELECT * FROM medicine_inventory_batches WHERE medicine_id = ? AND status = ? AND expiry_date >= ? AND quantity_available > 0 ORDER BY expiry_date ASC, id ASC FOR UPDATE', [(int) $medicine['id'], 'active', $today])->getResultArray();
                $remaining = $quantity;

                foreach ($batches as $batch) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $key = (int) $batch['id'];
                    $available = (int) $batch['quantity_available'] - (int) ($reserved[$key] ?? 0);

                    if ($available <= 0) {
                        continue;
                    }
                    $allocated = min($remaining, $available);
                    $reserved[$key] = ($reserved[$key] ?? 0) + $allocated;
                    $allocations[] = ['item' => $item, 'medicine' => $medicine, 'batch' => $batch, 'quantity' => $allocated];
                    $remaining -= $allocated;
                }

                if ($remaining > 0) {
                    $db->transRollback();

                    return $this->result(422, 'Order cannot be created because active, unexpired stock is insufficient.', ['items.' . $index . '.quantity' => 'Available stock is insufficient or expired.']);
                }
            }
            $orderNumber = 'MO-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $orderId = (new MedicineOrderModel())->insert([
                'order_number' => $orderNumber,
                'prescription_id' => $prescriptionId,
                'patient_id' => (int) $prescription['patient_id'],
                'clinic_id' => $clinicId,
                'status' => 'pending',
                'payment_status' => 'pending',
                'delivery_status' => 'pending',
                'idempotency_key' => $idempotencyKey,
                'source_type' => (string) ($external['source_type'] ?? 'internal'),
                'external_store_domain' => $external['external_store_domain'] ?? null,
                'external_order_id' => $external['external_order_id'] ?? null,
                'external_order_number' => $external['external_order_number'] ?? null,
                'delivery_address_snapshot' => $external['delivery_address_snapshot'] ?? null,
            ], true);

            if (!$orderId) {
                throw new \RuntimeException('Order insert failed.');
            }
            $itemModel = new MedicineOrderItemModel();

            foreach ($allocations as $allocation) {
                $batchId = (int) $allocation['batch']['id'];
                $updated = $db->query('UPDATE medicine_inventory_batches SET quantity_available = quantity_available - ? WHERE id = ? AND status = ? AND expiry_date >= ? AND quantity_available >= ?', [$allocation['quantity'], $batchId, 'active', $today, $allocation['quantity']]);

                if ($updated === false) {
                    $dbError = $db->error();

                    throw new \RuntimeException('Inventory decrement failed: ' . (string) ($dbError['message'] ?? 'database error'));
                }

                if ($db->affectedRows() !== 1) {
                    throw new \RuntimeException('Inventory changed while creating the pharmacy order.');
                }
                $item = $allocation['item'];
                $unitPrice = round((float) ($allocation['medicine']['unit_price'] ?? 0), 2);
                $lineTotal = round($unitPrice * (int) $allocation['quantity'], 2);

                if (!$itemModel->insert(['order_id' => (int) $orderId, 'prescription_item_id' => (int) $item['id'], 'medicine_id' => (int) $allocation['medicine']['id'], 'medicine_name' => $item['medicine_name'], 'batch_number' => $allocation['batch']['batch_number'], 'inventory_batch_id' => $batchId, 'dosage' => $item['dosage'] ?? null, 'frequency' => $item['frequency'] ?? null, 'duration' => $item['duration'] ?? null, 'duration_unit' => $item['duration_unit'] ?? null, 'instructions' => $item['instructions'] ?? null, 'quantity' => $allocation['quantity'], 'unit_price' => $unitPrice, 'line_total' => $lineTotal])) {
                    throw new \RuntimeException('Order item insert failed.');
                }
                $movementBatch = $allocation['batch'];
                $movementBatch['quantity_available'] = (int) $movementBatch['quantity_available'] - (int) $allocation['quantity'];
                $this->recordMovement($db, $movementBatch, -1 * (int) $allocation['quantity'], 'Order allocation', $clinicId);
            }
            $invoice = $db->table('invoices')->where('order_id', (int) $orderId)->where('clinic_id', $clinicId)->get()->getRowArray();

            if ($invoice === null) {
                $invoiceNumber = 'INV-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));
                $invoiceTotal = (float) $db->table('medicine_order_items')->selectSum('line_total')->where('order_id', (int) $orderId)->get()->getRowArray()['line_total'];
                $invoiceModel = new InvoiceModel();
                $invoiceId = $invoiceModel->insert(['order_id' => (int) $orderId, 'invoice_number' => $invoiceNumber, 'clinic_id' => $clinicId, 'patient_id' => (int) $prescription['patient_id'], 'currency' => 'INR', 'subtotal' => $invoiceTotal, 'tax' => 0, 'discount' => 0, 'total' => $invoiceTotal, 'status' => 'draft', 'payment_status' => 'pending', 'idempotency_key' => 'order:' . (int) $orderId, 'created_by' => (int) (($this->scope->user() ?? [])['id'] ?? 0)], true);

                if (!$invoiceId) {
                    throw new \RuntimeException('Invoice insert failed.');
                }
                $invoiceItems = [];

                foreach ($db->table('medicine_order_items')->where('order_id', (int) $orderId)->get()->getResultArray() as $orderItem) {
                    $invoiceItems[] = ['invoice_id' => (int) $invoiceId, 'description' => (string) $orderItem['medicine_name'], 'quantity' => (float) $orderItem['quantity'], 'unit_price' => (float) $orderItem['unit_price'], 'line_total' => (float) $orderItem['line_total']];
                }

                if ((new InvoiceItemModel())->insertBatch($invoiceItems) === false) {
                    throw new \RuntimeException('Invoice item insert failed.');
                }
            }

            if (!$db->transStatus()) {
                throw new \RuntimeException('Order transaction failed.');
            }
            $db->transCommit();
            if ($this->clinicOverride !== null) {
                service('audit')->record('shopify.order_created', ['order_id' => (int) $orderId, 'prescription_id' => $prescriptionId, 'clinic_id' => $clinicId, 'external_order_id' => $external['external_order_id'] ?? null]);
            } else {
                $this->scope->audit('pharmacy.order_created', ['order_id' => (int) $orderId, 'prescription_id' => $prescriptionId, 'previous_status' => null, 'new_status' => 'pending', 'reason' => 'approved prescription fulfilment']);
            }
            (new PharmacyNotificationService($this->scope))->create('order_created', 'Medicine order created', 'A new medicine order was created from prescription #' . $prescriptionId . '.', 'order', (int) $orderId, 'order-created:' . (int) $orderId);
            (new \Patient\Services\PatientNotificationService())->forOrder((int) $orderId, 'order_created', 'Medicine order created', 'Your medicine order is ready for processing.', 'patient-order-created:' . (int) $orderId);

            return ['http' => 200, 'body' => ['status' => true, 'msg' => 'Medicine order created.', 'data' => ['order_id' => (int) $orderId, 'order_number' => $orderNumber]]];
        } catch (\Throwable $exception) {
            $db->transRollback();
            $dbError = $db->error();
            $context = is_array($external['diagnostic_context'] ?? null) ? $external['diagnostic_context'] : [];
            log_message('error', 'Pharmacy order creation database failure ' . json_encode([
                'correlation_id' => (string) ($context['correlation_id'] ?? ''),
                'shopify_order_id' => (string) ($context['shopify_order_id'] ?? ''),
                'internal_import_id' => (int) ($context['internal_import_id'] ?? 0),
                'target_table' => 'medicine_orders',
                'operation' => 'insert_or_upsert',
                'database_error_code' => (string) ($dbError['code'] ?? ''),
                'sanitized_database_message' => $this->sanitizeDatabaseMessage((string) ($dbError['message'] ?? $exception->getMessage())),
                'missing_or_invalid_field' => $this->databaseFieldHint((string) ($dbError['message'] ?? $exception->getMessage())),
                'affected_rows' => (int) $db->affectedRows(),
            ], JSON_UNESCAPED_SLASHES));

            return $this->result(500, 'Medicine order could not be created safely.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(int $orderId, string $reason, bool $confirmed = false): array
    {
        $clinicId = $this->scope->clinicId();

        if ($clinicId === null) {
            return $this->result(403, 'Pharmacy clinic scope is not assigned.');
        }

        if (trim($reason) === '') {
            return $this->result(422, 'Cancellation reason is required.', ['reason' => 'Cancellation reason is required.']);
        }

        if (!$confirmed) {
            return $this->result(422, 'Cancellation confirmation is required.', ['confirmation' => 'Confirm that you want to cancel this pharmacy order.']);
        }
        $db = db_connect();
        $db->transBegin();

        try {
            $order = $db->query('SELECT orders.* FROM medicine_orders orders JOIN patients ON patients.id = orders.patient_id JOIN prescriptions ON prescriptions.id = orders.prescription_id AND prescriptions.clinic_id = ? WHERE orders.id = ? AND orders.clinic_id = ? LIMIT 1 FOR UPDATE', [$clinicId, $orderId, $clinicId])->getRowArray();

            if ($order === null) {
                $db->transRollback();

                return $this->result(404, 'Order not found in the assigned clinic.');
            }

            if ((string) $order['status'] === 'cancelled') {
                $db->transCommit();

                return ['http' => 200, 'body' => ['status' => true, 'msg' => 'Order was already cancelled.', 'data' => ['duplicate' => true]]];
            }
            $transitions = new TransitionContract();

            if (!$transitions->allows('order', (string) $order['status'], 'cancelled')) {
                $db->transRollback();

                return $this->result(422, 'Order status transition is not allowed.', ['status' => 'Only pending orders can be cancelled.']);
            }
            $items = $db->query('SELECT * FROM medicine_order_items WHERE order_id = ? ORDER BY id ASC FOR UPDATE', [$orderId])->getResultArray();

            foreach ($items as $item) {
                if (empty($item['batch_number'])) {
                    continue;
                }
                $batchId = (int) ($item['inventory_batch_id'] ?? 0);

                if ($batchId > 0) {
                    $batch = $db->query('SELECT * FROM medicine_inventory_batches WHERE id = ? AND medicine_id = ? LIMIT 1 FOR UPDATE', [$batchId, (int) $item['medicine_id']])->getRowArray();
                } else {
                    $matches = $db->query('SELECT * FROM medicine_inventory_batches WHERE medicine_id = ? AND batch_number = ? LIMIT 2 FOR UPDATE', [(int) $item['medicine_id'], $item['batch_number']])->getResultArray();

                    if (count($matches) !== 1) {
                        throw new \RuntimeException('Historical order item has an ambiguous inventory batch reference.');
                    }
                    $batch = $matches[0];
                }

                if ($batch === null) {
                    throw new \RuntimeException('Allocated inventory batch is missing.');
                }
                $db->query('UPDATE medicine_inventory_batches SET quantity_available = quantity_available + ? WHERE id = ?', [(int) $item['quantity'], (int) $batch['id']]);
                $batch['quantity_available'] = (int) $batch['quantity_available'] + (int) $item['quantity'];
                $this->recordMovement($db, $batch, (int) $item['quantity'], 'Order cancellation release', $clinicId);
            }
            $updated = $db->table('medicine_orders')->where('id', $orderId)->where('clinic_id', $clinicId)->where('status', $order['status'])->update(['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')]);

            if (!$updated || !$db->transStatus()) {
                throw new \RuntimeException('Order cancellation transaction failed.');
            }
            $db->transCommit();
            $this->scope->audit('pharmacy.order_cancelled', ['order_id' => $orderId, 'previous_status' => $order['status'], 'new_status' => 'cancelled', 'reason' => trim($reason)]);
            (new PharmacyNotificationService($this->scope))->create('order_cancelled', 'Medicine order cancelled', 'Order #' . $orderId . ' was cancelled.', 'order', $orderId, 'order-cancelled:' . $orderId);
            (new \Patient\Services\PatientNotificationService())->forOrder($orderId, 'order_cancelled', 'Medicine order cancelled', 'Your medicine order has been cancelled.', 'patient-order-cancelled:' . $orderId);

            return ['http' => 200, 'body' => ['status' => true, 'msg' => 'Order cancelled and allocated stock released.']];
        } catch (\Throwable $exception) {
            $db->transRollback();
            log_message('error', 'Pharmacy order cancellation failed: ' . $exception->getMessage());

            return $this->result(500, 'Order could not be cancelled safely.');
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function approvedPrescriptions(int $clinicId): array
    {
        return db_connect()->table('prescriptions')->select('prescriptions.id, prescriptions.prescription_number, prescriptions.prescription_date, patients.registration_number, patients.first_name, patients.last_name')->join('patients', 'patients.id = prescriptions.patient_id')->join('doctors', 'doctors.id = prescriptions.doctor_id')->join('medicine_orders', 'medicine_orders.prescription_id = prescriptions.id', 'left')->where('prescriptions.clinic_id', $clinicId)->where('doctors.clinic_id', $clinicId)->where('prescriptions.status', 'approved')->where('medicine_orders.id', null)->orderBy('prescriptions.prescription_date', 'ASC')->limit(100)->get()->getResultArray();
    }

    /**
     * Resolve a prescription through the same clinic, patient, and doctor
     * scope for both Pharmacy and verified Shopify imports.
     *
     * @return array<string, mixed>|null
     */
    private function loadPrescription(int $prescriptionId, int $clinicId): ?array
    {
        return db_connect()->table('prescriptions')
            ->select('prescriptions.*')
            ->join('patients', 'patients.id = prescriptions.patient_id')
            ->join('doctors', 'doctors.id = prescriptions.doctor_id')
            ->where('prescriptions.id', $prescriptionId)
            ->where('prescriptions.clinic_id', $clinicId)
            ->where('doctors.clinic_id', $clinicId)
            ->get(1)
            ->getRowArray() ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(array $record): array
    {
        $name = trim(implode(' ', array_filter([$record['first_name'] ?? '', $record['middle_name'] ?? '', $record['last_name'] ?? ''])));
        $mobile = trim(implode(' ', array_filter([(string) ($record['country_code'] ?? ''), (string) ($record['mobile_number'] ?? '')])));
        $record['prescription_status'] = trim((string) ($record['shopify_prescription_status'] ?? '')) ?: 'not_applicable';
        return ['id' => (int) $record['id'], 'order_number' => (string) ($record['order_number'] ?? 'Not recorded'), 'order_date' => $this->displayDate((string) ($record['created_at'] ?? '')) ?: 'Not recorded', 'patient_name' => $name ?: 'Patient not recorded', 'patient_number' => (string) ($record['registration_number'] ?? 'Not recorded'), 'patient_mobile' => $mobile ?: 'Not recorded', 'prescription_id' => (int) ($record['prescription_id'] ?? 0), 'prescription_number' => (string) ($record['prescription_number'] ?? 'Not recorded'), 'prescription_date' => $this->displayDate((string) ($record['prescription_date'] ?? '')), 'medicine_count' => (int) ($record['medicine_count'] ?? 0), 'medicine_summary' => trim((string) ($record['medicine_summary'] ?? '')) ?: 'No medicine summary', 'invoice_number' => trim((string) ($record['invoice_number'] ?? '')) ?: 'Not linked', 'total_amount' => (float) ($record['total_amount'] ?? 0), 'status' => (string) ($record['status'] ?? ''), 'payment_status' => (string) ($record['payment_status'] ?? ''), 'payment_provider' => trim((string) ($record['payment_provider'] ?? '')) ?: 'Not recorded', 'payment_provider_name' => trim((string) ($record['payment_provider_name'] ?? '')) ?: trim((string) ($record['payment_provider'] ?? '')) ?: 'Not recorded', 'payment_reference' => trim((string) ($record['payment_reference'] ?? '')) ?: 'Not recorded', 'delivery_status' => (string) ($record['delivery_status'] ?? ''), 'delivery_provider' => trim((string) ($record['delivery_provider_name_snapshot'] ?? $record['provider_name'] ?? $record['delivery_provider'] ?? '')) ?: 'Not recorded', 'delivery_provider_id' => (int) ($record['delivery_provider_id'] ?? 0), 'tracking_url_template' => (string) ($record['tracking_url_template'] ?? ''), 'tracking_reference' => trim((string) ($record['tracking_reference'] ?? '')) ?: 'Not recorded', 'delivery_address' => trim((string) ($record['delivery_address_snapshot'] ?? '')) ?: 'Not recorded', 'delivery_contact' => trim((string) ($record['delivery_contact_snapshot'] ?? '')) ?: ($mobile ?: 'Not recorded'), 'dispatched_at' => $this->displayDate((string) ($record['dispatched_at'] ?? '')), 'delivered_at' => $this->displayDate((string) ($record['delivered_at'] ?? '')), 'failure_reason' => trim((string) ($record['failure_reason'] ?? '')) ?: 'Not recorded', 'return_reason' => trim((string) ($record['return_reason'] ?? '')) ?: 'Not recorded', 'created_at' => (string) ($record['created_at'] ?? ''), 'updated_at' => (string) ($record['updated_at'] ?? ''), 'can_cancel' => ($record['status'] ?? '') === 'pending'];
    }

    private function displayDate(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));

        return $date !== false && $date->format('Y-m-d') === substr($value, 0, 10) ? $date->format('d-m-Y') : '';
    }

    private function resolveQuantity(array $item): ?int
    {
        $stored = trim((string) ($item['quantity'] ?? ''));

        if ($stored !== '' && is_numeric($stored) && (float) $stored > 0 && floor((float) $stored) === (float) $stored) {
            return (int) $stored;
        }

        if ($stored !== '') {
            return null;
        }

        $duration = (int) trim((string) ($item['duration'] ?? ''));
        $frequency = strtolower(trim((string) ($item['frequency'] ?? '')));
        $dose = (int) trim((string) ($item['dosage'] ?? ''));

        if ($duration <= 0 || $dose <= 0) {
            return null;
        }

        $dosesPerDay = match (true) {
            str_contains($frequency, 'four') => 4,
            str_contains($frequency, 'three') => 3,
            str_contains($frequency, 'twice') => 2,
            str_contains($frequency, 'once') => 1,
            preg_match('/every\s+(\d+)\s*hour/', $frequency, $match) === 1 && (int) $match[1] > 0 => (int) floor(24 / (int) $match[1]),
            default => 0,
        };

        if ($dosesPerDay <= 0) {
            return null;
        }

        return $duration * $dosesPerDay * $dose;
    }

    private function sanitizeDatabaseMessage(string $message): string
    {
        $message = preg_replace('/\s+/', ' ', trim($message)) ?? '';
        $message = preg_replace('/(password|secret|token|access[_ -]?key)\s*[=:]\s*[^ ]+/i', '$1=[redacted]', $message) ?? $message;
        $message = preg_replace('/\b(select|insert|update|delete|alter|create|drop)\b.*$/i', '[database operation failed]', $message) ?? $message;

        return mb_substr($message, 0, 240);
    }

    private function databaseFieldHint(string $message): ?string
    {
        if (preg_match('/(?:unknown column|column) [`\']?([a-zA-Z0-9_]+)[`\']?/i', $message, $matches) === 1) return $matches[1];
        if (preg_match('/foreign key.*?foreign key\s*\(`?([a-zA-Z0-9_]+)`?\)/i', $message, $matches) === 1) return $matches[1];

        return null;
    }

    private function toIsoDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        } $date = DateTimeImmutable::createFromFormat('!d-m-Y', $value);

        return $date !== false && $date->format('d-m-Y') === $value ? $date->format('Y-m-d') : null;
    }

    private function displayFilters(array $filters): array
    {
        $filters['from'] = $filters['from'] ? $this->displayDate($filters['from']) : '';
        $filters['to'] = $filters['to'] ? $this->displayDate($filters['to']) : '';

        return $filters;
    }

    private function today(): string
    {
        $timezone = (string) (config('App')->appTimezone ?: date_default_timezone_get());

        return (new \DateTimeImmutable('today', new \DateTimeZone($timezone)))->format('Y-m-d');
    }

    private function recordMovement($db, array $batch, int $delta, string $reason, int $clinicId): void
    {
        $new = max(0, (int) ($batch['quantity_available'] ?? 0));
        $previous = max(0, $new - $delta);
        $userId = (int) (($this->scope->user() ?? [])['id'] ?? 0);
        $db->table('inventory_stock_movements')->insert(['inventory_batch_id' => (int) $batch['id'], 'medicine_id' => (int) $batch['medicine_id'], 'batch_number' => (string) ($batch['batch_number'] ?? ''), 'adjustment_quantity' => $delta, 'previous_quantity' => $previous, 'new_quantity' => $new, 'reason' => $reason, 'actor_user_id' => $userId > 0 ? $userId : null, 'actor_clinic_id' => $clinicId, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function result(int $http, string $msg, array $errors = []): array
    {
        return ['http' => $http, 'body' => ['status' => false, 'msg' => $msg] + ($errors === [] ? [] : ['errors' => $errors])];
    }
}
