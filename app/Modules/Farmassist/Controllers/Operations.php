<?php

namespace Farmassist\Controllers;

use Admin\Models\MedicineInventoryBatchModel;
use Admin\Models\MedicineModel;
use Admin\Models\MedicineOrderItemModel;
use Admin\Models\MedicineOrderModel;
use Admin\Models\PrescriptionItemModel;
use Admin\Models\PrescriptionModel;
use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;
class Operations extends BaseController
{
    private const PAYMENT_PROVIDERS = ['cashfree', 'razorpay'];
    private const DELIVERY_TRANSITIONS = ['pending' => ['packed', 'failed'], 'packed' => ['dispatched', 'failed'], 'dispatched' => ['out_for_delivery', 'failed'], 'out_for_delivery' => ['delivered', 'failed'], 'failed' => ['returned'], 'delivered' => ['returned'], 'returned' => []];
    protected MedicineOrderModel $orders;
    protected MedicineOrderItemModel $orderItems;
    protected PrescriptionModel $prescriptions;
    protected PrescriptionItemModel $prescriptionItems;
    protected MedicineModel $medicines;
    protected MedicineInventoryBatchModel $batches;
    public function __construct()
    {
        $this->orders = new MedicineOrderModel();
        $this->orderItems = new MedicineOrderItemModel();
        $this->prescriptions = new PrescriptionModel();
        $this->prescriptionItems = new PrescriptionItemModel();
        $this->medicines = new MedicineModel();
        $this->batches = new MedicineInventoryBatchModel();
    }
    public function index(): string|RedirectResponse
    {
        $clinicId = $this->clinicId();
        if ($clinicId === null) {
            return redirect()->to(base_url('permission-denied'));
        }
        $db = db_connect();
        $orderPageSize = (int) ($this->request->getGet('order_page_size') ?? 15);
        $orderPageSize = in_array($orderPageSize, [10, 15, 25, 50], true) ? $orderPageSize : 15;
        $orderPage = max(1, (int) ($this->request->getGet('order_page') ?? 1));
        $orderSearch = trim((string) $this->request->getGet('order_search'));
        $orderDeliveryStatus = trim((string) $this->request->getGet('delivery_status'));
        $approvedPrescriptions = $db->table('prescriptions')->select('prescriptions.id, prescriptions.prescription_number, prescriptions.prescription_date, patients.registration_number, patients.first_name, patients.middle_name, patients.last_name')->join('patients', 'patients.id = prescriptions.patient_id')->join('doctors', 'doctors.id = prescriptions.doctor_id')->join('medicine_orders', 'medicine_orders.prescription_id = prescriptions.id', 'left')->where('prescriptions.clinic_id', $clinicId)->where('patients.clinic_id', $clinicId)->where('doctors.clinic_id', $clinicId)->where('prescriptions.status', 'approved')->where('medicine_orders.id', null)->orderBy('prescriptions.prescription_date', 'ASC')->limit(25)->get()->getResultArray();
        $orderQuery = $db->table('medicine_orders')->select('medicine_orders.id, medicine_orders.order_number, medicine_orders.status, medicine_orders.payment_status, medicine_orders.delivery_status, medicine_orders.created_at, patients.registration_number, patients.first_name, patients.middle_name, patients.last_name')->join('patients', 'patients.id = medicine_orders.patient_id')->where('medicine_orders.clinic_id', $clinicId)->where('patients.clinic_id', $clinicId);
        if ($orderSearch !== '') {
            $orderQuery->groupStart()->like('medicine_orders.order_number', $orderSearch)->orLike('patients.registration_number', $orderSearch)->orLike('patients.first_name', $orderSearch)->orLike('patients.last_name', $orderSearch)->groupEnd();
        }
        if (in_array($orderDeliveryStatus, ['pending', 'packed', 'dispatched', 'out_for_delivery', 'delivered', 'failed', 'returned'], true)) {
            $orderQuery->where('medicine_orders.delivery_status', $orderDeliveryStatus);
        }
        $orderTotal = (clone $orderQuery)->countAllResults();
        $orderPages = max(1, (int) ceil($orderTotal / $orderPageSize));
        $orderPage = min($orderPage, $orderPages);
        $orders = $orderQuery->orderBy('medicine_orders.id', 'DESC')->limit($orderPageSize, ($orderPage - 1) * $orderPageSize)->get()->getResultArray();
        return view(FARMASSIST_VIEW . 'pages/dashboard', ['clinicId' => $clinicId, 'reviewCount' => $db->table('prescriptions')->where('clinic_id', $clinicId)->whereIn('status', ['submitted', 'clarification_requested'])->countAllResults(), 'orderCount' => $this->orders->where('clinic_id', $clinicId)->countAllResults(), 'pendingDelivery' => $this->orders->where('clinic_id', $clinicId)->whereIn('delivery_status', ['pending', 'packed', 'dispatched', 'out_for_delivery'])->countAllResults(), 'auditAlerts' => $db->table('audit_logs')->where('clinic_id', $clinicId)->where('occurred_at >=', date('Y-m-d 00:00:00'))->groupStart()->like('action', 'failed')->orLike('action', 'denied')->groupEnd()->countAllResults(), 'approvedPrescriptions' => $approvedPrescriptions, 'orders' => $orders, 'orderFilters' => ['search' => $orderSearch, 'delivery_status' => $orderDeliveryStatus], 'orderPagination' => ['page' => $orderPage, 'pages' => $orderPages, 'page_size' => $orderPageSize, 'total' => $orderTotal]]);
    }
    public function createOrder(): ResponseInterface
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
        }
        $clinicId = $this->clinicId();
        $prescriptionId = (int) $this->request->getPost('prescription_id');
        if ($clinicId === null) {
            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'Farmassist clinic scope is not assigned.']);
        }
        if ($prescriptionId <= 0) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => 'A valid prescription is required.', 'errors' => ['prescription_id' => 'Prescription is required.']]);
        }
        $prescription = $this->prescriptions->select('prescriptions.*')->join('patients', 'patients.id = prescriptions.patient_id')->join('doctors', 'doctors.id = prescriptions.doctor_id')->where('prescriptions.id', $prescriptionId)->where('prescriptions.clinic_id', $clinicId)->where('patients.clinic_id', $clinicId)->where('doctors.clinic_id', $clinicId)->first();
        if ($prescription === null || $prescription['status'] !== 'approved') {
            return $this->response->setStatusCode(409)->setJSON(['status' => false, 'msg' => 'Only an approved prescription in the assigned clinic can create an order.']);
        }
        $idempotencyKey = 'prescription:' . $prescriptionId;
        $existing = $this->orders->where('idempotency_key', $idempotencyKey)->where('clinic_id', $clinicId)->first();
        if ($existing !== null) {
            return $this->response->setJSON(['status' => true, 'msg' => 'Medicine order already exists.', 'data' => ['order_id' => (int) $existing['id'], 'order_number' => $existing['order_number'], 'duplicate' => true]]);
        }
        $items = $this->prescriptionItems->where('prescription_id', $prescriptionId)->orderBy('id', 'ASC')->findAll();
        $allocations = [];
        $medicineMap = [];
        foreach ($this->medicines->where('status', 1)->findAll() as $medicine) {
            $medicineMap[strtolower(trim((string) $medicine['medicine_name']))] = $medicine;
        }
        foreach ($items as $index => $item) {
            $name = trim((string) ($item['medicine_name'] ?? ''));
            $quantity = (int) ($item['quantity'] ?? 0);
            $medicine = $medicineMap[strtolower($name)] ?? null;
            if ($medicine === null || $quantity <= 0) {
                return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => 'Order cannot be created because a prescribed medicine is unavailable or has no valid quantity.', 'errors' => ['items.' . $index => 'Medicine master and quantity must be valid.']]);
            }
            $remaining = $quantity;
            $batches = $this->batches->where('clinic_id', $clinicId)->where('medicine_id', (int) $medicine['id'])->where('status', 'active')->where('expiry_date >=', date('Y-m-d'))->where('quantity_available >', 0)->orderBy('expiry_date', 'ASC')->findAll();
            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }
                $allocated = min($remaining, (int) $batch['quantity_available']);
                $allocations[] = ['item' => $item, 'medicine' => $medicine, 'batch' => $batch, 'quantity' => $allocated];
                $remaining -= $allocated;
            }
            if ($remaining > 0) {
                return $this->response->setStatusCode(409)->setJSON(['status' => false, 'msg' => 'Order cannot be created because active, unexpired stock is insufficient.', 'errors' => ['items.' . $index => 'Unavailable or expired stock.']]);
            }
        }
        $db = db_connect();
        $db->transBegin();
        try {
            $orderNumber = 'MO-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $orderId = $this->orders->insert(['order_number' => $orderNumber, 'prescription_id' => $prescriptionId, 'patient_id' => (int) $prescription['patient_id'], 'clinic_id' => $clinicId, 'status' => 'pending', 'payment_status' => 'pending', 'delivery_status' => 'pending', 'idempotency_key' => $idempotencyKey], true);
            if (!$orderId) {
                throw new \RuntimeException('Order could not be created.');
            }
            foreach ($allocations as $allocation) {
                $batch = $allocation['batch'];
                $updated = $this->batches->where('id', (int) $batch['id'])->where('clinic_id', $clinicId)->where('status', 'active')->where('expiry_date >=', date('Y-m-d'))->where('quantity_available >=', $allocation['quantity'])->set('quantity_available', 'quantity_available - ' . (int) $allocation['quantity'], false)->update();
                if (!$updated) {
                    throw new \RuntimeException('Inventory changed while creating the order.');
                }
                $item = $allocation['item'];
                $this->orderItems->insert(['order_id' => (int) $orderId, 'prescription_item_id' => (int) $item['id'], 'medicine_id' => (int) $allocation['medicine']['id'], 'medicine_name' => $item['medicine_name'], 'batch_number' => $batch['batch_number'], 'dosage' => $item['dosage'] ?? null, 'frequency' => $item['frequency'] ?? null, 'duration' => $item['duration'] ?? null, 'duration_unit' => $item['duration_unit'] ?? null, 'instructions' => $item['instructions'] ?? null, 'quantity' => $allocation['quantity']]);
            }
            if ($db->transStatus() === false) {
                throw new \RuntimeException('Order transaction failed.');
            }
            $db->transCommit();
            $this->audit('farmassist.order_created', $clinicId, ['order_id' => (int) $orderId, 'prescription_id' => $prescriptionId]);
            return $this->response->setJSON(['status' => true, 'msg' => 'Medicine order created.', 'data' => ['order_id' => (int) $orderId, 'order_number' => $orderNumber]]);
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Farmassist order creation failed: ' . $e->getMessage());
            $existing = $this->orders->where('idempotency_key', $idempotencyKey)->where('clinic_id', $clinicId)->first();
            if ($existing !== null) {
                return $this->response->setJSON(['status' => true, 'msg' => 'Medicine order already exists.', 'data' => ['order_id' => (int) $existing['id'], 'order_number' => $existing['order_number'], 'duplicate' => true]]);
            }
            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Medicine order could not be created safely.']);
        }
    }
    public function reconcilePayment(): ResponseInterface
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
        }
        $clinicId = $this->clinicId();
        $orderId = (int) $this->request->getPost('order_id');
        $status = strtolower(trim((string) $this->request->getPost('payment_status')));
        $provider = strtolower(trim((string) $this->request->getPost('provider')));
        $reference = trim((string) $this->request->getPost('reference'));
        if ($clinicId === null) {
            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'Farmassist clinic scope is not assigned.']);
        }
        if (!in_array($status, ['pending', 'authorized', 'paid', 'failed', 'refunded'], true) || !in_array($provider, self::PAYMENT_PROVIDERS, true) || $reference === '' || strlen($reference) > 150) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => 'Payment status, provider, and reference are required.']);
        }
        $order = $this->orders->where('id', $orderId)->where('clinic_id', $clinicId)->first();
        if ($order === null) {
            return $this->response->setStatusCode(404)->setJSON(['status' => false, 'msg' => 'Order not found in the assigned clinic.']);
        }
        if ($order['payment_status'] === $status && $order['payment_provider'] === $provider && $order['payment_reference'] === $reference) {
            return $this->response->setJSON(['status' => true, 'msg' => 'Payment status was already reconciled.', 'data' => ['duplicate' => true]]);
        }
        try {
            $updated = $this->orders->update($orderId, ['payment_status' => $status, 'payment_provider' => $provider, 'payment_reference' => $reference, 'last_reconciled_at' => date('Y-m-d H:i:s')]);
        } catch (Throwable $e) {
            log_message('error', 'Farmassist payment reconciliation failed: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Payment status could not be reconciled safely.']);
        }
        if (!$updated) {
            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Payment status could not be reconciled safely.']);
        }
        $this->audit('farmassist.payment_reconciled', $clinicId, ['order_id' => $orderId, 'payment_status' => $status, 'provider' => $provider]);
        return $this->response->setJSON(['status' => true, 'msg' => 'Payment status reconciled.']);
    }
    public function updateDelivery(): ResponseInterface
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
        }
        $clinicId = $this->clinicId();
        $orderId = (int) $this->request->getPost('order_id');
        $target = strtolower(trim((string) $this->request->getPost('delivery_status')));
        if ($clinicId === null) {
            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'Farmassist clinic scope is not assigned.']);
        }
        $order = $this->orders->where('id', $orderId)->where('clinic_id', $clinicId)->first();
        if ($order === null || !array_key_exists($target, self::DELIVERY_TRANSITIONS)) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => 'Order and delivery status are invalid.']);
        }
        if ($order['delivery_status'] === $target) {
            return $this->response->setJSON(['status' => true, 'msg' => 'Delivery status was already recorded.', 'data' => ['duplicate' => true]]);
        }
        if (!in_array($target, self::DELIVERY_TRANSITIONS[$order['delivery_status']] ?? [], true)) {
            return $this->response->setStatusCode(409)->setJSON(['status' => false, 'msg' => 'Delivery status transition is not allowed.']);
        }
        $fields = ['delivery_status' => $target];
        if ($target === 'delivered') {
            $fields['status'] = 'fulfilled';
        }
        try {
            $updated = $this->orders->update($orderId, $fields);
        } catch (Throwable $e) {
            log_message('error', 'Farmassist delivery update failed: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Delivery status could not be updated safely.']);
        }
        if (!$updated) {
            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Delivery status could not be updated safely.']);
        }
        $this->audit('farmassist.delivery_updated', $clinicId, ['order_id' => $orderId, 'delivery_status' => $target]);
        return $this->response->setJSON(['status' => true, 'msg' => 'Delivery status updated.']);
    }
    private function clinicId(): ?int
    {
        if (!in_array('FarmAssist', session('roles') ?? [], true)) {
            return null;
        }
        $user = session('user') ?? [];
        $clinicId = (int) ($user['clinic_id'] ?? session('clinic_id') ?? 0);
        return $clinicId > 0 ? $clinicId : null;
    }
    /** @param array<string, mixed> $context */
    private function audit(string $action, int $clinicId, array $context): void
    {
        service('audit')->record($action, array_merge(['clinic_id' => $clinicId], $context));
    }
}
