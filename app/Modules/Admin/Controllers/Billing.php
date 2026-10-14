<?php

namespace Admin\Controllers;

use Admin\Models\InvoiceItemModel;
use Admin\Models\InvoiceModel;
use App\Controllers\BaseAdminController;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class Billing extends BaseAdminController
{
    public function index(): string
    {
        $query = db_connect()->table('invoices')->select('invoices.*, patients.registration_number, patients.first_name, patients.last_name, clinics.clinic_name')->join('patients', 'patients.id = invoices.patient_id', 'left')->join('clinics', 'clinics.id = invoices.clinic_id', 'left');
        $search = trim((string) $this->request->getGet('search'));
        $status = trim((string) $this->request->getGet('status'));
        $paymentStatus = trim((string) $this->request->getGet('payment_status'));
        $clinicId = (int) $this->request->getGet('clinic_id');
        $dateFrom = trim((string) $this->request->getGet('date_from'));
        $dateTo = trim((string) $this->request->getGet('date_to'));
        $pageSize = (int) $this->request->getGet('page_size');
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $this->scope($query, 'invoices.clinic_id');

        if ($search !== '') {
            $query->groupStart()->like('invoices.invoice_number', $search)->orLike('patients.registration_number', $search)->orLike('patients.first_name', $search)->orLike('patients.last_name', $search)->orLike('clinics.clinic_name', $search)->groupEnd();
        }

        if (in_array($status, ['draft', 'finalized'], true)) {
            $query->where('invoices.status', $status);
        }

        if (in_array($paymentStatus, ['pending', 'authorized', 'paid', 'failed', 'refunded'], true)) {
            $query->where('invoices.payment_status', $paymentStatus);
        }

        if ($clinicId > 0) {
            $query->where('invoices.clinic_id', $clinicId);
        }

        if ($dateFrom !== '') {
            $query->where('invoices.created_at >=', $dateFrom . ' 00:00:00');
        }

        if ($dateTo !== '') {
            $query->where('invoices.created_at <=', $dateTo . ' 23:59:59');
        }
        $invoices = $query->orderBy('invoices.id', 'DESC');
        $total = (clone $invoices)->countAllResults();
        $invoices = $invoices->get($pageSize, max(0, ((int) $this->request->getGet('page_invoices') - 1) * $pageSize))->getResultArray();
        $summary = ['count' => $total, 'drafts' => count(array_filter($invoices, static fn (array $invoice): bool => ($invoice['status'] ?? '') === 'draft')), 'finalized' => count(array_filter($invoices, static fn (array $invoice): bool => ($invoice['status'] ?? '') === 'finalized')), 'total' => array_sum(array_map(static fn (array $invoice): float => (float) ($invoice['total'] ?? 0), $invoices))];
        $pager = service('pager');
        $pager->makeLinks((int) $this->request->getGet('page_invoices') ?: 1, $pageSize, $total, 'default_full', 0, 'invoices');

        return view(ADMIN_VIEW . 'pages/billing/index', ['invoices' => $invoices, 'summary' => $summary, 'pager' => $pager, 'filters' => ['search' => $search, 'status' => $status, 'payment_status' => $paymentStatus, 'clinic_id' => $clinicId ?: '', 'date_from' => $dateFrom, 'date_to' => $dateTo, 'page_size' => $pageSize], 'clinics' => (new \Admin\Models\Clinic\ClinicModel())->dropdown()]);
    }

    public function newBill(): string
    {
        return view(ADMIN_VIEW . 'pages/billing/new', ['clinics' => (new \Admin\Models\Clinic\ClinicModel())->dropdown()]);
    }

    public function edit(?int $invoiceId = null)
    {
        if ($invoiceId === null) {
            $query = db_connect()->table('invoices')->select('invoices.*, patients.registration_number, patients.first_name, patients.last_name, clinics.clinic_name')->join('patients', 'patients.id = invoices.patient_id', 'left')->join('clinics', 'clinics.id = invoices.clinic_id', 'left')->where('invoices.status', 'draft')->orderBy('invoices.id', 'DESC');
            $this->scope($query, 'invoices.clinic_id');

            return view(ADMIN_VIEW . 'pages/billing/edit_list', ['invoices' => $query->get()->getResultArray()]);
        }
        $invoice = (new InvoiceModel())->where('id', $invoiceId)->first();

        if ($invoice === null || !$this->inScope((int) $invoice['clinic_id']) || $invoice['status'] !== 'draft') {
            return redirect()->to(base_url('admin/billing'))->with('error', 'Only permitted draft invoices can be edited.');
        }

        return view(ADMIN_VIEW . 'pages/billing/edit', ['invoice' => $invoice, 'items' => (new InvoiceItemModel())->where('invoice_id', $invoiceId)->findAll()]);
    }

    public function update(int $invoiceId): ResponseInterface
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->json(405, false, 'Invalid request.');
        }
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->where('id', $invoiceId)->first();

        if ($invoice === null || !$this->inScope((int) $invoice['clinic_id'])) {
            return $this->json(404, false, 'Invoice not found in the permitted scope.');
        }

        if ($invoice['status'] !== 'draft') {
            return $this->json(409, false, 'Finalized invoices cannot be edited.');
        }
        $items = $this->normalizeItems($this->request->getPost('items'));

        if ($items['errors'] !== []) {
            return $this->validation($items['errors']);
        }
        $tax = max(0, (float) $this->request->getPost('tax'));
        $discount = max(0, (float) $this->request->getPost('discount'));

        if ($discount > $items['subtotal'] + $tax) {
            return $this->json(422, false, 'Discount cannot exceed the invoice amount.');
        }
        $db = db_connect();
        $db->transBegin();

        try {
            if (!$invoiceModel->update($invoiceId, ['subtotal' => $items['subtotal'], 'tax' => $tax, 'discount' => $discount, 'total' => round($items['subtotal'] + $tax - $discount, 2)])) {
                throw new \RuntimeException('Invoice update failed.');
            }
            $lineModel = new InvoiceItemModel();
            $lineModel->where('invoice_id', $invoiceId)->delete();

            foreach ($items['rows'] as $line) {
                if (!$lineModel->insert(['invoice_id' => $invoiceId, 'description' => $line['description'], 'quantity' => $line['quantity'], 'unit_price' => $line['unitPrice'], 'line_total' => $line['lineTotal']])) {
                    throw new \RuntimeException('Invoice item update failed.');
                }
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Invoice transaction failed.');
            }
            $db->transCommit();
            $this->audit->record('admin.billing_invoice_updated', ['invoice_id' => $invoiceId, 'clinic_id' => (int) $invoice['clinic_id']]);

            return $this->json(200, true, 'Invoice draft updated.');
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Invoice update failed: ' . $e->getMessage());

            return $this->json(500, false, 'Invoice could not be updated safely.');
        }
    }

    public function create(): ResponseInterface
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->json(405, false, 'Invalid request.');
        }
        $clinicId = (int) $this->request->getPost('clinic_id');
        $patientId = (int) $this->request->getPost('patient_id');
        $appointmentId = (int) $this->request->getPost('appointment_id') ?: null;
        $items = $this->request->getPost('items');
        $errors = [];

        if ($clinicId <= 0 || db_connect()->table('clinics')->where('id', $clinicId)->where('status', 1)->get()->getRowArray() === null) {
            $errors['clinic_id'] = 'An active clinic is required.';
        }
        $patient = $patientId > 0 ? db_connect()->table('patients')->where('id', $patientId)->where('clinic_id', $clinicId)->get()->getRowArray() : null;

        if ($patient === null) {
            $errors['patient_id'] = 'A patient in the selected clinic is required.';
        }

        if (!is_array($items) || $items === []) {
            $errors['items'] = 'At least one invoice item is required.';
        }

        if ($errors !== []) {
            return $this->validation($errors);
        }
        $normalized = [];
        $subtotal = 0.0;

        foreach ($items as $index => $item) {
            $description = trim((string) ($item['description'] ?? ''));
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? -1);

            if ($description === '' || $quantity <= 0 || $unitPrice < 0) {
                return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => 'Invoice item values are invalid.', 'errors' => ['items.' . $index => 'Description, quantity, and non-negative price are required.']]);
            }
            $lineTotal = round($quantity * $unitPrice, 2);
            $normalized[] = compact('description', 'quantity', 'unitPrice', 'lineTotal');
            $subtotal += $lineTotal;
        }
        $tax = max(0, (float) $this->request->getPost('tax'));
        $discount = max(0, (float) $this->request->getPost('discount'));

        if ($discount > $subtotal + $tax) {
            return $this->json(422, false, 'Discount cannot exceed the invoice amount.');
        }
        $total = round($subtotal + $tax - $discount, 2);
        $key = trim((string) $this->request->getPost('idempotency_key'));

        if ($key !== '') {
            $existing = (new InvoiceModel())->where('idempotency_key', $key)->where('clinic_id', $clinicId)->first();

            if ($existing !== null) {
                return $this->json(200, true, 'Invoice already exists.', ['invoice_id' => (int) $existing['id'], 'duplicate' => true]);
            }
        }
        $db = db_connect();
        $db->transBegin();

        try {
            $invoice = new InvoiceModel();
            $invoiceId = $invoice->insert(['invoice_number' => 'INV-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(2))), 'clinic_id' => $clinicId, 'patient_id' => $patientId, 'appointment_id' => $appointmentId, 'currency' => 'INR', 'subtotal' => $subtotal, 'tax' => $tax, 'discount' => $discount, 'total' => $total, 'status' => 'draft', 'payment_status' => 'pending', 'idempotency_key' => $key ?: null, 'created_by' => (int) ($this->user['id'] ?? 0)], true);

            if (!$invoiceId) {
                throw new \RuntimeException('Invoice insert failed.');
            }
            $lineModel = new InvoiceItemModel();

            foreach ($normalized as $line) {
                if (!$lineModel->insert(['invoice_id' => $invoiceId, 'description' => $line['description'], 'quantity' => $line['quantity'], 'unit_price' => $line['unitPrice'], 'line_total' => $line['lineTotal']])) {
                    throw new \RuntimeException('Invoice item insert failed.');
                }
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Invoice transaction failed.');
            }
            $db->transCommit();
            $this->audit->record('admin.billing_invoice_created', ['invoice_id' => $invoiceId, 'clinic_id' => $clinicId, 'patient_id' => $patientId]);

            return $this->json(200, true, 'Invoice created.', ['invoice_id' => (int) $invoiceId]);
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Invoice creation failed: ' . $e->getMessage());

            return $this->json(500, false, 'Invoice could not be created safely.');
        }
    }

    public function finalize(int $invoiceId): ResponseInterface
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->json(405, false, 'Invalid request.');
        }
        $invoice = (new InvoiceModel())->where('id', $invoiceId)->first();

        if ($invoice === null || !$this->inScope((int) $invoice['clinic_id'])) {
            return $this->json(404, false, 'Invoice not found in the permitted scope.');
        }

        if ($invoice['status'] === 'finalized') {
            return $this->json(200, true, 'Invoice was already finalized.', ['duplicate' => true]);
        }

        if ($invoice['status'] !== 'draft') {
            return $this->json(409, false, 'Only draft invoices can be finalized.');
        }

        if (!(new InvoiceModel())->update($invoiceId, ['status' => 'finalized'])) {
            return $this->json(500, false, 'Invoice could not be finalized safely.');
        }
        $this->audit->record('admin.billing_invoice_finalized', ['invoice_id' => $invoiceId, 'clinic_id' => (int) $invoice['clinic_id']]);

        return $this->json(200, true, 'Invoice finalized.');
    }

    public function payment(int $invoiceId): ResponseInterface
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->json(405, false, 'Invalid request.');
        }
        $status = strtolower(trim((string) $this->request->getPost('payment_status')));

        if (!in_array($status, ['pending', 'authorized', 'paid', 'failed', 'refunded'], true)) {
            return $this->json(422, false, 'Payment status is invalid.');
        }
        $invoice = (new InvoiceModel())->where('id', $invoiceId)->first();

        if ($invoice === null || !$this->inScope((int) $invoice['clinic_id'])) {
            return $this->json(404, false, 'Invoice not found in the permitted scope.');
        }

        if ($invoice['payment_status'] === $status) {
            return $this->json(200, true, 'Payment status was already recorded.', ['duplicate' => true]);
        }

        if ($invoice['status'] !== 'finalized') {
            return $this->json(409, false, 'Only finalized invoices can receive payment updates.');
        }

        if (!(new InvoiceModel())->update($invoiceId, ['payment_status' => $status])) {
            return $this->json(500, false, 'Payment status could not be updated safely.');
        }
        $this->audit->record('admin.billing_payment_updated', ['invoice_id' => $invoiceId, 'clinic_id' => (int) $invoice['clinic_id'], 'payment_status' => $status]);

        return $this->json(200, true, 'Payment status updated.');
    }

    private function inScope(int $clinicId): bool
    {
        $userClinic = (int) ($this->user['clinic_id'] ?? session('clinic_id') ?? 0);

        return $userClinic <= 0 || $userClinic === $clinicId;
    }

    private function normalizeItems($items): array
    {
        if (!is_array($items) || $items === []) {
            return ['rows' => [], 'subtotal' => 0.0, 'errors' => ['items' => 'At least one invoice item is required.']];
        }
        $rows = [];
        $subtotal = 0.0;

        foreach ($items as $index => $item) {
            $description = trim((string) ($item['description'] ?? ''));
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? -1);

            if ($description === '' || $quantity <= 0 || $unitPrice < 0) {
                return ['rows' => [], 'subtotal' => 0.0, 'errors' => ['items.' . $index => 'Description, quantity, and non-negative price are required.']];
            }
            $lineTotal = round($quantity * $unitPrice, 2);
            $rows[] = compact('description', 'quantity', 'unitPrice', 'lineTotal');
            $subtotal += $lineTotal;
        }

        return ['rows' => $rows, 'subtotal' => round($subtotal, 2), 'errors' => []];
    }

    private function scope($query, string $column): void
    {
        $clinicId = (int) ($this->user['clinic_id'] ?? session('clinic_id') ?? 0);

        if ($clinicId > 0) {
            $query->where($column, $clinicId);
        }
    }

    private function validation(array $errors): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => (string) reset($errors), 'errors' => $errors]);
    }

    private function json(int $code, bool $status, string $message, array $data = []): ResponseInterface
    {
        $payload = ['status' => $status, 'msg' => $message];

        if ($data !== []) {
            $payload['data'] = $data;
        }

        return $this->response->setStatusCode($code)->setJSON($payload);
    }
}
