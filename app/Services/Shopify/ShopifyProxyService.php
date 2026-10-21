<?php

namespace App\Services\Shopify;

use Admin\Services\PatientRegistrationService;
use Admin\Models\Location\TelephoneCountryCode;
use App\Modules\CoreApi\Models\CoreApiCredentialModel;
use App\Modules\CoreApi\Services\CoreApiAppointmentService;
use App\Modules\CoreApi\Services\CoreApiException;
use App\Modules\CoreApi\Services\CoreApiPrescriptionUploadService;
use App\Modules\CoreApi\Services\CoreApiRateLimitService;
use App\Modules\Shopify\Models\ShopifyPatientIdentityLinkModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use InvalidArgumentException;
use App\Services\Shopify\ShopifyAppointmentIntentService;
use App\Services\TelephoneContactService;

final class ShopifyProxyService
{
    private const OPERATIONS = ['appointments.availability', 'appointments.book', 'patients.register', 'prescriptions.upload'];
    private const TIMESTAMP_TOLERANCE_SECONDS = 300;

    public function verify(string $operation, array $query, string $rawQuery): array
    {
        $signatureService = new ShopifyProxySignatureService();
        $received = array_keys($query);
        sort($received, SORT_STRING);
        $required = ['shop', 'timestamp', 'signature', 'path_prefix'];
        $missing = array_values(array_diff($required, $received));
        try {
            $configuration = (new ShopifyProxyConfigurationService())->runtime();
        } catch (InvalidArgumentException $exception) {
            $this->logValidationFailure($missing, $received, null, null, 'missing_configuration', false);
            throw new ShopifyProxyAuthenticationException($exception->getMessage(), 'SHOPIFY_PROXY_CONFIGURATION_MISSING');
        }

        $secretPresent = $configuration['client_secret'] !== '';
        if (! $configuration['enabled']) {
            $this->logValidationFailure($missing, $received, null, null, 'disabled', $secretPresent);
            throw new ShopifyProxyAuthenticationException('Shopify Core API App Proxy is disabled.', 'SHOPIFY_PROXY_DISABLED', 403);
        }
        if (! in_array($operation, $configuration['allowed_operations'], true)) {
            $this->logValidationFailure($missing, $received, null, null, 'operation_disabled', $secretPresent);
            throw new ShopifyProxyAuthenticationException('This Shopify Core API App Proxy operation is disabled.', 'SHOPIFY_PROXY_OPERATION_DISABLED', 403);
        }
        if ($missing !== []) {
            $this->logValidationFailure($missing, $received, null, null, 'missing_parameters', $secretPresent);
            throw new ShopifyProxyAuthenticationException('The Shopify proxy request is missing required parameters.', 'SHOPIFY_PROXY_PARAMETERS_MISSING');
        }

        $shop = ShopifyProxyConfigurationService::normalizeStoreDomain($this->scalar($query['shop'] ?? ''));
        if (! self::isValidStoreDomain($shop) || $shop !== $configuration['store_domain']) {
            $this->logValidationFailure($missing, $received, $shop, null, 'store_scope', $secretPresent);
            throw new ShopifyProxyAuthenticationException('The Shopify Core API app store scope is invalid.', 'SHOPIFY_PROXY_STORE_SCOPE_INVALID', 403);
        }

        $timestampValue = $this->scalar($query['timestamp'] ?? '');
        $timestamp = ctype_digit($timestampValue) ? (int) $timestampValue : null;
        $timestampAge = $timestamp !== null ? time() - $timestamp : null;
        if ($timestamp === null || abs($timestampAge) > self::TIMESTAMP_TOLERANCE_SECONDS) {
            $this->logValidationFailure($missing, $received, $shop, $timestampAge, 'timestamp_invalid', $secretPresent);
            throw new ShopifyProxyAuthenticationException('The Shopify proxy request timestamp is invalid or expired.', 'SHOPIFY_PROXY_TIMESTAMP_INVALID');
        }

        $provided = $query['signature'] ?? '';
        if (! is_string($provided) || $provided === '' || ! $secretPresent) {
            $this->logValidationFailure($missing, $received, $shop, $timestampAge, 'signature_unavailable', $secretPresent);
            throw new ShopifyProxyAuthenticationException('The Shopify Core API app proxy signature is unavailable.', 'SHOPIFY_PROXY_SIGNATURE_MISSING');
        }

        $canonical = $signatureService->canonical($signatureService->query($rawQuery));
        $expected = hash_hmac('sha256', $canonical, $configuration['client_secret']);
        if (! hash_equals($expected, $provided)) {
            $this->logValidationFailure($missing, $received, $shop, $timestampAge, 'signature_invalid', true);
            throw new ShopifyProxyAuthenticationException('The Shopify proxy signature is invalid.', 'SHOPIFY_PROXY_SIGNATURE_INVALID');
        }

        return ['store_domain' => $shop, 'customer_id' => trim($this->scalar($query['logged_in_customer_id'] ?? '')), 'configuration' => $configuration];
    }

    private function scalar(mixed $value): string
    {
        return is_array($value) ? '' : trim((string) $value);
    }

    private static function isValidStoreDomain(string $store): bool
    {
        return preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $store) === 1;
    }

    private function logValidationFailure(array $missing, array $received, ?string $store, ?int $timestampAge, string $reason, bool $secretPresent): void
    {
        log_message('warning', 'Shopify Core API App Proxy validation failed ' . json_encode([
            'missing_parameter_names' => array_values($missing),
            'received_parameter_names' => array_values($received),
            'store_domain' => $store,
            'timestamp_age_seconds' => $timestampAge,
            'configuration_source' => 'shopify_core_api_proxy_configurations',
            'configuration_status' => $reason === 'missing_configuration' ? 'missing' : 'configured',
            'secret_present' => $secretPresent,
            'reason' => $reason,
        ], JSON_UNESCAPED_SLASHES));
    }

    /** @return array<string, mixed> */
    public function execute(string $operation, array $context, array $payload, ?UploadedFile $file = null): array
    {
        $configuration = $context['configuration'];
        $credential = $this->credential();
        $this->rateLimit((int) $credential['id'], (int) $configuration['rate_limit_per_minute']);
        $patientScoped = in_array($operation, ['appointments.book', 'prescriptions.upload'], true);
        $autoLinkPatient = $operation === 'appointments.book';
        if ($operation === 'appointments.availability') $payload = $this->normalizeAvailabilityPayload($payload);
        if ($operation === 'appointments.book') $payload = $this->normalizeBookingPayload($payload);
        $customerId = (string) $context['customer_id'];
        if ($patientScoped && $customerId === '') {
            $this->identityFailure('SHOPIFY_CUSTOMER_LOGIN_REQUIRED', 'Sign in to Shopify before continuing.', ['next_action' => 'SHOPIFY_CUSTOMER_LOGIN']);
        }

        $links = new ShopifyPatientIdentityLinkModel();
        $link = $patientScoped ? $links->where('shopify_store_domain', $context['store_domain'])->where('shopify_customer_id', $customerId)->first() : null;

        if ($patientScoped && $link === null && ! $autoLinkPatient) {
            $foreignLink = (new ShopifyPatientIdentityLinkModel())->where('shopify_customer_id', $customerId)->where('shopify_store_domain !=', $context['store_domain'])->first();
            if ($foreignLink !== null) {
                $this->identityFailure('SHOPIFY_STORE_MISMATCH', 'The Shopify customer is not linked to this store.', ['shopify_customer' => 'The customer identity belongs to another store.']);
            }
            $this->identityFailure('SHOPIFY_CUSTOMER_UNLINKED', 'Complete patient registration before booking.', ['next_action' => 'REGISTER_PATIENT']);
        }
        if ($patientScoped && (string) ($link['link_status'] ?? '') !== 'linked' && $autoLinkPatient) {
            $resolved = $this->ensureCustomerPatientLink($context, $credential);
            $link = ['patient_id' => $resolved['patient_id'], 'link_status' => 'linked'];
        }
        if ($patientScoped && (string) ($link['link_status'] ?? '') !== 'linked') {
            $this->identityFailure('SHOPIFY_CUSTOMER_UNLINKED', 'Complete patient registration before booking.', ['next_action' => 'REGISTER_PATIENT']);
        }
        if ($patientScoped && $link !== null) {
            $patient = db_connect()->table('patients')->select('registration_number, clinic_id, status')->where('id', (int) $link['patient_id'])->get()->getRowArray();
            if ($patient === null || (int) $patient['status'] !== 1) {
                $this->identityFailure('SHOPIFY_PATIENT_INACTIVE', 'The linked ROOTINS patient is inactive.', ['patient' => 'Reactivate the patient before booking.']);
            }
            if ((int) $patient['clinic_id'] !== (int) $credential['clinic_id']) {
                service('audit')->record('shopify.proxy_booking_identity_rejected', ['code' => 'SHOPIFY_STORE_SCOPE_MISMATCH', 'operation' => 'appointments.book']);
                $this->identityFailure('SHOPIFY_SCOPE_INVALID', 'The Shopify customer is outside the configured clinic scope.', ['clinic' => 'The linked patient is outside the configured clinic scope.']);
            }
            $payload['patient_reference'] = (string) $patient['registration_number'];
            $payload['clinic_id'] = (int) $credential['clinic_id'];
            $context['patient_id'] = (int) $link['patient_id'];
            $context['patient_reference'] = $payload['patient_reference'];
        }
        return match ($operation) {
            'appointments.availability' => (new CoreApiAppointmentService())->availability($credential, $payload),
            'appointments.book' => (new ShopifyAppointmentIntentService())->create($context, $credential, $payload),
            'prescriptions.upload' => $file === null ? throw new CoreApiException(422, 'FILE_REQUIRED', 'A prescription file is required.') : (new CoreApiPrescriptionUploadService())->upload($credential, $payload + ['clinic_id' => (int) $credential['clinic_id']], $file),
            'patients.register' => $this->register($context, $payload, $credential),
            default => throw new InvalidArgumentException('Unsupported Shopify proxy operation.'),
        };
    }

    /** @return array<string, mixed> */
    private function normalizeAvailabilityPayload(array $payload): array
    {
        if (! array_key_exists('from_date', $payload) && array_key_exists('date_from', $payload)) {
            $payload['from_date'] = $payload['date_from'];
        }
        if (! array_key_exists('to_date', $payload) && array_key_exists('date_to', $payload)) {
            $payload['to_date'] = $payload['date_to'];
        }
        if (! array_key_exists('from_date', $payload) && ! array_key_exists('to_date', $payload) && ! array_key_exists('date', $payload) && ! array_key_exists('dates', $payload)) {
            $timezone = new \DateTimeZone((string) (config('App')->appTimezone ?: date_default_timezone_get()));
            $today = new \DateTimeImmutable('today', $timezone);
            $payload['from_date'] = $today->format('d-m-Y');
            $payload['to_date'] = $today->modify('+1 day')->format('d-m-Y');
        }
        if (array_key_exists('doctor_id', $payload)) {
            $doctorReference = is_scalar($payload['doctor_id']) ? trim((string) $payload['doctor_id']) : '';
            if ($doctorReference === '') {
                throw new CoreApiException(422, 'DOCTOR_INVALID', 'The selected doctor is invalid.', ['doctor_id' => 'Select an active doctor available for online booking.']);
            }
            $doctorCode = db_connect()->table('doctors')->select('doctor_code')->where('doctor_code', $doctorReference)->where('status', 1)->where('online_booking', 1)->get()->getRow('doctor_code');
            if (! is_string($doctorCode) || trim($doctorCode) !== $doctorReference) {
                throw new CoreApiException(422, 'DOCTOR_INVALID', 'The selected doctor is invalid.', ['doctor_id' => 'Select an active doctor available for online booking.']);
            }
            if (array_key_exists('doctor_reference', $payload) && trim((string) $payload['doctor_reference']) !== $doctorReference) {
                throw new CoreApiException(422, 'DOCTOR_INVALID', 'The selected doctor is invalid.', ['doctor_id' => 'doctor_id and doctor_reference must identify the same doctor.']);
            }
            $payload['doctor_reference'] = $doctorReference;
        }
        if (! array_key_exists('specialization_reference', $payload) && array_key_exists('specialization_id', $payload)) {
            $payload['specialization_reference'] = 'specialization-' . (int) $payload['specialization_id'];
        }
        if (array_key_exists('service_id', $payload) && ! array_key_exists('service_reference', $payload)) {
            $serviceReference = is_scalar($payload['service_id']) ? trim((string) $payload['service_id']) : '';
            if (! preg_match('/^service-\d+$/', $serviceReference)) {
                throw new CoreApiException(422, 'SERVICE_INVALID', 'The selected service is invalid.', ['service_id' => 'Select a valid service.']);
            }
            $payload['service_reference'] = $serviceReference;
        }
        return $payload;
    }

    private function normalizeBookingPayload(array $payload): array
    {
        $payload['_doctor_field'] = array_key_exists('doctor_id', $payload) ? 'doctor_id' : 'doctor_reference';
        $payload['_service_field'] = array_key_exists('service_id', $payload) ? 'service_id' : 'service_reference';
        $payload['_start_time_field'] = array_key_exists('start_time', $payload) ? 'start_time' : 'appointment_time';

        if (array_key_exists('doctor_id', $payload)) {
            $doctorReference = is_scalar($payload['doctor_id']) ? trim((string) $payload['doctor_id']) : '';
            if ($doctorReference === '') {
                throw new CoreApiException(422, 'DOCTOR_INVALID', 'The selected doctor is invalid.', ['doctor_id' => 'Select an active doctor available for online booking.']);
            }
            $doctorCode = db_connect()->table('doctors')->select('doctor_code')->where('doctor_code', $doctorReference)->where('status', 1)->where('online_booking', 1)->get(1)->getRow('doctor_code');
            if (! is_string($doctorCode) || trim($doctorCode) !== $doctorReference) {
                throw new CoreApiException(422, 'DOCTOR_INVALID', 'The selected doctor is invalid.', ['doctor_id' => 'Select an active doctor available for online booking.']);
            }
            if (array_key_exists('doctor_reference', $payload) && trim((string) $payload['doctor_reference']) !== $doctorReference) {
                throw new CoreApiException(422, 'DOCTOR_INVALID', 'The selected doctor is invalid.', ['doctor_id' => 'doctor_id and doctor_reference must identify the same doctor.']);
            }
            $payload['doctor_reference'] = $doctorReference;
        }
        if (! array_key_exists('service_reference', $payload) && array_key_exists('service_id', $payload)) {
            $serviceReference = is_scalar($payload['service_id']) ? trim((string) $payload['service_id']) : '';
            if (! preg_match('/^service-\d+$/', $serviceReference)) {
                throw new CoreApiException(422, 'SERVICE_INVALID', 'The selected service is invalid.', ['service_id' => 'Select a valid service.']);
            }
            $payload['service_reference'] = $serviceReference;
        }
        if (! array_key_exists('appointment_time', $payload) && array_key_exists('start_time', $payload)) $payload['appointment_time'] = $payload['start_time'];
        if (array_key_exists('end_time', $payload)) $payload['_requested_end_time'] = $payload['end_time'];
        return $payload;
    }

    private function register(array $context, array $payload, array $credential): array
    {
        $customerId = trim((string) ($context['customer_id'] ?? ''));
        if ($customerId === '') $this->identityFailure('SHOPIFY_CUSTOMER_LOGIN_REQUIRED', 'Sign in to Shopify before continuing.', ['next_action' => 'SHOPIFY_CUSTOMER_LOGIN']);

        $patient = $payload['patient'] ?? null;
        if (! is_array($patient)) {
            throw new CoreApiException(422, 'PATIENT_DATA_INVALID', 'Patient details are required.', ['patient' => 'Submit a patient object with the required registration fields.']);
        }
        $validated = $this->validateSubmittedPatient($patient, $customerId);
        $resolved = $this->ensureCustomerPatientLink($context, $credential, $validated);
        return ['booking_enabled' => true, 'patient_reference' => $resolved['patient_reference']];
    }

    /** @return array{patient_id:int, patient_reference:string, already_registered:bool} */
    private function ensureCustomerPatientLink(array $context, array $credential, ?array $registrationFields = null): array
    {
        $customerId = trim((string) ($context['customer_id'] ?? ''));
        if ($customerId === '') $this->identityFailure('SHOPIFY_CUSTOMER_LOGIN_REQUIRED', 'Sign in to Shopify before continuing.', ['next_action' => 'SHOPIFY_CUSTOMER_LOGIN']);

        $links = new ShopifyPatientIdentityLinkModel();
        $store = (string) $context['store_domain'];
        if ($registrationFields === null) {
            $verified = $this->verifiedShopifyCustomer($context['configuration'], $customerId);
            $registrationFields = $this->verifiedRegistrationFields($verified);
            $customerRequired = $this->requiredCustomerDataFields($registrationFields);
            if ($customerRequired !== []) {
                throw new CoreApiException(422, 'SHOPIFY_CUSTOMER_DATA_INCOMPLETE', 'Required customer details are missing.', ['required_fields' => implode(',', $customerRequired)]);
            }
        }
        $registrationFields['clinic_id'] = (int) $credential['clinic_id'];
        $existing = $links->where('shopify_store_domain', $store)->where('shopify_customer_id', $customerId)->first();
        if ($existing !== null && (string) ($existing['link_status'] ?? '') === 'linked') {
            $patient = db_connect()->table('patients')->select('registration_number, clinic_id, status')->where('id', (int) $existing['patient_id'])->get()->getRowArray();
            if ($patient !== null && (int) $patient['clinic_id'] === (int) $credential['clinic_id'] && (int) $patient['status'] === 1) {
                return ['patient_id' => (int) $existing['patient_id'], 'patient_reference' => (string) $patient['registration_number'], 'already_registered' => true];
            }
            if ($patient !== null && (int) $patient['clinic_id'] !== (int) $credential['clinic_id']) {
                $this->identityFailure('SHOPIFY_SCOPE_INVALID', 'The Shopify customer is outside the configured clinic scope.', ['clinic' => 'The linked patient is outside the configured clinic scope.']);
            }
            $this->identityFailure('SHOPIFY_PATIENT_INACTIVE', 'The linked ROOTINS patient is inactive.', ['patient' => 'Reactivate the patient before booking.']);
        }

        $foreignLink = $links->where('shopify_customer_id', $customerId)->where('shopify_store_domain !=', $store)->where('link_status', 'linked')->first();
        if ($foreignLink !== null) $this->identityFailure('SHOPIFY_STORE_MISMATCH', 'The Shopify customer is linked to another store.', ['shopify_customer' => 'This customer cannot be linked across stores.']);

        $profileRequired = $this->requiredProfileFields($registrationFields);
        if ($profileRequired !== []) throw new CoreApiException(422, 'PATIENT_PROFILE_INCOMPLETE', 'The ROOTINS patient profile is incomplete for registration.', ['required_fields' => implode(',', $profileRequired)]);
        $result = $this->findExistingPatient($registrationFields, (int) $credential['clinic_id'], $links);
        if ($result === null) {
            $result = (new PatientRegistrationService())->register($registrationFields, null, ['source_type' => 'shopify', 'source_reference' => $customerId]);
        }
        $now = date('Y-m-d H:i:s');
        $linkData = ['shopify_store_domain' => $store, 'shopify_customer_id' => $customerId, 'patient_id' => (int) $result['id'], 'email_snapshot' => trim((string) ($registrationFields['email'] ?? '')), 'mobile_snapshot' => trim((string) ($registrationFields['mobile_number'] ?? '')), 'link_status' => 'linked', 'matched_by' => 'proxy_registration', 'active_patient_key' => 'shopify:' . (int) $result['id'], 'linked_at' => $now, 'created_at' => $now, 'updated_at' => $now];
        try {
            if ($existing !== null) $links->update((int) $existing['id'], $linkData);
            else $links->insert($linkData);
        } catch (\Throwable) {
            $duplicate = (new ShopifyPatientIdentityLinkModel())->where('shopify_store_domain', $store)->where('shopify_customer_id', $customerId)->where('link_status', 'linked')->first();
            if ($duplicate === null) {
                service('audit')->record('shopify.proxy_identity_link_failed', ['code' => 'DUPLICATE_IDENTITY_LINK']);
                throw new CoreApiException(409, 'SHOPIFY_IDENTITY_LINK_CONFLICT', 'The Shopify customer identity could not be linked safely.');
            }
            $result['id'] = (int) $duplicate['patient_id'];
            $result['registration_number'] = (string) db_connect()->table('patients')->select('registration_number')->where('id', (int) $duplicate['patient_id'])->get()->getRow('registration_number');
        }
        service('audit')->record('shopify.proxy_patient_registered', ['code' => 'IDENTITY_LINKED', 'booking_enabled' => true]);
        return ['patient_id' => (int) $result['id'], 'patient_reference' => (string) $result['registration_number'], 'already_registered' => false];
    }

    /** @return array<string, mixed> */
    private function validateSubmittedPatient(array $patient, string $customerId): array
    {
        $submittedCustomerId = array_key_exists('shopify_customer_id', $patient) ? $this->patientString($patient['shopify_customer_id']) : null;
        if ($submittedCustomerId !== null && $submittedCustomerId !== $customerId) {
            $this->identityFailure('SHOPIFY_CUSTOMER_ID_MISMATCH', 'The submitted Shopify customer does not match the signed-in customer.', ['shopify_customer_id' => 'The submitted customer identity must match the signed App Proxy identity.']);
        }

        $errors = [];
        $firstName = $this->patientString($patient['first_name'] ?? null);
        $lastName = $this->patientString($patient['last_name'] ?? null);
        if ($this->invalidPatientName($firstName)) $errors['first_name'] = 'First name is required and must be 1–100 characters.';
        if ($this->invalidPatientName($lastName)) $errors['last_name'] = 'Last name is required and must be 1–100 characters.';

        $gender = strtolower($this->patientString($patient['gender'] ?? null));
        if (! in_array($gender, ['male', 'female', 'other'], true)) $errors['gender'] = 'Select a valid gender.';

        $bookingFor = strtolower($this->patientString($patient['booking_for'] ?? null));
        if ($bookingFor !== 'self') $errors['booking_for'] = 'Select a supported booking subject.';

        $countryCode = TelephoneCountryCode::normalizeCode($this->patientString($patient['country_code'] ?? null));
        $country = $countryCode === null ? null : (new TelephoneCountryCode())->activeByCode($countryCode);
        if ($country === null) $errors['country_code'] = 'Enter a valid country code.';

        $mobile = $this->patientString($patient['mobile'] ?? null);
        $mobileNumber = $this->patientString($patient['mobile_number'] ?? null);
        $phoneDigits = (new TelephoneContactService())->normalizedMobile($mobile !== '' ? $mobile : $mobileNumber);
        $numberDigits = (new TelephoneContactService())->normalizedMobile($mobileNumber);
        $normalizedMobile = '';
        if ($phoneDigits === null) {
            $errors['mobile'] = 'Mobile number is required.';
        } elseif ($countryCode !== null && str_starts_with($phoneDigits, $countryCode) && strlen($phoneDigits) > strlen($countryCode)) {
            $normalizedMobile = substr($phoneDigits, strlen($countryCode));
        } else {
            $normalizedMobile = $phoneDigits;
        }
        if ($numberDigits !== null) {
            if (preg_match('/^[0-9]{7,15}$/', $numberDigits) !== 1) {
                $errors['mobile_number'] = 'Enter a valid mobile number.';
            } elseif ($normalizedMobile !== '' && $numberDigits !== $normalizedMobile) {
                $errors['mobile_number'] = 'Mobile values must identify the same number.';
            } else {
                $normalizedMobile = $numberDigits;
            }
        }
        if ($normalizedMobile === '' || preg_match('/^[0-9]{7,15}$/', $normalizedMobile) !== 1 || $this->isPlaceholderValue($normalizedMobile, true)) {
            $errors['mobile'] = 'Enter a valid mobile number.';
        }

        $email = strtolower($this->patientString($patient['email'] ?? null));
        if ($email !== '' && ($this->isPlaceholderValue($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 255)) $errors['email'] = 'Enter a valid email address.';

        $age = $patient['age'] ?? null;
        if ($age !== null && $this->patientString($age) !== '' && (filter_var($age, FILTER_VALIDATE_INT) === false || (int) $age < 0 || (int) $age > 150)) $errors['age'] = 'Enter a valid age.';

        $dateOfBirth = $this->patientString($patient['date_of_birth'] ?? null);
        if ($dateOfBirth !== '') {
            $dateOfBirth = $this->normalizePatientDate($dateOfBirth);
            if ($dateOfBirth === null) $errors['date_of_birth'] = 'Enter a valid date of birth.';
        }

        if ($errors !== []) throw new CoreApiException(422, 'PATIENT_DATA_INVALID', 'Patient data is invalid.', $errors);

        return [
            'clinic_id' => 0,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => $gender,
            'date_of_birth' => $dateOfBirth !== '' ? $dateOfBirth : null,
            'email' => $email,
            'country_code' => $countryCode,
            'telephone_country_code_id' => (int) $country['id'],
            'country_code_id' => $country['country_id'] !== null ? (int) $country['country_id'] : null,
            'mobile_number' => $normalizedMobile,
            'mobile_normalized' => $normalizedMobile,
            'address' => $this->patientString($patient['address'] ?? null) ?: null,
            'source_type' => 'shopify',
        ];
    }

    private function patientString(mixed $value): string
    {
        if ($value === null) return '';
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function invalidPatientName(string $value): bool
    {
        return $value === '' || mb_strlen($value) > 100 || $this->isPlaceholderValue($value);
    }

    private function normalizePatientDate(string $value): ?string
    {
        foreach (['Y-m-d', 'd-m-Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) && $date->format($format) === $value && $date->format('Y-m-d') <= date('Y-m-d')) return $date->format('Y-m-d');
        }
        return null;
    }

    /** @return array<string, mixed> */
    private function verifiedShopifyCustomer(array $configuration, string $customerId): array
    {
        try {
            $shopify = new ShopifyConfiguration([
                'store_domain' => (string) $configuration['store_domain'],
                'client_id' => (string) $configuration['client_id'],
                'client_secret' => (string) $configuration['client_secret'],
                'api_version' => '2025-01',
                'app_url' => 'https://core-api-proxy.invalid',
                'webhook_secret' => 'core-api-proxy-not-used',
                'encryption_key' => 'core-api-proxy-not-used',
            ], 'core_api_proxy');
            return (new ShopifyCustomerLookupService(runtimeConfiguration: $shopify))->lookup($customerId);
        } catch (ShopifyCustomerLookupException $exception) {
            service('audit')->record('shopify.proxy_customer_lookup_failed', ['code' => $exception->errorCode]);
            throw new CoreApiException(422, 'SHOPIFY_CUSTOMER_DATA_INCOMPLETE', 'Verified Shopify customer data could not be loaded safely.');
        }
    }

    /** @return array<string, mixed> */
    private function verifiedRegistrationFields(array $customer): array
    {
        $fields = [
            'first_name' => trim((string) ($customer['first_name'] ?? '')),
            'last_name' => trim((string) ($customer['last_name'] ?? '')),
            'email' => strtolower(trim((string) ($customer['email'] ?? ''))),
            'mobile_number' => '',
            'country_code' => '',
        ];
        $digits = (new TelephoneContactService())->normalizedMobile($customer['mobile'] ?? null);
        if ($digits !== null && preg_match('/^[0-9]{7,15}$/', $digits) === 1) {
            foreach ((new TelephoneCountryCode())->select('normalized_code')->where('status', 1)->orderBy('normalized_code', 'DESC')->findAll() as $code) {
                $prefix = trim((string) ($code['normalized_code'] ?? ''));
                $national = $prefix !== '' && str_starts_with($digits, $prefix) ? substr($digits, strlen($prefix)) : '';
                if ($national !== '' && preg_match('/^[0-9]{7,15}$/', $national) === 1) {
                    $fields['country_code'] = $prefix;
                    $fields['mobile_number'] = $national;
                    break;
                }
            }
            if ($fields['mobile_number'] === '') $fields['mobile_number'] = $digits;
        }
        return $fields;
    }

    /** @return list<string> */
    private function requiredCustomerDataFields(array $fields): array
    {
        $required = [];
        foreach (['first_name', 'last_name'] as $field) {
            $value = trim((string) ($fields[$field] ?? ''));
            if ($this->isPlaceholderValue($value) || $value === '' || mb_strlen($value) > 100) $required[] = $field;
        }

        $mobile = trim((string) ($fields['mobile_number'] ?? ''));
        $mobileValid = ! $this->isPlaceholderValue($mobile, true) && preg_match('/^[0-9]{7,15}$/', $mobile) === 1;
        if (! $mobileValid) {
            $required[] = 'mobile';
        } else {
            $countryCode = trim((string) ($fields['country_code'] ?? ''));
            if ($countryCode === '' || TelephoneCountryCode::normalizeCode($countryCode) === null || (new TelephoneCountryCode())->activeByCode($countryCode) === null) $required[] = 'country_code';
        }

        $email = trim((string) ($fields['email'] ?? ''));
        if ($email !== '' && ($this->isPlaceholderValue($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 255)) $required[] = 'email';

        return array_values(array_unique($required));
    }

    private function isPlaceholderValue(string $value, bool $numeric = false): bool
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '' || in_array($normalized, ['-', '--', 'n/a', 'na', 'none', 'null', 'unknown', 'undefined', 'test'], true)) return true;
        if ($numeric && (preg_match('/^(\d)\1+$/', $normalized) === 1 || in_array($normalized, ['1234567', '12345678', '123456789', '1234567890'], true))) return true;
        return false;
    }

    /** @return list<string> */
    private function requiredProfileFields(array $fields): array
    {
        $required = [];
        foreach (['date_of_birth', 'gender'] as $field) {
            $nullable = false;
            foreach (db_connect()->getFieldData('patients') as $column) if ((string) ($column->name ?? '') === $field) $nullable = (bool) ($column->nullable ?? false);
            if (! $nullable && trim((string) ($fields[$field] ?? '')) === '') $required[] = $field;
        }
        return $required;
    }

    /** @return array{id: int, registration_number: string}|null */
    private function findExistingPatient(array $fields, int $clinicId, ShopifyPatientIdentityLinkModel $links): ?array
    {
        $mobile = preg_replace('/[^0-9]/', '', (string) ($fields['mobile_number'] ?? ''));
        $country = trim((string) ($fields['country_code'] ?? ''));
        $query = db_connect()->table('patients')->select('id, registration_number')->where('clinic_id', $clinicId)->where('status', 1);
        if ($mobile !== '' && $country !== '') {
            $query->where('country_code', $country)->where('mobile_normalized', $mobile);
        } elseif (trim((string) ($fields['email'] ?? '')) !== '') {
            $query->where('email', strtolower(trim((string) $fields['email'])));
        } else {
            return null;
        }
        $matches = $query->get()->getResultArray();
        $patient = count($matches) === 1 ? $matches[0] : null;
        if ($patient === null) return null;

        $conflict = $links->where('patient_id', (int) $patient['id'])->where('link_status', 'linked')->first();
        if ($conflict !== null) $this->identityFailure('SHOPIFY_IDENTITY_ALREADY_LINKED', 'This ROOTINS patient is already linked to another Shopify customer.', ['patient' => 'Choose the patient identity already associated with this customer.'], 409);

        return ['id' => (int) $patient['id'], 'registration_number' => (string) $patient['registration_number']];
    }

    private function identityFailure(string $code, string $message, array $fields = [], int $status = 403): never
    {
        service('audit')->record('shopify.proxy_booking_identity_rejected', ['code' => $code, 'operation' => 'appointments.book']);
        throw new CoreApiException($status, $code, $message, $fields);
    }

    /** @return array<string, mixed> */
    private function credential(): array
    {
        $query = (new CoreApiCredentialModel())->where('status', 'active');
        $credential = $query->orderBy('id', 'ASC')->first();
        if ($credential === null) throw new CoreApiException(503, 'CORE_API_UNAVAILABLE', 'The ROOTINS Core API is not configured for this clinic.');
        unset($credential['secret_encrypted']);
        return $credential;
    }

    private function rateLimit(int $credentialId, int $limit): void
    {
        try { $result = (new CoreApiRateLimitService())->consume($credentialId, $limit); } catch (\Throwable) { throw new CoreApiException(503, 'RATE_LIMITER_UNAVAILABLE', 'The request could not be admitted safely.'); }
        if (! $result['allowed']) throw new CoreApiException(429, 'RATE_LIMITED', 'Too many Shopify proxy requests.', [], $result['retry_after']);
    }
}
