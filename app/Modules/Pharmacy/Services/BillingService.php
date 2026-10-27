<?php

namespace Pharmacy\Services;

use DateTimeImmutable;

final class BillingService
{
    private const PAYMENT_STATUSES = ['pending', 'authorized', 'paid', 'failed', 'refunded'];

    private const PROVIDERS = ['manual', 'cashfree', 'razorpay'];

    private PaymentStatusCatalog $paymentStatuses;
    private PaymentProviderCatalog $paymentProviders;

    public function __construct(private readonly PharmacyScope $scope, private readonly PaymentProviderBoundary $providers = new PaymentProviderBoundary())
    {
        $this->paymentStatuses = new PaymentStatusCatalog();
        $this->paymentProviders = new PaymentProviderCatalog();
    }

    /**
     * Billing reads authoritative invoice totals while keeping payment-provider
     * credentials and reconciliation transitions on the server boundary.
     */
    public function list(array $input): array
    {
        $clinicId = $this->scope->clinicId();
        $pageSize = (int) ($input['page_size'] ?? 15);
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $page = max(1, (int) ($input['page'] ?? 1));
        $requestedView = (string) ($input['view'] ?? 'list');
        $view = in_array($requestedView, ['list', 'grid'], true) ? $requestedView : 'list';
        $filters = ['search' => trim((string) ($input['search'] ?? '')), 'payment_status' => trim((string) ($input['payment_status'] ?? '')), 'order_status' => trim((string) ($input['order_status'] ?? '')), 'provider' => trim((string) ($input['provider'] ?? '')), 'from' => $this->toIso((string) ($input['from'] ?? '')), 'to' => $this->toIso((string) ($input['to'] ?? '')), 'date_error' => false, 'page_size' => $pageSize];
        $filters['date_error'] = $filters['from'] !== null && $filters['to'] !== null && $filters['to'] < $filters['from'];

        if ($clinicId === null) {
            return $this->emptyPayload($view, $filters, $pageSize);
        }
        $query = db_connect()->table('medicine_orders AS orders')->select('orders.id, orders.order_number, orders.status AS order_status, orders.payment_status, orders.delivery_status, orders.payment_provider, payment_providers.provider_name AS payment_provider_name, orders.payment_reference, orders.last_reconciled_at, orders.created_at, orders.updated_at, patients.registration_number, patients.first_name, patients.middle_name, patients.last_name, patients.mobile_number, patients.country_code, invoices.id AS invoice_id, invoices.invoice_number, invoices.currency, invoices.subtotal, invoices.tax, invoices.discount, invoices.total AS invoice_total, invoices.status AS invoice_status, invoices.payment_status AS invoice_payment_status, invoices.created_at AS invoice_created_at, invoices.updated_at AS invoice_updated_at, (SELECT COUNT(*) FROM medicine_order_items count_items WHERE count_items.order_id = orders.id) AS medicine_count, (SELECT COALESCE(SUM(items.line_total), 0) FROM medicine_order_items items WHERE items.order_id = orders.id) AS order_total')->join('patients', 'patients.id = orders.patient_id')->join('payment_providers', 'payment_providers.provider_code = orders.payment_provider', 'left')->join('invoices', 'invoices.order_id = orders.id', 'left')->where('orders.clinic_id', $clinicId)->where('(invoices.id IS NULL OR invoices.clinic_id = ' . (int) $clinicId . ')', null, false);

        if ($filters['date_error']) {
            $query->where('1 = 0', null, false);
        }

        if (in_array($filters['payment_status'], self::PAYMENT_STATUSES, true)) {
            $query->where('orders.payment_status', $filters['payment_status']);
        }

        if (in_array($filters['order_status'], ['pending', 'fulfilled', 'cancelled'], true)) {
            $query->where('orders.status', $filters['order_status']);
        }

        if (in_array($filters['provider'], self::PROVIDERS, true) || $this->paymentProviders->isActive($filters['provider'])) {
            $query->where('orders.payment_provider', $filters['provider']);
        }

        if (!$filters['date_error'] && $filters['from'] !== null) {
            $query->where('orders.created_at >=', $filters['from'] . ' 00:00:00');
        }

        if (!$filters['date_error'] && $filters['to'] !== null) {
            $query->where('orders.created_at <=', $filters['to'] . ' 23:59:59');
        }

        if ($filters['search'] !== '') {
            $query->groupStart()->like('orders.order_number', $filters['search'])->orLike('invoices.invoice_number', $filters['search'])->orLike('patients.first_name', $filters['search'])->orLike('patients.middle_name', $filters['search'])->orLike('patients.last_name', $filters['search'])->orLike('patients.registration_number', $filters['search'])->orLike('patients.mobile_number', $filters['search'])->orLike('patients.mobile_normalized', $filters['search'])->orLike('orders.payment_reference', $filters['search'])->orLike('orders.payment_provider', $filters['search'])->groupEnd();
        }
        $total = (clone $query)->countAllResults();
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $records = $query->orderBy('orders.id', 'DESC')->get($pageSize, ($page - 1) * $pageSize)->getResultArray();

        return ['rows' => array_map(fn (array $row): array => $this->normalize($row), $records), 'view' => $view, 'filters' => $this->displayFilters($filters), 'pagination' => ['page' => $page, 'pages' => $pages, 'page_size' => $pageSize, 'total' => $total], 'readiness' => ['cashfree' => $this->providers->readiness('cashfree'), 'razorpay' => $this->providers->readiness('razorpay')]];
    }

    public function detail(int $orderId): ?array
    {
        $clinicId = $this->scope->clinicId();

        if ($clinicId === null || $orderId <= 0) {
            return null;
        }
        $row = db_connect()->table('medicine_orders AS orders')->select('orders.*, payment_providers.provider_name AS payment_provider_name, clinics.clinic_name, patients.registration_number, patients.first_name, patients.middle_name, patients.last_name, patients.mobile_number, patients.country_code, invoices.id AS invoice_id, invoices.invoice_number, invoices.currency, invoices.subtotal, invoices.tax, invoices.discount, invoices.total AS invoice_total, invoices.status AS invoice_status, invoices.payment_status AS invoice_payment_status, invoices.created_at AS invoice_created_at, invoices.updated_at AS invoice_updated_at')->join('clinics', 'clinics.id = orders.clinic_id')->join('payment_providers', 'payment_providers.provider_code = orders.payment_provider', 'left')->join('patients', 'patients.id = orders.patient_id')->join('invoices', 'invoices.order_id = orders.id AND invoices.clinic_id = orders.clinic_id', 'left')->where('orders.id', $orderId)->where('orders.clinic_id', $clinicId)->get()->getRowArray();

        if ($row === null) {
            return null;
        }
        $row = $this->normalize($row);
        $row['items'] = db_connect()->table('invoice_items')->where('invoice_id', (int) ($row['invoice_id'] ?? 0))->orderBy('id', 'ASC')->get()->getResultArray();

        return $row;
    }

    public function reconcile(int $orderId, string $status, string $provider, string $reference, string $reason, bool $confirmed): array
    {
        $scope = $this->scope->clinicId();
        $status = strtolower(trim($status));
        $provider = strtolower(trim($provider));
        $reference = trim($reference) !== '' ? trim($reference) : null;
        $reason = trim($reason) !== '' ? trim($reason) : null;

        if ($scope === null) {
            return $this->result(403, 'Pharmacy clinic scope is not assigned.');
        }

        if (!in_array($status, self::PAYMENT_STATUSES, true)) {
            return $this->result(422, 'Payment status is invalid.', ['payment_status' => 'Choose a valid payment status.']);
        }

        if (!$this->paymentStatuses->isActive($status)) {
            return $this->result(422, 'Payment status is inactive or unavailable.', ['payment_status' => 'Choose an active payment status.']);
        }

        if ($provider === '') {
            return $this->result(422, 'Provider is required.', ['provider' => 'Provider is required.']);
        }

        if ($this->paymentProviders->findActive($provider) === null) {
            return $this->result(422, 'Payment provider is invalid.', ['provider' => 'Choose a supported provider.']);
        }

        if (!$confirmed) {
            return $this->result(422, 'Manual reconciliation confirmation is required.', ['confirmation' => 'Confirm that this payment was verified manually.']);
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $order = $db->query('SELECT orders.*, invoices.total AS invoice_total, invoices.id AS invoice_id FROM medicine_orders orders JOIN patients ON patients.id = orders.patient_id LEFT JOIN invoices ON invoices.order_id = orders.id AND invoices.clinic_id = ? WHERE orders.id = ? AND orders.clinic_id = ? LIMIT 1 FOR UPDATE', [$scope, $orderId, $scope])->getRowArray();

            if ($order === null) {
                $db->transRollback();

                return $this->result(404, 'Order not found in the assigned clinic.');
            }

            if ((string) $order['payment_status'] === $status && strtolower(trim((string) ($order['payment_provider'] ?? ''))) === $provider && (string) ($order['payment_reference'] ?? '') === (string) ($reference ?? '')) {
                $db->transCommit();

                return ['http' => 200, 'body' => ['status' => true, 'msg' => 'Payment was already reconciled.', 'data' => ['duplicate' => true]]];
            }

            $updated = $db->table('medicine_orders')->where('id', $orderId)->where('clinic_id', $scope)->where('payment_status', $order['payment_status'])->update(['payment_status' => $status, 'payment_provider' => $provider, 'payment_reference' => $reference, 'last_reconciled_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);

            if (!$updated || !$db->transStatus()) {
                throw new \RuntimeException('Payment update failed.');
            }

            if (!empty($order['invoice_id'])) {
                $invoiceUpdated = $db->table('invoices')->where('id', (int) $order['invoice_id'])->where('clinic_id', $scope)->update(['payment_status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);

                if (!$invoiceUpdated || !$db->transStatus()) {
                    throw new \RuntimeException('Invoice payment status update failed.');
                }
            }
            $db->transCommit();
            $this->scope->audit('pharmacy.payment_reconciled', ['order_id' => $orderId, 'invoice_id' => $order['invoice_id'] ?? null, 'previous_status' => $order['payment_status'], 'new_status' => $status, 'amount' => (float) ($order['invoice_total'] ?? 0), 'provider' => $provider, 'reference' => $reference, 'reason' => $reason, 'reconciliation_source' => 'manual']);
            (new PharmacyNotificationService($this->scope))->create('payment_reconciliation', 'Payment status updated', 'Payment for order #' . $orderId . ' is now ' . $status . '.', 'billing', $orderId, 'payment-reconciled:' . $orderId . ':' . $status . ':' . ($reference ?? ''));
            (new \Patient\Services\PatientNotificationService())->forOrder($orderId, 'payment_status_changed', 'Payment status updated', 'Payment for your medicine order is now ' . $status . '.', 'patient-payment:' . $orderId . ':' . $status . ':' . ($reference ?? ''));

            return ['http' => 200, 'body' => ['status' => true, 'msg' => 'Saved as manual payment reconciliation; no external provider call was made.', 'data' => ['reconciliation_source' => 'manual', 'external_call' => false]]];
        } catch (\Throwable $exception) {
            $db->transRollback();
            log_message('error', 'Pharmacy billing reconciliation failed: ' . $exception->getMessage());

            return $this->result(500, 'Payment status could not be updated safely.');
        }
    }

    public function refund(int $orderId, float $amount, string $provider, string $reference, string $reason, bool $confirmed): array
    {
        $detail = $this->detail($orderId);

        if ($detail === null) {
            return $this->result(404, 'Order not found in the assigned clinic.');
        }

        if ((string) $detail['payment_status'] === 'refunded') {
            return $this->result(422, 'This payment has already been refunded.', ['amount' => 'Duplicate refunds are not allowed.']);
        }
        $paidAmount = (float) ($detail['paid_amount'] ?? $detail['invoice_total'] ?? 0);
        $refundableAmount = (float) ($detail['refundable_amount'] ?? $paidAmount);
        $provider = strtolower(trim($provider));
        $reference = trim($reference) !== '' ? trim($reference) : null;
        $reason = trim($reason) !== '' ? trim($reason) : null;

        if ((string) $detail['payment_status'] !== 'paid') {
            return $this->result(422, 'Only paid orders can be refunded.', ['amount' => 'The order payment is not paid.']);
        }

        if ($amount <= 0 || $amount > $refundableAmount) {
            return $this->result(422, 'Refund amount is invalid.', ['amount' => 'Refund cannot exceed the refundable paid amount of ' . number_format($refundableAmount, 2, '.', '') . '.']);
        }

        if (!$confirmed) {
            return $this->result(422, 'Refund confirmation is required.', ['confirmation' => 'Confirm this refund request.']);
        }

        $providerRecord = $this->paymentProviders->findActive($provider);
        if ($providerRecord === null || ($providerRecord['provider_type'] ?? 'manual') === 'manual') {
            return $this->result(422, 'Payment provider is invalid.', ['provider' => 'Choose a supported refund provider.']);
        }

        if (!$this->providers->isConfigured($provider)) {
            $this->scope->audit('pharmacy.provider_not_configured', ['workflow' => 'refund', 'provider' => $provider, 'order_id' => $orderId, 'amount' => $amount]);
            (new PharmacyNotificationService($this->scope))->create('refund_result', 'Refund could not be processed', 'Refund provider is not configured for order #' . $orderId . '.', 'billing', $orderId, 'refund-result:' . $orderId . ':' . $reference);

            return $this->result(503, 'Provider is not configured; no refund was processed.');
        }
        $this->scope->audit('pharmacy.provider_not_configured', ['workflow' => 'refund_adapter', 'provider' => $provider, 'order_id' => $orderId, 'amount' => $amount]);

        return $this->result(503, 'Provider refund adapter is not enabled; no refund was processed.');
    }

    private function normalize(array $row): array
    {
        $name = trim(implode(' ', array_filter([$row['first_name'] ?? '', $row['middle_name'] ?? '', $row['last_name'] ?? ''])));
        $amount = (float) ($row['invoice_total'] ?? $row['order_total'] ?? 0);
        $paymentStatus = (string) ($row['payment_status'] ?? 'unavailable');
        $paidAmount = in_array($paymentStatus, ['paid', 'refunded'], true) ? $amount : 0.0;
        $refundedAmount = $paymentStatus === 'refunded' ? $amount : 0.0;

        return ['id' => (int) $row['id'], 'order_number' => (string) ($row['order_number'] ?? 'Not recorded'), 'patient_name' => $name ?: 'Patient not recorded', 'patient_number' => (string) ($row['registration_number'] ?? 'Not recorded'), 'patient_mobile' => trim(implode(' ', array_filter([(string) ($row['country_code'] ?? ''), (string) ($row['mobile_number'] ?? '')])) ?: 'Not recorded'), 'medicine_count' => (int) ($row['medicine_count'] ?? 0), 'invoice_id' => $row['invoice_id'] === null ? null : (int) $row['invoice_id'], 'invoice_number' => (string) ($row['invoice_number'] ?? 'Unavailable'), 'amount' => $amount, 'subtotal' => (float) ($row['subtotal'] ?? $amount), 'tax' => (float) ($row['tax'] ?? 0), 'discount' => (float) ($row['discount'] ?? 0), 'paid_amount' => $paidAmount, 'refunded_amount' => $refundedAmount, 'refundable_amount' => max(0.0, $paidAmount - $refundedAmount), 'currency' => (string) ($row['currency'] ?? 'INR'), 'invoice_status' => (string) ($row['invoice_status'] ?? 'unavailable'), 'payment_status' => $paymentStatus, 'delivery_status' => (string) ($row['delivery_status'] ?? ''), 'payment_reference' => (string) ($row['payment_reference'] ?? 'Not recorded'), 'provider' => (string) ($row['payment_provider'] ?? 'Not recorded'), 'provider_name' => (string) ($row['payment_provider_name'] ?? $row['payment_provider'] ?? 'Not recorded'), 'order_status' => (string) ($row['order_status'] ?? ''), 'created_at' => $this->displayDate($row['created_at'] ?? ''), 'updated_at' => $this->displayDate($row['updated_at'] ?? ''), 'invoice_created_at' => $this->displayDate($row['invoice_created_at'] ?? ''), 'invoice_updated_at' => $this->displayDate($row['invoice_updated_at'] ?? '')];
    }

    private function displayDate(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value) ?: DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));

        return $date ? $date->format('d-m-Y') : '';
    }

    private function emptyPayload(string $view, array $filters, int $pageSize): array
    {
        return ['rows' => [], 'view' => $view, 'filters' => $this->displayFilters($filters), 'pagination' => ['page' => 1, 'pages' => 1, 'page_size' => $pageSize, 'total' => 0], 'readiness' => []];
    }

    private function toIso(string $value): ?string
    {
        if ($value === '') {
            return null;
        } $date = DateTimeImmutable::createFromFormat('!d-m-Y', $value);

        return $date !== false && $date->format('d-m-Y') === $value ? $date->format('Y-m-d') : null;
    }

    private function displayFilters(array $filters): array
    {
        foreach (['from', 'to'] as $key) {
            if ($filters[$key]) {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$key]);
                $filters[$key] = $date ? $date->format('d-m-Y') : '';
            }
        }

        return $filters;
    }

    private function result(int $http, string $msg, array $errors = []): array
    {
        return ['http' => $http, 'body' => ['status' => false, 'msg' => $msg] + ($errors ? ['errors' => $errors] : [])];
    }
}
