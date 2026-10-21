<?php

namespace App\Services\Shopify;

use Admin\Models\Location\TelephoneCountryCode;
use Admin\Models\PatientModel;
use Admin\Services\PatientRegistrationService;
use App\Modules\Shopify\Models\ShopifyPatientIdentityLinkModel;
use App\Services\TelephoneContactService;
use InvalidArgumentException;

final class ShopifyCustomerIdentityService
{
    public function __construct(
        private readonly ?ShopifyPatientIdentityLinkModel $links = null,
        private readonly ?PatientModel $patients = null,
        private readonly ?ShopifyWebhookEventService $events = null,
        private readonly ?ShopifyConfiguration $configuration = null,
    ) {
    }

    /**
     * Resolve one stored customer webhook event and update its identity link.
     * No patient or clinical workflow is changed here.
     *
     * @return array{status: string, patient_id: int|null, link_id: int|null}
     */
    public function processCustomerEvent(int $eventId): array
    {
        $events = $this->events ?? new ShopifyWebhookEventService();
        $event = $events->find($eventId);

        if ($event === null) {
            throw new InvalidArgumentException('Shopify event was not found.');
        }

        if ((string) ($event['status'] ?? '') === 'processed') {
            return ['status' => 'processed', 'patient_id' => null, 'link_id' => null];
        }

        $payload = json_decode((string) ($event['payload_json'] ?? ''), true);

        if (! is_array($payload)) {
            $events->markFailed($eventId, 'Customer payload is unavailable.');

            return ['status' => 'unmatched', 'patient_id' => null, 'link_id' => null];
        }

        $customerId = trim((string) ($payload['id'] ?? ''));

        if ($customerId === '') {
            $events->markFailed($eventId, 'Customer ID is unavailable.');

            return ['status' => 'unmatched', 'patient_id' => null, 'link_id' => null];
        }

        $result = $this->resolveCustomer(
            (string) $event['store_domain'],
            $customerId,
            $payload,
        );
        $events->markProcessed($eventId);

        service('audit')->record('shopify.customer_' . $result['status'], [
            'store_domain' => (string) $event['store_domain'],
            'customer_id' => $customerId,
            'patient_id' => $result['patient_id'],
            'matched_by' => $result['matched_by'] ?? null,
        ]);

        return [
            'status' => $result['status'],
            'patient_id' => $result['patient_id'],
            'link_id' => $result['link_id'],
        ];
    }

    /**
     * Return a minimized, store-scoped customer view for the Admin test action.
     * Internal patient IDs and full webhook payloads never leave this service.
     *
     * @return array<string, mixed>
     */
    public function customerDetails(?string $customerId = null, ?int $eventId = null): array
    {
        $configuration = $this->configuration ?? new ShopifyConfiguration();
        $storeDomain = strtolower(trim($configuration->storeDomain()));
        $events = $this->events ?? new ShopifyWebhookEventService();
        $event = $eventId !== null && $eventId > 0
            ? $events->find($eventId)
            : $events->findLatestCustomerEvent($storeDomain, trim((string) $customerId));

        if ($event === null || strtolower((string) ($event['store_domain'] ?? '')) !== $storeDomain || ! in_array((string) ($event['topic'] ?? ''), ['customers/create', 'customers/update'], true)) {
            throw new InvalidArgumentException('A received Shopify customer event was not found in the configured store.');
        }

        $payload = json_decode((string) ($event['payload_json'] ?? ''), true);
        if (! is_array($payload) || trim((string) ($payload['id'] ?? '')) === '') {
            throw new InvalidArgumentException('The received Shopify customer details are unavailable.');
        }

        $customerId = trim((string) $payload['id']);
        $link = ($this->links ?? new ShopifyPatientIdentityLinkModel())
            ->where('shopify_store_domain', $storeDomain)
            ->where('shopify_customer_id', $customerId)
            ->first();
        $patientReference = null;
        if ($link !== null && ! empty($link['patient_id'])) {
            $clinicId = $configuration->clinicId();
            $patient = $clinicId === null ? null : ($this->patients ?? new PatientModel())
                ->select('registration_number')
                ->where('id', (int) $link['patient_id'])
                ->where('clinic_id', $clinicId)
                ->where('status', 1)
                ->first();
            $patientReference = $patient['registration_number'] ?? null;
        }

        return [
            'shopify_customer_id' => $customerId,
            'name' => trim((string) ($payload['first_name'] ?? '') . ' ' . (string) ($payload['last_name'] ?? '')),
            'email' => (string) ($payload['email'] ?? ''),
            'mobile' => (string) ($payload['phone'] ?? ''),
            'store_domain' => $storeDomain,
            'match_status' => (string) ($link['link_status'] ?? 'unmatched'),
            'linked_patient_reference' => $patientReference,
        ];
    }

    /** @return array{match_status:string,linked_patient_reference:?string} */
    public function identityMatch(string $customerId): array
    {
        $configuration = $this->configuration ?? new ShopifyConfiguration();
        $storeDomain = strtolower(trim($configuration->storeDomain()));
        $link = ($this->links ?? new ShopifyPatientIdentityLinkModel())
            ->where('shopify_store_domain', $storeDomain)
            ->where('shopify_customer_id', trim($customerId))
            ->first();
        $patientReference = null;

        if ($link !== null && ! empty($link['patient_id'])) {
            $clinicId = $configuration->clinicId();
            $patient = $clinicId === null ? null : ($this->patients ?? new PatientModel())
                ->select('registration_number')
                ->where('id', (int) $link['patient_id'])
                ->where('clinic_id', $clinicId)
                ->where('status', 1)
                ->first();
            $patientReference = $patient['registration_number'] ?? null;
        }

        return [
            'match_status' => (string) ($link['link_status'] ?? 'unmatched'),
            'linked_patient_reference' => $patientReference,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reviewQueue(): array
    {
        $configuration = $this->configuration ?? new ShopifyConfiguration();
        $model = $this->links ?? new ShopifyPatientIdentityLinkModel();

        $rows = $model
            ->where('shopify_store_domain', strtolower($configuration->storeDomain()))
            ->whereIn('link_status', ['unmatched', 'ambiguous', 'linked'])
            ->orderBy('updated_at', 'DESC')
            ->findAll(100);

        foreach ($rows as &$row) {
            $patient = ! empty($row['patient_id']) ? ($this->patients ?? new PatientModel())->select('registration_number')->where('id', (int) $row['patient_id'])->first() : null;
            $row['patient_reference'] = (string) ($patient['registration_number'] ?? '');
            unset($row['patient_id']);
        }
        unset($row);
        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function patientOptions(): array
    {
        $clinicId = ($this->configuration ?? new ShopifyConfiguration())->clinicId();

        if ($clinicId === null) {
            return [];
        }

        return ($this->patients ?? new PatientModel())
            ->select('id, registration_number, first_name, last_name')
            ->where('clinic_id', $clinicId)
            ->where('status', 1)
            ->orderBy('first_name', 'ASC')
            ->findAll(250);
    }

    public function linkExisting(int $linkId, int $patientId): bool
    {
        $configuration = $this->configuration ?? new ShopifyConfiguration();
        $clinicId = $configuration->clinicId();
        $links = $this->links ?? new ShopifyPatientIdentityLinkModel();
        $link = $this->scopedLink($linkId);

        if ($link === null || $clinicId === null) {
            throw new InvalidArgumentException('The Shopify identity is outside the configured clinic scope.');
        }

        $patient = ($this->patients ?? new PatientModel())
            ->where('id', $patientId)
            ->where('clinic_id', $clinicId)
            ->where('status', 1)
            ->first();

        if ($patient === null) {
            throw new InvalidArgumentException('The selected patient is invalid for this clinic.');
        }

        $conflict = $links
            ->where('active_patient_key', 'shopify:' . $patientId)
            ->where('id !=', $linkId)
            ->where('link_status', 'linked')
            ->first();

        if ($conflict !== null) {
            throw new InvalidArgumentException('This patient is already linked to another Shopify customer.');
        }

        $updated = $links->update($linkId, [
            'patient_id' => $patientId,
            'link_status' => 'linked',
            'matched_by' => 'admin',
            'active_patient_key' => 'shopify:' . $patientId,
            'linked_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($updated) {
            service('audit')->record('shopify.patient_linked', [
                'store_domain' => $link['shopify_store_domain'],
                'customer_id' => $link['shopify_customer_id'],
                'patient_id' => $patientId,
            ]);
        }

        return (bool) $updated;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createPatient(int $linkId, array $data): int
    {
        $configuration = $this->configuration ?? new ShopifyConfiguration();
        $link = $this->scopedLink($linkId);
        $clinicId = $configuration->clinicId();

        if ($link === null || $clinicId === null || ! $configuration->allowAutomaticPatientCreation()) {
            throw new InvalidArgumentException('Patient creation is not enabled for this Shopify integration.');
        }

        $existing = $this->scopedLink($linkId);

        if (($existing['link_status'] ?? '') === 'linked' || ! empty($existing['patient_id'])) {
            throw new InvalidArgumentException('This Shopify customer is already linked.');
        }

        $phone = $this->normalizeFullMobile((string) ($link['mobile_snapshot'] ?? ''));

        if ($phone === null) {
            throw new InvalidArgumentException('A valid Shopify mobile number is required to create a patient.');
        }

        $registration = (new PatientRegistrationService())->register([
            'clinic_id' => $clinicId,
            'first_name' => trim((string) ($data['first_name'] ?? '')),
            'last_name' => trim((string) ($data['last_name'] ?? '')),
            'email' => strtolower(trim((string) ($data['email'] ?? $link['email_snapshot'] ?? ''))),
            'mobile_number' => $phone['mobile'],
            'country_code' => $phone['country_code'],
            'date_of_birth' => trim((string) ($data['date_of_birth'] ?? '')),
            'source_type' => 'shopify',
            'source_reference' => (string) $link['shopify_customer_id'],
            'status' => 1,
        ], (int) ((session('user') ?? [])['id'] ?? 0), [
            'source_type' => 'shopify',
            'source_reference' => (string) $link['shopify_customer_id'],
        ]);
        $this->linkExisting($linkId, (int) $registration['id']);

        service('audit')->record('shopify.patient_created_and_linked', [
            'store_domain' => $link['shopify_store_domain'],
            'customer_id' => $link['shopify_customer_id'],
            'patient_id' => (int) $registration['id'],
        ]);

        return (int) $registration['id'];
    }

    public function reject(int $linkId): bool
    {
        return $this->setStatus($linkId, 'rejected', 'shopify.customer_rejected');
    }

    public function unlink(int $linkId): bool
    {
        return $this->setStatus($linkId, 'unlinked', 'shopify.patient_unlinked', [
            'patient_id' => null,
            'active_patient_key' => null,
            'linked_at' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: string, patient_id: int|null, link_id: int|null, matched_by: string|null}
     */
    private function resolveCustomer(string $storeDomain, string $customerId, array $payload): array
    {
        $links = $this->links ?? new ShopifyPatientIdentityLinkModel();
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $mobile = $this->normalizeFullMobile((string) ($payload['phone'] ?? ''));
        $link = $links
            ->where('shopify_store_domain', strtolower($storeDomain))
            ->where('shopify_customer_id', $customerId)
            ->first();
        $data = [
            'shopify_store_domain' => strtolower($storeDomain),
            'shopify_customer_id' => $customerId,
            'email_snapshot' => $email !== '' ? $email : null,
            'mobile_snapshot' => $mobile['full'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($link !== null && (string) ($link['link_status'] ?? '') === 'linked') {
            $links->update((int) $link['id'], $data);

            return ['status' => 'matched', 'patient_id' => (int) $link['patient_id'], 'link_id' => (int) $link['id'], 'matched_by' => 'existing_link'];
        }

        $matches = $this->findMatches($email, $mobile);
        $status = 'unmatched';
        $patientId = null;
        $matchedBy = null;

        if (count($matches) === 1) {
            $patientId = (int) $matches[0]['id'];
            $status = 'linked';
            $matchedBy = $matches[0]['matched_by'];
            $conflict = $links
                ->where('active_patient_key', 'shopify:' . $patientId)
                ->where('id !=', (int) ($link['id'] ?? 0))
                ->where('link_status', 'linked')
                ->first();

            if ($conflict !== null) {
                $status = 'ambiguous';
                $patientId = null;
                $matchedBy = null;
            }
        } elseif (count($matches) > 1) {
            $status = 'ambiguous';
        }

        $data['patient_id'] = $patientId;
        $data['link_status'] = $status;
        $data['matched_by'] = $matchedBy;
        $data['active_patient_key'] = $patientId !== null ? 'shopify:' . $patientId : null;
        $data['linked_at'] = $patientId !== null ? date('Y-m-d H:i:s') : null;
        $data['created_at'] = $link['created_at'] ?? date('Y-m-d H:i:s');

        if ($link === null) {
            $linkId = (int) $links->insert($data, true);
        } else {
            $linkId = (int) $link['id'];
            $links->update($linkId, $data);
        }

        return ['status' => $status === 'linked' ? 'matched' : $status, 'patient_id' => $patientId, 'link_id' => $linkId, 'matched_by' => $matchedBy];
    }

    /**
     * @return list<array{id: int, matched_by: string}>
     */
    private function findMatches(string $email, ?array $mobile): array
    {
        $configuration = $this->configuration ?? new ShopifyConfiguration();
        $clinicId = $configuration->clinicId();

        if ($clinicId === null || ($email === '' && $mobile === null)) {
            return [];
        }

        $patients = $this->patients ?? new PatientModel();
        $query = $patients
            ->select('patients.id, patients.email, patients.mobile_normalized, patients.country_code')
            ->where('patients.clinic_id', $clinicId)
            ->where('patients.status', 1)
            ->groupStart();

        if ($email !== '') {
            $query->where('patients.email', $email);
        }

        if ($mobile !== null) {
            $query->orGroupStart()
                ->where('patients.mobile_normalized', $mobile['mobile'])
                ->where('patients.country_code', $mobile['country_code'])
                ->groupEnd();
        }

        $rows = $query->groupEnd()->findAll();
        $matches = [];

        foreach ($rows as $row) {
            $matchedBy = $email !== '' && strtolower((string) ($row['email'] ?? '')) === $email ? 'email' : 'mobile';
            $matches[(int) $row['id']] = ['id' => (int) $row['id'], 'matched_by' => $matchedBy];
        }

        return array_values($matches);
    }

    /**
     * @return array{full: string, country_code: string, mobile: string}|null
     */
    private function normalizeFullMobile(string $value): ?array
    {
        $digits = (new TelephoneContactService())->normalizedMobile($value);

        if ($digits === null) {
            return null;
        }

        $codes = (new TelephoneCountryCode())
            ->select('normalized_code')
            ->where('status', 1)
            ->orderBy('normalized_code', 'DESC')
            ->findAll();

        foreach ($codes as $code) {
            $countryCode = (string) ($code['normalized_code'] ?? '');

            if ($countryCode === '' || ! str_starts_with($digits, $countryCode)) {
                continue;
            }

            $mobile = substr($digits, strlen($countryCode));

            if (preg_match('/^[0-9]{7,15}$/', $mobile)) {
                return ['full' => $digits, 'country_code' => $countryCode, 'mobile' => $mobile];
            }
        }

        return null;
    }

    private function scopedLink(int $linkId): ?array
    {
        $configuration = $this->configuration ?? new ShopifyConfiguration();

        return ($this->links ?? new ShopifyPatientIdentityLinkModel())
            ->where('id', $linkId)
            ->where('shopify_store_domain', strtolower($configuration->storeDomain()))
            ->first();
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function setStatus(int $linkId, string $status, string $auditAction, array $extra = []): bool
    {
        $link = $this->scopedLink($linkId);

        if ($link === null) {
            throw new InvalidArgumentException('Shopify identity was not found in the configured store scope.');
        }

        $data = array_merge([
            'link_status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ], $extra);
        $updated = ($this->links ?? new ShopifyPatientIdentityLinkModel())->update($linkId, $data);

        if ($updated) {
            service('audit')->record($auditAction, [
                'store_domain' => $link['shopify_store_domain'],
                'customer_id' => $link['shopify_customer_id'],
                'patient_id' => $link['patient_id'],
            ]);
        }

        return (bool) $updated;
    }
}
