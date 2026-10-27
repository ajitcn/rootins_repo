<?php

namespace Pharmacy\Services;

use DateTimeImmutable;

final class DeliveryService
{
    private const PROVIDERS = ['manual', 'internal'];
    private const DELIVERY_STATUS_ORDER = ['pending', 'packed', 'dispatched', 'out_for_delivery', 'delivered', 'failed', 'returned'];
    private DeliveryProviderCatalog $catalog;

    public function __construct(private readonly PharmacyScope $scope, private readonly DeliveryProviderBoundary $providers = new DeliveryProviderBoundary())
    {
        $this->catalog = new DeliveryProviderCatalog();
    }

    /**
     * Delivery status changes are validated by the shared transition contract;
     * provider readiness never implies a successful external delivery call.
     */
    public function list(array $input): array
    {
        $clinicId = $this->scope->clinicId();
        $pageSize = (int) ($input['page_size'] ?? 15);
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $page = max(1, (int) ($input['page'] ?? 1));
        $requestedView = (string) ($input['view'] ?? 'list');
        $view = in_array($requestedView, ['list', 'grid'], true) ? $requestedView : 'list';
        $filters = ['search' => trim((string) ($input['search'] ?? '')), 'delivery_status' => trim((string) ($input['delivery_status'] ?? '')), 'provider' => trim((string) ($input['provider'] ?? '')), 'from' => $this->toIso((string) ($input['from'] ?? '')), 'to' => $this->toIso((string) ($input['to'] ?? '')), 'page_size' => $pageSize];

        if ($clinicId === null) {
            return $this->emptyPayload($view, $filters, $pageSize);
        }
        $query = db_connect()->table('medicine_orders AS orders')->select('orders.id, orders.order_number, orders.prescription_id, orders.status AS order_status, orders.payment_status, orders.delivery_status, orders.delivery_provider, orders.delivery_provider_id, orders.delivery_provider_name_snapshot, providers.provider_name, providers.tracking_url_template, orders.tracking_reference, orders.delivery_address_snapshot, orders.delivery_contact_snapshot, orders.dispatched_at, orders.delivered_at, orders.failure_reason, orders.return_reason, orders.created_at, orders.updated_at, prescriptions.prescription_number, prescriptions.prescription_date, patients.registration_number, patients.first_name, patients.middle_name, patients.last_name, patients.mobile_number, patients.country_code, (SELECT COUNT(*) FROM medicine_order_items items WHERE items.order_id = orders.id) AS medicine_count')->join('patients', 'patients.id = orders.patient_id')->join('prescriptions', 'prescriptions.id = orders.prescription_id')->join('delivery_providers providers', 'providers.id = orders.delivery_provider_id', 'left')->where('orders.clinic_id', $clinicId)->where('prescriptions.clinic_id', $clinicId);

        if (array_key_exists($filters['delivery_status'], TransitionContract::DELIVERY)) {
            $query->where('orders.delivery_status', $filters['delivery_status']);
        }

        if ($filters['provider'] !== '') {
            $query->groupStart()->like('orders.delivery_provider', $filters['provider'])->orLike('providers.provider_name', $filters['provider'])->orLike('providers.provider_code', $filters['provider'])->groupEnd();
        }

        if ($filters['from'] !== null) {
            $query->where('orders.created_at >=', $filters['from'] . ' 00:00:00');
        }

        if ($filters['to'] !== null) {
            $query->where('orders.created_at <=', $filters['to'] . ' 23:59:59');
        }

        if ($filters['search'] !== '') {
            $query->groupStart()->like('orders.order_number', $filters['search'])->orLike('prescriptions.prescription_number', $filters['search'])->orLike('patients.first_name', $filters['search'])->orLike('patients.middle_name', $filters['search'])->orLike('patients.last_name', $filters['search'])->orLike('patients.registration_number', $filters['search'])->orLike('patients.mobile_number', $filters['search'])->orLike('patients.mobile_normalized', $filters['search'])->orLike('orders.delivery_provider', $filters['search'])->orLike('providers.provider_name', $filters['search'])->orLike('providers.provider_code', $filters['search'])->orLike('orders.tracking_reference', $filters['search'])->orLike('orders.delivery_status', $filters['search'])->groupEnd();
        }
        $total = (clone $query)->countAllResults();
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $records = $query->orderBy('orders.id', 'DESC')->get($pageSize, ($page - 1) * $pageSize)->getResultArray();

        return ['rows' => array_map(fn (array $row): array => $this->normalize($row), $records), 'view' => $view, 'filters' => $this->displayFilters($filters), 'pagination' => ['page' => $page, 'pages' => $pages, 'page_size' => $pageSize, 'total' => $total], 'readiness' => ['manual' => $this->providers->readiness('manual'), 'internal' => $this->providers->readiness('internal')]];
    }

    public function detail(int $orderId): ?array
    {
        $clinicId = $this->scope->clinicId();

        if ($clinicId === null || $orderId <= 0) {
            return null;
        }
        $row = db_connect()->table('medicine_orders AS orders')->select('orders.*, providers.provider_name, providers.tracking_url_template, clinics.clinic_name, prescriptions.prescription_number, prescriptions.prescription_date, prescriptions.clinic_id AS prescription_clinic_id, patients.registration_number, patients.first_name, patients.middle_name, patients.last_name, patients.mobile_number, patients.country_code, patients.address')->join('clinics', 'clinics.id = orders.clinic_id')->join('patients', 'patients.id = orders.patient_id')->join('prescriptions', 'prescriptions.id = orders.prescription_id')->join('delivery_providers providers', 'providers.id = orders.delivery_provider_id', 'left')->where('orders.id', $orderId)->where('orders.clinic_id', $clinicId)->where('prescriptions.clinic_id', $clinicId)->get()->getRowArray();

        if ($row === null) {
            return null;
        }
        $result = $this->normalize($row);
        $result['items'] = db_connect()->table('medicine_order_items')->where('order_id', $orderId)->orderBy('id', 'ASC')->get()->getResultArray();

        return $result;
    }

    public function update(int $orderId, string $target, string $provider, string $tracking, string $reason, bool $confirmed, ?string $address = null, ?string $contact = null, ?int $providerId = null): array
    {
        $clinicId = $this->scope->clinicId();
        $target = strtolower(trim($target));
        $provider = strtolower(trim($provider));
        $tracking = trim($tracking);
        $reason = trim($reason);
        $reasonValue = $reason === '' ? null : $reason;
        $providerName = '';
        $providerTemplate = null;

        if ($clinicId === null) {
            return $this->result(403, 'Pharmacy clinic scope is not assigned.');
        }

        if (!array_key_exists($target, TransitionContract::DELIVERY)) {
            return $this->result(422, 'Delivery status is invalid.', ['delivery_status' => 'Choose a valid delivery status.']);
        }

        if ($provider === '') {
            $provider = 'manual';
        }

        if ((int) $providerId > 0) {
            $providerRow = $this->catalog->findActive((int) $providerId);
            if ($providerRow === null) return $this->result(422, 'Delivery provider is invalid.', ['provider_id' => 'Choose an active delivery provider.']);
            $provider = strtolower(trim((string) $providerRow['provider_code']));
            $providerName = (string) $providerRow['provider_name'];
            $providerTemplate = $providerRow['tracking_url_template'] ?? null;
        }

        // Legacy orders may contain a historical provider label that is not in the new master.
        // Keep that label in the snapshot while using the safe manual boundary for this update.
        if ((int) $providerId <= 0 && !in_array($provider, self::PROVIDERS, true)) {
            $providerName = $provider;
            $provider = 'manual';
        }

        if (!$confirmed) {
            return $this->result(422, 'Delivery confirmation is required.', ['confirmation' => 'Confirm this delivery update before saving.']);
        }

        if (in_array($target, ['dispatched', 'out_for_delivery', 'delivered'], true) && $tracking === '') {
            return $this->result(422, 'Tracking reference is required for this delivery status.', ['tracking_reference' => 'Tracking reference is required.']);
        }

        // Provider master data is independent from an external API boundary.
        // Save the delivery update locally even when credentials or an adapter
        // are unavailable. An external call is only eligible when both are
        // present; no adapters are installed in this application currently.
        $providerConfigured = $provider !== 'manual' && $this->providers->isConfigured($provider);
        $adapterAvailable = $providerConfigured && $this->providers->hasAdapter($provider);
        $integrationStatus = $adapterAvailable ? 'configured' : 'not_configured';
        $manualUpdate = !$adapterAvailable;
        $db = db_connect();
        $db->transBegin();

        try {
            $order = $db->query('SELECT orders.* FROM medicine_orders orders JOIN patients ON patients.id = orders.patient_id JOIN prescriptions ON prescriptions.id = orders.prescription_id AND prescriptions.clinic_id = ? WHERE orders.id = ? AND orders.clinic_id = ? LIMIT 1 FOR UPDATE', [$clinicId, $orderId, $clinicId])->getRowArray();

            if ($order === null) {
                $db->transRollback();

                return $this->result(404, 'Delivery order not found in the assigned clinic.');
            }

            $previousStatus = strtolower(trim((string) $order['delivery_status']));
            $isSequentialTransition = (new TransitionContract())->allows('delivery', $previousStatus, $target);
            if (!$isSequentialTransition && !$this->scope->can('delivery.status.override')) {
                $db->transRollback();
                $this->scope->audit('pharmacy.permission_denied', ['permission' => 'delivery.status.override', 'workflow' => 'delivery', 'order_id' => $orderId, 'previous_status' => $previousStatus, 'new_status' => $target]);

                return $this->result(403, 'Delivery override permission is required for this status change.', ['delivery_status' => 'This status requires the delivery override permission.']);
            }
            $now = date('Y-m-d H:i:s');
            $previousRank = array_search($previousStatus, self::DELIVERY_STATUS_ORDER, true);
            $targetRank = array_search($target, self::DELIVERY_STATUS_ORDER, true);
            $fields = ['delivery_status' => $target, 'delivery_provider' => $provider, 'delivery_provider_id' => (int) $providerId > 0 ? (int) $providerId : null, 'delivery_provider_name_snapshot' => $providerName !== '' ? $providerName : ($provider !== '' ? $provider : null), 'tracking_reference' => $tracking !== '' ? $tracking : null, 'updated_at' => $now];

            if ($target === 'dispatched') {
                $fields['dispatched_at'] = $now;
            } elseif ($targetRank < 2) {
                $fields['dispatched_at'] = null;
            }

            if ($target === 'delivered') {
                $fields['delivered_at'] = $now;

                if ((new TransitionContract())->allows('order', (string) $order['status'], 'fulfilled')) {
                    $fields['status'] = 'fulfilled';
                }
            } elseif ($target !== 'delivered') {
                $fields['delivered_at'] = null;
            }

            if ($target === 'failed') {
                $fields['failure_reason'] = $reasonValue;
            } else {
                $fields['failure_reason'] = null;
            }

            if ($target === 'returned') {
                $fields['return_reason'] = $reasonValue;
            } else {
                $fields['return_reason'] = null;
            }

            if (!$db->table('medicine_orders')->where('id', $orderId)->where('clinic_id', $clinicId)->where('delivery_status', $order['delivery_status'])->update($fields) || !$db->transStatus()) {
                throw new \RuntimeException('Delivery update failed.');
            }
            $db->transCommit();
            $user = session('user');
            $actorId = is_array($user) ? (int) ($user['id'] ?? 0) : 0;
            $this->scope->audit('pharmacy.delivery_updated', ['order_id' => $orderId, 'previous_status' => $previousStatus, 'new_status' => $target, 'reason' => $reasonValue, 'actor' => $actorId, 'source' => 'pharmacy_manual_update', 'integration_status' => $integrationStatus, 'external_call' => $adapterAvailable, 'provider' => $provider, 'provider_id' => (int) $providerId, 'tracking_reference' => $tracking]);
            if ($manualUpdate) {
                $this->scope->audit('pharmacy.delivery_integration_skipped', ['workflow' => 'delivery', 'order_id' => $orderId, 'provider' => $provider, 'integration_status' => $integrationStatus, 'external_call' => false, 'reason' => $providerConfigured ? 'adapter_unavailable' : 'not_configured']);
            }
            (new PharmacyNotificationService($this->scope))->create('delivery_status', 'Delivery status updated', 'Delivery for order #' . $orderId . ' is now ' . $target . '.', 'delivery', $orderId, 'delivery-status:' . $orderId . ':' . $target . ':' . $tracking);

            if (in_array($target, ['dispatched', 'delivered', 'failed', 'returned'], true)) {
                (new \Patient\Services\PatientNotificationService())->forOrder($orderId, 'delivery_status_changed', 'Delivery status updated', 'Your medicine delivery is now ' . str_replace('_', ' ', $target) . '.', 'patient-delivery:' . $orderId . ':' . $target . ':' . $tracking);
            }

            $message = $manualUpdate
                ? 'Delivery status updated as a manual update. No external provider call was made.'
                : 'Delivery status updated.';

            return ['http' => 200, 'body' => ['status' => true, 'msg' => $message, 'notice' => $message, 'manual_update' => $manualUpdate, 'integration_status' => $integrationStatus]];
        } catch (\Throwable $exception) {
            $db->transRollback();
            log_message('error', 'Pharmacy delivery update failed route=' . uri_string() . ' controller=Delivery::update method=POST order_id=' . $orderId . ' target=' . $target . ' file=' . $exception->getFile() . ' line=' . $exception->getLine() . ' error=' . $exception->getMessage());

            return $this->result(500, 'Delivery status could not be updated safely.');
        }
    }

    private function normalize(array $row): array
    {
        $name = trim(implode(' ', array_filter([$row['first_name'] ?? '', $row['middle_name'] ?? '', $row['last_name'] ?? ''])));
        $contact = trim(implode(' ', array_filter([(string) ($row['country_code'] ?? ''), (string) ($row['mobile_number'] ?? '')])));
        $deliveryStatus = (string) ($row['delivery_status'] ?? 'pending');

        $providerName = trim((string) ($row['delivery_provider_name_snapshot'] ?? $row['provider_name'] ?? $row['delivery_provider'] ?? '')) ?: 'Not recorded';
        return ['id' => (int) $row['id'], 'order_number' => (string) ($row['order_number'] ?? 'Not recorded'), 'prescription_id' => (int) ($row['prescription_id'] ?? 0), 'prescription_number' => (string) ($row['prescription_number'] ?? 'Not recorded'), 'prescription_date' => $this->displayDate($row['prescription_date'] ?? null), 'patient_name' => $name ?: 'Patient not recorded', 'patient_number' => (string) ($row['registration_number'] ?? 'Not recorded'), 'patient_mobile' => $contact ?: 'Not recorded', 'delivery_address' => (string) ($row['delivery_address_snapshot'] ?? $row['address'] ?? 'Not recorded'), 'delivery_contact' => (string) ($row['delivery_contact_snapshot'] ?? ($contact ?: 'Not recorded')), 'medicine_count' => (int) ($row['medicine_count'] ?? 0), 'order_status' => (string) ($row['order_status'] ?? $row['status'] ?? ''), 'payment_status' => (string) ($row['payment_status'] ?? ''), 'delivery_status' => $deliveryStatus, 'can_update' => in_array($deliveryStatus, ['pending', 'packed', 'dispatched', 'out_for_delivery', 'failed', 'delivered'], true), 'provider' => $providerName, 'provider_id' => (int) ($row['delivery_provider_id'] ?? 0), 'tracking_url_template' => (string) ($row['tracking_url_template'] ?? ''), 'tracking_url' => $this->catalog->trackingUrl($row['tracking_url_template'] ?? null, (string) ($row['tracking_reference'] ?? '')), 'tracking_reference' => trim((string) ($row['tracking_reference'] ?? '')) ?: 'Not recorded', 'dispatched_at' => $this->displayDateTime($row['dispatched_at'] ?? null), 'delivered_at' => $this->displayDateTime($row['delivered_at'] ?? null), 'failure_reason' => (string) ($row['failure_reason'] ?? ''), 'return_reason' => (string) ($row['return_reason'] ?? ''), 'created_at' => $this->displayDateTime($row['created_at'] ?? null), 'updated_at' => $this->displayDateTime($row['updated_at'] ?? null)];
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

    private function displayDateTime(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        } $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', substr($value, 0, 19));

        return $date !== false ? $date->format('d-m-Y H:i') : '';
    }

    private function displayDate(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        } $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));

        return $date !== false ? $date->format('d-m-Y') : '';
    }
}
