<?php

namespace App\Services\Shopify;

use Admin\Models\Location\TelephoneCountryCode;
use Admin\Models\PatientModel;
use App\Modules\Shopify\Models\ShopifyPatientIdentityLinkModel;
use App\Services\TelephoneContactService;
use Throwable;

final class ShopifyCustomerPatientSyncService
{
    public function __construct(
        private readonly ?ShopifyPatientIdentityLinkModel $links = null,
        private readonly ?PatientModel $patients = null,
        private readonly ?ShopifyWebhookEventService $events = null,
    ) {
    }

    /** @return array{status:string,patient_id:int,link_id:int,matched_by:string} */
    public function processEvent(int $eventId): array
    {
        $events = $this->events ?? new ShopifyWebhookEventService();
        $event = $events->find($eventId);

        if ($event === null) {
            throw new ShopifyCustomerPatientSyncException('SHOPIFY_CUSTOMER_PAYLOAD_INVALID', 'Shopify customer event was not found.');
        }
        if ((string) ($event['status'] ?? '') === 'processed') {
            return ['status' => 'processed', 'patient_id' => 0, 'link_id' => 0, 'matched_by' => 'idempotent_replay'];
        }

        $payload = json_decode((string) ($event['payload_json'] ?? ''), true);
        if (! is_array($payload)) {
            throw new ShopifyCustomerPatientSyncException('SHOPIFY_CUSTOMER_PAYLOAD_INVALID', 'Shopify customer payload is invalid.');
        }

        $customer = $this->normalizeCustomer($payload);
        $store = strtolower(trim((string) ($event['store_domain'] ?? '')));
        if ($store === '') {
            throw new ShopifyCustomerPatientSyncException('SHOPIFY_CUSTOMER_PAYLOAD_INVALID', 'Shopify customer store scope is invalid.');
        }

        try {
            $result = $this->sync($store, $customer);
            $events->markProcessed($eventId);
            $auditEvent = match ($result['status']) {
                'created' => 'shopify.customer_patient_created',
                'updated' => 'shopify.customer_patient_updated',
                default => 'shopify.customer_patient_linked',
            };
            service('audit')->record($auditEvent, [
                'correlation_id' => $this->correlationId(),
                'topic' => (string) ($event['topic'] ?? ''),
                'store_domain' => $store,
                'customer_id' => $customer['customer_id'],
                'patient_reference' => $result['patient_reference'],
                'event_id_hash' => hash('sha256', (string) ($event['external_event_id'] ?? '')),
                'processing_stage' => 'completed',
            ]);
            service('audit')->record('shopify.customer_patient_linked', [
                'correlation_id' => $this->correlationId(),
                'topic' => (string) ($event['topic'] ?? ''),
                'store_domain' => $store,
                'customer_id' => $customer['customer_id'],
                'patient_reference' => $result['patient_reference'],
                'event_id_hash' => hash('sha256', (string) ($event['external_event_id'] ?? '')),
                'processing_stage' => 'identity_linked',
            ]);

            return [
                'status' => $result['status'],
                'patient_id' => $result['patient_id'],
                'link_id' => $result['link_id'],
                'matched_by' => $result['matched_by'],
            ];
        } catch (ShopifyCustomerPatientSyncException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ShopifyCustomerPatientSyncException('SHOPIFY_PATIENT_SYNC_FAILED', 'Shopify customer patient synchronization failed.');
        }
    }

    /** @return array<string,mixed> */
    private function normalizeCustomer(array $payload): array
    {
        $customerId = trim((string) ($payload['id'] ?? ''));
        if ($customerId === '') {
            throw new ShopifyCustomerPatientSyncException('SHOPIFY_CUSTOMER_ID_REQUIRED', 'Shopify customer ID is required.');
        }

        $firstName = trim((string) ($payload['first_name'] ?? ''));
        if ($firstName === '' || mb_strlen($firstName) > 100) {
            throw new ShopifyCustomerPatientSyncException('SHOPIFY_CUSTOMER_PAYLOAD_INVALID', 'Shopify customer first name is invalid.');
        }

        $lastName = trim((string) ($payload['last_name'] ?? '')) ?: null;
        if ($lastName !== null && mb_strlen($lastName) > 100) {
            throw new ShopifyCustomerPatientSyncException('SHOPIFY_CUSTOMER_PAYLOAD_INVALID', 'Shopify customer last name is invalid.');
        }

        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        if ($email !== '' && (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 255)) {
            throw new ShopifyCustomerPatientSyncException('SHOPIFY_CUSTOMER_PAYLOAD_INVALID', 'Shopify customer email is invalid.');
        }

        $phoneInput = trim((string) ($payload['phone'] ?? ''));
        $phone = $phoneInput === '' ? null : $this->normalizeFullMobile($phoneInput);
        if ($phoneInput !== '' && $phone === null) {
            throw new ShopifyCustomerPatientSyncException('SHOPIFY_CUSTOMER_PAYLOAD_INVALID', 'Shopify customer phone is invalid.');
        }

        $address = $payload['default_address'] ?? [];
        if ($address !== null && ! is_array($address)) {
            throw new ShopifyCustomerPatientSyncException('SHOPIFY_CUSTOMER_PAYLOAD_INVALID', 'Shopify customer address is invalid.');
        }

        return [
            'customer_id' => $customerId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone,
            'address' => is_array($address) ? $this->normalizeAddress($address) : [],
        ];
    }

    /** @param array<string,mixed> $customer @return array{status:string,patient_id:int,patient_reference:string,link_id:int,matched_by:string} */
    private function sync(string $store, array $customer): array
    {
        $links = $this->links ?? new ShopifyPatientIdentityLinkModel();
        $patients = $this->patients ?? new PatientModel();
        $existingLink = $links->where('shopify_store_domain', $store)->where('shopify_customer_id', $customer['customer_id'])->first();
        $patientId = (int) ($existingLink['patient_id'] ?? 0);
        $matchedBy = 'existing_link';
        $status = 'updated';

        if ($patientId > 0) {
            $patient = $patients->where('id', $patientId)->first();
            if ($patient === null) {
                throw new ShopifyCustomerPatientSyncException('SHOPIFY_PATIENT_SYNC_FAILED', 'The linked ROOTINS patient could not be found.');
            }
        } else {
            [$patientId, $matchedBy, $patientCreated] = $this->findOrCreatePatient($store, $customer, $patients, $links);
            $status = $patientCreated ? 'created' : 'linked';
        }

        $this->assertPatientLinkAvailable($links, $store, $customer['customer_id'], $patientId, (int) ($existingLink['id'] ?? 0));
        $this->updateProfile($patients, $patientId, $customer);

        $now = date('Y-m-d H:i:s');
        $linkData = [
            'shopify_store_domain' => $store,
            'shopify_customer_id' => $customer['customer_id'],
            'patient_id' => $patientId,
            'email_snapshot' => $customer['email'],
            'mobile_snapshot' => $customer['phone']['full'] ?? null,
            'link_status' => 'linked',
            'matched_by' => $matchedBy,
            'active_patient_key' => $this->activePatientKey($store, $patientId),
            'linked_at' => $existingLink['linked_at'] ?? $now,
            'created_at' => $existingLink['created_at'] ?? $now,
            'updated_at' => $now,
        ];

        try {
            if ($existingLink === null) {
                $linkId = (int) $links->insert($linkData, true);
            } else {
                $linkId = (int) $existingLink['id'];
                $links->update($linkId, $linkData);
            }
        } catch (Throwable $exception) {
            throw new ShopifyCustomerPatientSyncException('SHOPIFY_PATIENT_IDENTITY_CONFLICT', 'The Shopify customer identity could not be linked safely.');
        }

        $reference = (string) ($patients->select('registration_number')->where('id', $patientId)->first()['registration_number'] ?? '');
        if ($reference === '') {
            throw new ShopifyCustomerPatientSyncException('SHOPIFY_PATIENT_SYNC_FAILED', 'The ROOTINS patient reference could not be resolved.');
        }

        return ['status' => $status, 'patient_id' => $patientId, 'patient_reference' => $reference, 'link_id' => $linkId, 'matched_by' => $matchedBy];
    }

    /** @return array{0:int,1:string,2:bool} */
    private function findOrCreatePatient(string $store, array $customer, PatientModel $patients, ShopifyPatientIdentityLinkModel $links): array
    {
        $sourceReference = 'shopify:' . $store . ':' . $customer['customer_id'];
        $source = $patients->where('source_type', 'shopify')->where('source_reference', $sourceReference)->where('status', 1)->first();
        if ($source !== null) return [(int) $source['id'], 'source_reference', false];

        $matches = $this->matchingPatients($customer, $patients);
        if (count($matches) > 1) {
            throw new ShopifyCustomerPatientSyncException('SHOPIFY_PATIENT_IDENTITY_CONFLICT', 'Multiple ROOTINS patients match the Shopify customer.');
        }
        if ($matches !== []) return [(int) $matches[0]['id'], (string) $matches[0]['matched_by'], false];

        $db = db_connect();
        $now = date('Y-m-d H:i:s');
        $data = [
            'clinic_id' => null,
            'first_name' => $customer['first_name'],
            'last_name' => $customer['last_name'],
            'gender' => null,
            'date_of_birth' => null,
            'email' => $customer['email'],
            'country_code' => $customer['phone']['country_code'] ?? null,
            'country_code_id' => $customer['phone']['country_code_id'] ?? null,
            'telephone_country_code_id' => $customer['phone']['telephone_country_code_id'] ?? null,
            'mobile_number' => $customer['phone']['mobile'] ?? null,
            'mobile_normalized' => $customer['phone']['mobile'] ?? null,
            'address' => $customer['address']['address'] ?? null,
            'postal_code' => $customer['address']['zip'] ?? null,
            'status' => 1,
            'source_type' => 'shopify',
            'source_reference' => $sourceReference,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if (! $db->table('patients')->insert($data)) {
            throw new ShopifyCustomerPatientSyncException('SHOPIFY_PATIENT_SYNC_FAILED', 'ROOTINS patient creation failed.');
        }
        $patientId = (int) $db->insertID();
        if ($patientId < 1 || ! $db->table('patients')->where('id', $patientId)->update(['registration_number' => sprintf('PAT-%06d', $patientId)])) {
            throw new ShopifyCustomerPatientSyncException('SHOPIFY_PATIENT_SYNC_FAILED', 'ROOTINS patient reference creation failed.');
        }

        return [$patientId, 'created', true];
    }

    /** @return list<array{id:int,matched_by:string}> */
    private function matchingPatients(array $customer, PatientModel $patients): array
    {
        if ($customer['email'] === null && $customer['phone'] === null) return [];
        $query = $patients->select('id, email, mobile_normalized, country_code')->where('status', 1);
        if ($customer['email'] !== null && $customer['phone'] !== null) {
            $query->groupStart()
                ->where('email', $customer['email'])
                ->orGroupStart()
                ->where('mobile_normalized', $customer['phone']['mobile'])
                ->where('country_code', $customer['phone']['country_code'])
                ->groupEnd()
                ->groupEnd();
        } elseif ($customer['email'] !== null) {
            $query->where('email', $customer['email']);
        } else {
            $query->where('mobile_normalized', $customer['phone']['mobile'])->where('country_code', $customer['phone']['country_code']);
        }
        $rows = $query->findAll();
        $matches = [];
        foreach ($rows as $row) {
            $matchedBy = $customer['email'] !== null && strtolower((string) ($row['email'] ?? '')) === $customer['email'] ? 'email' : 'phone';
            $matches[(int) $row['id']] = ['id' => (int) $row['id'], 'matched_by' => $matchedBy];
        }
        return array_values($matches);
    }

    private function assertPatientLinkAvailable(ShopifyPatientIdentityLinkModel $links, string $store, string $customerId, int $patientId, int $currentLinkId): void
    {
        $conflict = $links->where('shopify_store_domain', $store)->where('active_patient_key', $this->activePatientKey($store, $patientId))->where('link_status', 'linked')->where('id !=', $currentLinkId)->first();
        if ($conflict !== null) throw new ShopifyCustomerPatientSyncException('SHOPIFY_PATIENT_IDENTITY_CONFLICT', 'The ROOTINS patient is already linked to another Shopify customer in this store.');
    }

    private function updateProfile(PatientModel $patients, int $patientId, array $customer): void
    {
        $data = ['first_name' => $customer['first_name'], 'last_name' => $customer['last_name'], 'email' => $customer['email']];
        if ($customer['phone'] !== null) $data += ['country_code' => $customer['phone']['country_code'], 'country_code_id' => $customer['phone']['country_code_id'], 'telephone_country_code_id' => $customer['phone']['telephone_country_code_id'], 'mobile_number' => $customer['phone']['mobile'], 'mobile_normalized' => $customer['phone']['mobile']];
        else $data += ['country_code' => null, 'country_code_id' => null, 'telephone_country_code_id' => null, 'mobile_number' => null, 'mobile_normalized' => null];
        if ($customer['address'] !== []) $data += ['address' => $customer['address']['address'], 'postal_code' => $customer['address']['zip']];
        if (! $patients->update($patientId, $data)) throw new ShopifyCustomerPatientSyncException('SHOPIFY_PATIENT_SYNC_FAILED', 'ROOTINS patient profile update failed.');
    }

    /** @return array<string,mixed> */
    private function normalizeAddress(array $address): array
    {
        $parts = array_filter(array_map(static fn (string $key): string => trim((string) ($address[$key] ?? '')), ['address1', 'address2', 'city', 'province', 'country']));
        return ['address' => $parts !== [] ? implode(', ', $parts) : null, 'zip' => trim((string) ($address['zip'] ?? '')) ?: null];
    }

    /** @return array{full:string,country_code:string,mobile:string,country_code_id:int|null,telephone_country_code_id:int|null} */
    private function normalizeFullMobile(string $value): ?array
    {
        $digits = (new TelephoneContactService())->normalizedMobile($value);
        if ($digits === null) return null;
        foreach ((new TelephoneCountryCode())->select('id, country_id, normalized_code')->where('status', 1)->orderBy('normalized_code', 'DESC')->findAll() as $code) {
            $prefix = trim((string) ($code['normalized_code'] ?? ''));
            $national = $prefix !== '' && str_starts_with($digits, $prefix) ? substr($digits, strlen($prefix)) : '';
            if ($national !== '' && preg_match('/^[0-9]{7,15}$/', $national) === 1) return ['full' => $digits, 'country_code' => $prefix, 'mobile' => $national, 'country_code_id' => $code['country_id'] !== null ? (int) $code['country_id'] : null, 'telephone_country_code_id' => (int) $code['id']];
        }
        return null;
    }

    private function activePatientKey(string $store, int $patientId): string { return 'shopify:' . hash('sha256', $store . '|' . $patientId); }
    private function correlationId(): string { try { return bin2hex(random_bytes(16)); } catch (Throwable) { return 'unavailable'; } }
}
