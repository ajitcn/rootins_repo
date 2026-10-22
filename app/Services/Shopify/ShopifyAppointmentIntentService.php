<?php

namespace App\Services\Shopify;

use App\Modules\CoreApi\Services\CoreApiException;
use App\Modules\Shopify\Models\ShopifyAppointmentIntentModel;
use App\Services\AppointmentBillingService;
use Admin\Models\Doctor\DoctorLeaveModel;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class ShopifyAppointmentIntentService
{
    private const HOLD_MINUTES = 30;
    private const ACTIVE_INTENT_STATUSES = ['pending_payment', 'paid'];
    private const ACTIVE_APPOINTMENT_STATUSES = ['scheduled', 'confirmed', 'checked_in', 'in_queue'];

    /** @return array<string, mixed> */
    public function create(array $context, array $credential, array $input): array
    {
        $store = strtolower(trim((string) ($context['store_domain'] ?? '')));
        $customerId = trim((string) ($context['customer_id'] ?? ''));
        $patientId = (int) ($context['patient_id'] ?? 0);
        // The proxy configuration authenticates the store; it is not a
        // clinical scope. Resolve the billing/operational clinic from the
        // selected ROOTINS doctor after validating the public reference.
        $clinicId = 0;
        $idempotencyKey = trim((string) ($input['idempotency_key'] ?? ''));
        $doctorReference = trim((string) ($input['doctor_reference'] ?? ''));
        $serviceReference = trim((string) ($input['service_reference'] ?? ''));
        $slotReference = trim((string) ($input['slot_reference'] ?? ''));
        $date = $this->parseDate((string) ($input['appointment_date'] ?? ''));
        $time = $this->normalizeTime((string) ($input['appointment_time'] ?? ''));
        $endTimeValue = array_key_exists('_requested_end_time', $input) ? (string) $input['_requested_end_time'] : '';
        $endTime = $this->normalizeTime($endTimeValue);
        $consultationMode = strtolower(trim((string) ($input['consultation_mode'] ?? $input['appointment_type'] ?? 'in_person')));
        $doctorField = (string) ($input['_doctor_field'] ?? 'doctor_reference');
        $serviceField = (string) ($input['_service_field'] ?? 'service_reference');
        $timeField = (string) ($input['_start_time_field'] ?? 'appointment_time');
        $errors = [];

        if ($customerId === '') $errors['customer'] = 'A linked Shopify customer is required.';
        if ($patientId < 1) $errors['patient'] = 'A linked ROOTINS patient is required.';
        if ($doctorReference === '') $errors[$doctorField] = 'Select a valid doctor available for online booking.';
        if (! preg_match('/^service-(\d+)$/', $serviceReference, $serviceMatch)) $errors[$serviceField] = 'Select a valid service.';
        if ($date === null || $date < date('Y-m-d')) $errors['appointment_date'] = 'Enter a valid appointment date as dd-mm-yyyy.';
        if ($time === null) $errors[$timeField] = 'Enter a valid start time as HH:mm.';
        if ($endTimeValue === '' || $endTime === null) $errors['end_time'] = 'Enter a valid end time as HH:mm.';
        if ($slotReference === '') $errors['slot_reference'] = 'Select a valid slot from availability.';
        if (! in_array($consultationMode, ['in_person', 'video', 'phone'], true)) $errors['consultation_mode'] = 'Select a valid consultation mode.';
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 191) $errors['idempotency_key'] = 'A valid Idempotency-Key header is required.';
        if ($this->consentRequired() && ! $this->acceptedConsent($input['consent'] ?? null)) $errors['consent'] = 'Appointment consent is required.';
        if ($errors !== []) throw new CoreApiException(422, $this->validationCode($errors), 'Review the booking fields.', $errors);

        $slotParts = $this->slotParts($slotReference);
        if ($slotParts === null || $slotParts['doctor_reference'] !== $doctorReference || ! in_array($slotParts['service_reference'], ['service-0', $serviceReference], true) || $slotParts['date'] !== $date || $slotParts['start_time'] !== substr((string) $time, 0, 5)) {
            throw new CoreApiException(422, 'INVALID_SLOT', 'The selected slot reference is invalid.', ['slot_reference' => 'Select a slot returned for the selected doctor, service, date, and time.']);
        }

        $db = db_connect();
        $intentModel = new ShopifyAppointmentIntentModel();
        $existing = $intentModel->where('shopify_store_domain', $store)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            if ((int) $existing['patient_id'] !== $patientId || (string) $existing['appointment_date'] !== $date || substr((string) $existing['appointment_time'], 0, 5) !== $time || (string) ($existing['consultation_mode'] ?? 'in_person') !== $consultationMode) {
                throw new CoreApiException(409, 'APPOINTMENT_INTENT_REUSED', 'This Idempotency-Key was used for different booking details.');
            }
            return $this->intentResponse($existing);
        }

        $doctor = $db->table('doctors')->where('doctor_code', $doctorReference)->where('status', 1)->where('online_booking', 1)->get(1)->getRowArray();
        $clinicId = (int) ($doctor['clinic_id'] ?? 0);
        $service = $db->table('admin_services')->groupStart()->where('clinic_id', $clinicId)->orWhere('clinic_id', null)->groupEnd()->where('id', (int) $serviceMatch[1])->where('status', 1)->get(1)->getRowArray();
        if ($doctor === null) throw new CoreApiException(422, 'DOCTOR_INVALID', 'The selected doctor is invalid.', [$doctorField => 'The selected doctor is unavailable for online booking.']);
        if ($clinicId < 1) throw new CoreApiException(503, 'BILLING_CLINIC_UNAVAILABLE', 'Billing scope is unavailable for the selected doctor.');
        if ($service === null) throw new CoreApiException(422, 'SERVICE_INVALID', 'The selected service is invalid.', [$serviceField => 'The selected service is unavailable.']);

        $db->transBegin();
        try {
            $day = (int) (new DateTimeImmutable($date, $this->timezone()))->format('N');
            $schedule = $db->query('SELECT * FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ? AND status = 1 AND deleted_at IS NULL AND start_time <= ? AND end_time > ? ORDER BY start_time ASC LIMIT 1 FOR UPDATE', [(int) $doctor['id'], $day, $time, $time])->getRowArray();
            if ($schedule === null) throw new CoreApiException(409, 'SLOT_UNAVAILABLE', 'The selected appointment slot is unavailable.');
            $scheduleDuration = max(1, (int) ($schedule['slot_duration'] ?? 15));
            $duration = $this->serviceDuration($service, $scheduleDuration);
            if ($duration > $scheduleDuration) throw new CoreApiException(422, 'SERVICE_SLOT_INCOMPATIBLE', 'The selected service requires more time than this schedule slot allows.', ['slot_reference' => 'Select a longer compatible slot.']);
            $slotEnd = (new DateTimeImmutable($date . ' ' . $time, $this->timezone()))->modify('+' . $duration . ' minutes')->format('H:i:s');
            if ($endTime !== $slotEnd) throw new CoreApiException(422, 'SERVICE_SLOT_INCOMPATIBLE', 'The selected service does not fit the selected slot.', ['end_time' => 'End time must match the selected service duration.']);
            if (! $this->isSlotAligned((string) $schedule['start_time'], $time, $scheduleDuration)) throw new CoreApiException(422, 'INVALID_SLOT', 'The selected appointment slot is not aligned to the schedule.', ['start_time' => 'Select one of the available slots.']);
            $breakStart = trim((string) ($schedule['break_start_time'] ?? ''));
            $breakEnd = trim((string) ($schedule['break_end_time'] ?? ''));
            if ($this->leaveOverlaps((int) $doctor['id'], $date, $time, $slotEnd) || (($breakStart !== '' && $breakEnd !== '' && $time < $breakEnd && $slotEnd > $breakStart) || $slotEnd > (string) $schedule['end_time'])) throw new CoreApiException(409, 'SLOT_UNAVAILABLE', 'The selected appointment slot is unavailable.');
            $nowLocal = new DateTimeImmutable('now', $this->timezone());
            if ($date < $nowLocal->format('Y-m-d') || ($date === $nowLocal->format('Y-m-d') && $time <= $nowLocal->format('H:i:s'))) throw new CoreApiException(409, 'SLOT_UNAVAILABLE', 'The selected appointment slot is unavailable.');
            $now = date('Y-m-d H:i:s');
            $this->assertSlotAvailable($db, $clinicId, (int) $doctor['id'], $date, $time, $slotEnd, $now, (int) ($schedule['max_patients'] ?? 0));
            $amount = $this->amount($db, (int) $doctor['id'], (int) $service['id'], $date, (float) ($service['amount'] ?? 0));
            $data = [
                'public_reference' => 'RINT-' . strtoupper(bin2hex(random_bytes(8))),
                'shopify_store_domain' => $store, 'shopify_customer_id' => $customerId, 'patient_id' => $patientId,
                'patient_reference' => (string) ($context['patient_reference'] ?? ''), 'clinic_id' => $clinicId,
                'doctor_id' => (int) $doctor['id'], 'service_id' => (int) $service['id'], 'appointment_date' => $date,
                'appointment_time' => $time, 'consultation_mode' => $consultationMode, 'duration_minutes' => $duration, 'amount' => $amount, 'currency' => 'INR',
                'idempotency_key' => $idempotencyKey, 'status' => 'pending_payment',
                'expires_at' => date('Y-m-d H:i:s', time() + self::HOLD_MINUTES * 60), 'created_at' => $now, 'updated_at' => $now,
            ];
            if (! $intentModel->insert($data, true)) throw new RuntimeException('Appointment intent insert failed.');
            $data['id'] = (int) $intentModel->getInsertID();
            if (! $db->transCommit()) throw new RuntimeException('Appointment intent transaction failed.');
        } catch (CoreApiException|RuntimeException $exception) {
            $db->transRollback();
            throw $exception;
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        service('audit')->record('shopify.appointment_intent_created', ['intent_reference' => $data['public_reference'], 'clinic_id' => $clinicId, 'doctor_id' => (int) $doctor['id']]);
        return $this->intentResponse($data);
    }

    /** @return array<string, mixed> */
    public function finalizePaid(array $event, array $payload): array
    {
        $reference = trim((string) ($payload['rootins_appointment_intent'] ?? ''));
        $store = strtolower(trim((string) ($event['store_domain'] ?? '')));
        $orderId = trim((string) ($payload['id'] ?? $payload['order_id'] ?? ''));
        if ($reference === '' || $orderId === '') throw new RuntimeException('Appointment payment metadata is missing.');
        if (strtolower(trim((string) ($payload['financial_status'] ?? ''))) !== 'paid') throw new RuntimeException('Shopify order payment is not confirmed.');

        $db = db_connect();
        $db->transBegin();
        try {
            $intent = $db->query('SELECT * FROM shopify_appointment_intents WHERE shopify_store_domain = ? AND public_reference = ? LIMIT 1 FOR UPDATE', [$store, $reference])->getRowArray();
            if ($intent === null) throw new RuntimeException('Appointment intent was not found.');
            if ((string) $intent['status'] === 'booked') {
                if ((string) ($intent['shopify_order_id'] ?? '') !== $orderId) throw new RuntimeException('Appointment intent is already linked to another Shopify order.');
                $db->transCommit();
                return ['status' => 'booked', 'appointment_id' => (int) $intent['appointment_id'], 'intent_reference' => $reference];
            }
            if (! in_array((string) $intent['status'], self::ACTIVE_INTENT_STATUSES, true)) throw new RuntimeException('Appointment intent is not payable.');
            if ((string) ($payload['customer_id'] ?? '') !== (string) $intent['shopify_customer_id']) throw new RuntimeException('Shopify customer does not match the appointment intent.');
            if ((int) ($intent['clinic_id'] ?? 0) < 1 || $db->table('clinics')->where('id', (int) $intent['clinic_id'])->where('status', 1)->get(1)->getRowArray() === null) {
                throw new RuntimeException('BILLING_CLINIC_UNAVAILABLE');
            }
            if (strtotime((string) $intent['expires_at']) < time()) throw new RuntimeException('Appointment intent has expired.');
            if (strtoupper((string) ($payload['currency'] ?? '')) !== strtoupper((string) $intent['currency']) || abs((float) ($payload['total_price'] ?? 0) - (float) $intent['amount']) > 0.01) throw new RuntimeException('Shopify payment amount or currency does not match the appointment intent.');
            $day = (int) (new DateTimeImmutable((string) $intent['appointment_date'], $this->timezone()))->format('N');
            $schedule = $db->query('SELECT * FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ? AND status = 1 AND deleted_at IS NULL AND start_time <= ? AND end_time > ? ORDER BY start_time ASC LIMIT 1 FOR UPDATE', [(int) $intent['doctor_id'], $day, $intent['appointment_time'], $intent['appointment_time']])->getRowArray();
            if ($schedule === null) throw new RuntimeException('The paid appointment slot is no longer available.');
            $duration = max(1, (int) ($schedule['slot_duration'] ?? $intent['duration_minutes']));
            $slotEnd = (new DateTimeImmutable($intent['appointment_date'] . ' ' . $intent['appointment_time'], $this->timezone()))->modify('+' . $duration . ' minutes')->format('H:i:s');
            if (! $this->isSlotAligned((string) $schedule['start_time'], (string) $intent['appointment_time'], $duration)) throw new RuntimeException('The paid appointment slot is no longer available.');
            $breakStart = trim((string) ($schedule['break_start_time'] ?? '')); $breakEnd = trim((string) ($schedule['break_end_time'] ?? ''));
            if ($this->leaveOverlaps((int) $intent['doctor_id'], (string) $intent['appointment_date'], (string) $intent['appointment_time'], $slotEnd) || (($breakStart !== '' && $breakEnd !== '' && $intent['appointment_time'] < $breakEnd && $slotEnd > $breakStart) || $slotEnd > (string) $schedule['end_time'])) throw new RuntimeException('The paid appointment slot is inside a break or outside working hours.');
            $this->assertSlotAvailable($db, (int) $intent['clinic_id'], (int) $intent['doctor_id'], (string) $intent['appointment_date'], (string) $intent['appointment_time'], $slotEnd, date('Y-m-d H:i:s'), (int) ($schedule['max_patients'] ?? 0), (int) $intent['id']);
            $now = date('Y-m-d H:i:s');
            $db->table('appointments')->insert(['appointment_number' => 'TMP-' . bin2hex(random_bytes(8)), 'patient_id' => (int) $intent['patient_id'], 'doctor_id' => (int) $intent['doctor_id'], 'clinic_id' => (int) $intent['clinic_id'], 'appointment_date' => $intent['appointment_date'], 'appointment_time' => $intent['appointment_time'], 'duration_minutes' => $duration, 'appointment_type' => (string) ($intent['consultation_mode'] ?? 'in_person'), 'service_name' => $this->serviceName($db, (int) $intent['service_id']), 'status' => 'scheduled', 'created_by_type' => 'patient', 'created_by_id' => (int) $intent['patient_id'], 'booking_source' => 'shopify', 'created_at' => $now, 'updated_at' => $now]);
            $appointmentId = (int) $db->insertID();
            if ($appointmentId < 1 || ! $db->table('appointments')->where('id', $appointmentId)->update(['appointment_number' => sprintf('APT-%06d', $appointmentId)])) throw new RuntimeException('Final appointment creation failed.');
            if (! $db->table('appointment_services')->insert(['appointment_id' => $appointmentId, 'service_id' => (int) $intent['service_id'], 'service_name' => $this->serviceName($db, (int) $intent['service_id']), 'created_at' => $now])) throw new RuntimeException('Appointment service link failed.');
            (new AppointmentBillingService())->createOrUpdate((int) $intent['clinic_id'], (int) $intent['patient_id'], $appointmentId, (float) $intent['amount'], (string) $intent['currency'], 'paid', 'shopify-appointment-' . $intent['id']);
            $db->table('shopify_appointment_intents')->where('id', (int) $intent['id'])->update(['status' => 'booked', 'shopify_order_id' => $orderId, 'shopify_order_number' => (string) ($payload['order_number'] ?? ''), 'appointment_id' => $appointmentId, 'paid_at' => $now, 'updated_at' => $now]);
            if (! $db->transCommit()) throw new RuntimeException('Final appointment transaction failed.');
        } catch (\Throwable $exception) { $db->transRollback(); throw $exception; }
        service('audit')->record('shopify.appointment_booked_after_payment', ['intent_reference' => $reference, 'appointment_id' => $appointmentId, 'shopify_order_id' => $orderId]);
        return ['status' => 'booked', 'intent_reference' => $reference, 'appointment_id' => $appointmentId];
    }

    private function assertSlotAvailable($db, int $clinicId, int $doctorId, string $date, string $start, string $end, string $now, int $capacity, int $ignoreIntentId = 0): void
    {
        $appointments = $db->query("SELECT COUNT(*) AS total FROM appointments WHERE clinic_id = ? AND doctor_id = ? AND appointment_date = ? AND appointment_time < ? AND ADDTIME(appointment_time, SEC_TO_TIME(COALESCE(duration_minutes, 15) * 60)) > ? AND status IN ('scheduled','confirmed','checked_in','in_queue') FOR UPDATE", [$clinicId, $doctorId, $date, $end, $start])->getRow('total');
        $intentSql = "SELECT COUNT(*) AS total FROM shopify_appointment_intents WHERE clinic_id = ? AND doctor_id = ? AND appointment_date = ? AND appointment_time < ? AND ADDTIME(appointment_time, SEC_TO_TIME(COALESCE(duration_minutes, 15) * 60)) > ? AND status IN ('pending_payment','paid') AND expires_at > ?";
        $params = [$clinicId, $doctorId, $date, $end, $start, $now];
        if ($ignoreIntentId > 0) { $intentSql .= ' AND id <> ?'; $params[] = $ignoreIntentId; }
        $intents = $db->query($intentSql . ' FOR UPDATE', $params)->getRow('total');
        if ((int) $appointments > 0 || (int) $intents > 0 || ($capacity > 0 && ((int) $appointments + (int) $intents) >= $capacity)) throw new CoreApiException(409, 'SLOT_UNAVAILABLE', 'The selected appointment slot is already booked.');
    }

    private function amount($db, int $doctorId, int $serviceId, string $date, float $serviceAmount): float
    {
        if ($serviceAmount > 0) return round($serviceAmount, 2);
        $fee = $db->table('doctor_consultation_fees')->where('doctor_id', $doctorId)->where('status', 1)->where('effective_from <=', $date)->groupStart()->where('effective_to', null)->orWhere('effective_to >=', $date)->groupEnd()->orderBy('effective_from', 'DESC')->get(1)->getRow('fee');
        return round((float) $fee, 2);
    }

    private function serviceDuration(array $service, int $scheduleDuration): int
    {
        $configured = (int) ($service['duration_minutes'] ?? 0);
        if ($configured > 0) return $configured;
        // Existing services do not carry a duration column. In that schema,
        // the locked doctor schedule is the authoritative duration. If a
        // service duration is added later, the configured value above is used
        // and the compatibility check remains enforced.
        return $scheduleDuration;
    }

    private function serviceName($db, int $serviceId): string { return (string) ($db->table('admin_services')->select('name')->where('id', $serviceId)->get(1)->getRow('name') ?? 'Consultation'); }
    private function intentResponse(array $intent): array { return ['booking_intent_reference' => (string) $intent['public_reference'], 'status' => (string) $intent['status'], 'amount' => number_format((float) $intent['amount'], 2, '.', ''), 'currency' => (string) $intent['currency'], 'expires_at' => (string) $intent['expires_at'], 'checkout' => ['attributes' => [['name' => 'rootins_appointment_intent', 'value' => (string) $intent['public_reference']]], 'line_item_properties' => ['rootins_appointment_intent' => (string) $intent['public_reference']]]]; }
    /** @param array<string, string> $errors */
    private function validationCode(array $errors): string
    {
        if (isset($errors['appointment_date'])) return 'INVALID_DATE';
        if (isset($errors['appointment_time']) || isset($errors['start_time'])) return 'INVALID_TIME';
        if (isset($errors['end_time'])) return 'INVALID_TIME';
        if (isset($errors['slot_reference'])) return 'INVALID_SLOT';
        if (isset($errors['doctor_id']) || isset($errors['doctor_reference'])) return 'DOCTOR_INVALID';
        if (isset($errors['service_id']) || isset($errors['service_reference'])) return 'SERVICE_INVALID';
        if (isset($errors['consent'])) return 'CONSENT_REQUIRED';
        if (isset($errors['consultation_mode'])) return 'CONSULTATION_MODE_INVALID';
        if (isset($errors['idempotency_key'])) return 'IDEMPOTENCY_KEY_REQUIRED';
        return 'APPOINTMENT_INTENT_INVALID';
    }
    private function consentRequired(): bool { return filter_var(env('SHOPIFY_APPOINTMENT_CONSENT_REQUIRED', false), FILTER_VALIDATE_BOOLEAN); }
    private function acceptedConsent(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $consent) {
                if (in_array(strtolower(trim((string) $consent)), ['1', 'true', 'yes', 'accepted'], true)) return true;
            }
            return false;
        }
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'accepted'], true);
    }
    private function parseDate(string $value): ?string { $date = DateTimeImmutable::createFromFormat('!d-m-Y', trim($value), $this->timezone()); $errors = DateTimeImmutable::getLastErrors(); return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) && $date->format('d-m-Y') === trim($value) ? $date->format('Y-m-d') : null; }
    /** @return array{doctor_reference: string, service_reference: string, date: string, start_time: string}|null */
    private function slotParts(string $reference): ?array
    {
        if (! preg_match('/^(.+)-service-(\d+)-(\d{8})-(\d{4})$/', $reference, $matches)) return null;
        $date = DateTimeImmutable::createFromFormat('!Ymd', $matches[3], $this->timezone());
        if ($date === false || $date->format('Ymd') !== $matches[3]) return null;
        return ['doctor_reference' => $matches[1], 'service_reference' => 'service-' . $matches[2], 'date' => $date->format('Y-m-d'), 'start_time' => substr($matches[4], 0, 2) . ':' . substr($matches[4], 2, 2)];
    }
    private function normalizeTime(string $value): ?string { $value = trim($value); foreach (['!H:i:s', '!H:i', '!h:i A'] as $format) { $time = DateTimeImmutable::createFromFormat($format, $value, $this->timezone()); $errors = DateTimeImmutable::getLastErrors(); if ($time !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) return $time->format('H:i:s'); } return null; }
    private function timezone(): DateTimeZone { return new DateTimeZone((string) (config('App')->appTimezone ?: date_default_timezone_get())); }
    private function isSlotAligned(string $scheduleStart, string $time, int $duration): bool { $start = new DateTimeImmutable('2000-01-01 ' . substr($scheduleStart, 0, 8), $this->timezone()); $slot = new DateTimeImmutable('2000-01-01 ' . $time, $this->timezone()); $seconds = $slot->getTimestamp() - $start->getTimestamp(); return $seconds >= 0 && $seconds % ($duration * 60) === 0; }
    private function leaveOverlaps(int $doctorId, string $date, string $start, string $end): bool { foreach ((new DoctorLeaveModel())->where('doctor_id', $doctorId)->where('status', 1)->where('start_date <=', $date)->where('end_date >=', $date)->findAll() as $leave) { $leaveStart = trim((string) ($leave['start_time'] ?? '')); $leaveEnd = trim((string) ($leave['end_time'] ?? '')); if ($leaveStart === '' || $leaveEnd === '' || ($start < $leaveEnd && $end > $leaveStart)) return true; } return false; }
}
