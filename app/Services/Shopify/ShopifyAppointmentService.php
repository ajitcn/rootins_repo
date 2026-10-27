<?php

namespace App\Services\Shopify;

use Admin\Models\Doctor\DoctorLeaveModel;
use Admin\Models\PatientModel;
use App\Modules\Shopify\Models\ShopifyAppointmentBookingModel;
use App\Modules\Shopify\Models\ShopifyPatientIdentityLinkModel;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * Appointment boundary for Shopify.
 *
 * The core appointment tables remain the source of truth. This service only
 * stores Shopify's external reference and applies the same schedule, leave,
 * patient-conflict, and status rules used by the Patient booking flow.
 */
final class ShopifyAppointmentService
{
    private const ACTIVE_APPOINTMENT_STATUSES = ['scheduled', 'confirmed', 'checked_in', 'in_queue'];

    private const PATIENT_EDITABLE_STATUSES = ['scheduled', 'confirmed'];

    private const DATE_FORMAT = 'd-m-Y';

    public function __construct(
        private readonly ?ShopifyConfiguration $configuration = null,
        private readonly ?ShopifyHandoffService $handoffService = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function availability(array $handoff, ?int $doctorId = null, ?int $serviceId = null, ?string $displayDate = null): array
    {
        $scope = $this->scope($handoff);
        $date = $displayDate === null || trim($displayDate) === ''
            ? date('Y-m-d')
            : $this->parseDisplayDate($displayDate);

        if ($date === null) {
            throw new InvalidArgumentException('Enter a valid appointment date as dd-mm-yyyy.');
        }

        $db = db_connect();
        $clinic = $db->table('clinics')
            ->select('id, clinic_name, phone, email, address')
            ->where('id', $scope['clinic_id'])
            ->where('status', 1)
            ->get()
            ->getRowArray();

        if ($clinic === null) {
            throw new InvalidArgumentException('The configured Shopify clinic is unavailable.');
        }

        $doctorsQuery = $db->table('doctors AS doctors')
            ->select('doctors.id, doctors.doctor_code, doctors.first_name, doctors.last_name, doctors.specialization_id, doctor_specializations.specialization_name')
            ->join('doctor_specializations', 'doctor_specializations.id = doctors.specialization_id', 'left')
            ->where('doctors.clinic_id', $scope['clinic_id'])
            ->where('doctors.status', 1)
            ->where('doctors.online_booking', 1);

        if ($doctorId !== null && $doctorId > 0) {
            $doctorsQuery->where('doctors.id', $doctorId);
        }

        $doctorRows = $doctorsQuery
            ->orderBy('doctors.display_order', 'ASC')
            ->orderBy('doctors.first_name', 'ASC')
            ->get()
            ->getResultArray();
        $doctors = [];
        $specializationMap = [];

        foreach ($doctorRows as $doctor) {
            $doctors[] = [
                'id' => (int) $doctor['id'],
                'code' => (string) ($doctor['doctor_code'] ?? ''),
                'name' => trim((string) $doctor['first_name'] . ' ' . (string) $doctor['last_name']),
                'specialization' => (string) ($doctor['specialization_name'] ?? ''),
                'available' => $this->dayStatus((int) $doctor['id'], $date) === 'available',
            ];

            $specializationId = (int) ($doctor['specialization_id'] ?? 0);

            if ($specializationId > 0 && ! isset($specializationMap[$specializationId])) {
                $specializationMap[$specializationId] = [
                    'id' => $specializationId,
                    'name' => (string) ($doctor['specialization_name'] ?? ''),
                ];
            }
        }

        $services = $db->table('admin_services')
            ->select('id, name, description, amount')
            ->groupStart()
                ->where('clinic_id', $scope['clinic_id'])
                ->orWhere('clinic_id', null)
            ->groupEnd()
            ->where('status', 1)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
        $serviceRows = array_map(static fn (array $service): array => [
            'id' => (int) $service['id'],
            'name' => (string) $service['name'],
            'description' => (string) ($service['description'] ?? ''),
        ], $services);
        $slots = [];

        if ($doctorId !== null && $doctorId > 0) {
            $slots = $this->slotsForDoctor($scope['clinic_id'], $scope['patient_id'], $doctorId, $date);
        }

        $dates = [];
        $dateCursor = new DateTimeImmutable(date('Y-m-d'));

        for ($offset = 0; $offset < 31; $offset++) {
            $candidate = $dateCursor->modify('+' . $offset . ' days')->format('Y-m-d');
            $candidateDoctors = $doctorId !== null && $doctorId > 0
                ? [$doctorId]
                : array_map(static fn (array $doctor): int => (int) $doctor['id'], $doctorRows);
            $state = 'unavailable';
            $hasLeave = false;
            $hasFull = false;

            foreach ($candidateDoctors as $candidateDoctorId) {
                if ($this->dayStatus($candidateDoctorId, $candidate) === 'leave') {
                    $hasLeave = true;

                    continue;
                }

                $candidateSlots = $this->slotsForDoctor($scope['clinic_id'], $scope['patient_id'], $candidateDoctorId, $candidate);

                if (array_filter($candidateSlots, static fn (array $slot): bool => $slot['state'] === 'available') !== []) {
                    $state = 'available';

                    break;
                }

                if ($candidateSlots !== []) {
                    $hasFull = true;
                }
            }

            if ($state !== 'available') {
                $state = $hasFull ? 'full' : ($hasLeave ? 'leave' : 'unavailable');
            }

            $dates[] = [
                'date' => $this->formatDisplayDate($candidate),
                'date_iso' => $candidate,
                'state' => $state,
            ];
        }

        return [
            'clinic' => [
                'id' => (int) $clinic['id'],
                'name' => (string) $clinic['clinic_name'],
                'phone' => (string) ($clinic['phone'] ?? ''),
                'email' => (string) ($clinic['email'] ?? ''),
            ],
            'doctors' => $doctors,
            'specializations' => array_values($specializationMap),
            'services' => $serviceRows,
            'dates' => $dates,
            'date' => $this->formatDisplayDate($date),
            'date_iso' => $date,
            'slots' => $slots,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function book(array $handoff, array $input): array
    {
        $scope = $this->scope($handoff);
        $doctorId = (int) ($input['doctor_id'] ?? 0);
        $serviceId = (int) ($input['service_id'] ?? 0);
        $date = $this->parseDisplayDate((string) ($input['appointment_date'] ?? ''));
        $time = $this->normalizeTime((string) ($input['appointment_time'] ?? ''));
        $type = strtolower(trim((string) ($input['appointment_type'] ?? 'in_person')));
        $externalReference = trim((string) ($input['external_reference'] ?? ''));
        $idempotencyKey = trim((string) ($input['idempotency_key'] ?? ''));
        $errors = [];

        if ($doctorId < 1) {
            $errors['doctor_id'] = 'Doctor is required.';
        }

        if ($serviceId < 1) {
            $errors['service_id'] = 'Service is required.';
        }

        if ($date === null || $date < date('Y-m-d')) {
            $errors['appointment_date'] = 'Enter a valid appointment date as dd-mm-yyyy.';
        }

        if ($time === null) {
            $errors['appointment_time'] = 'Enter a valid appointment time as hh:mm AM/PM.';
        }

        if ($date === date('Y-m-d') && $time !== null && $time <= date('H:i:s')) {
            $errors['appointment_time'] = 'The selected appointment time has already passed.';
        }

        if (! in_array($type, ['in_person', 'video', 'phone'], true)) {
            $errors['appointment_type'] = 'Appointment type is invalid.';
        }

        if ($externalReference === '' || mb_strlen($externalReference) > 191) {
            $errors['external_reference'] = 'A valid external reference is required.';
        }

        if ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 191) {
            $errors['idempotency_key'] = 'A valid idempotency key is required.';
        }

        if ($errors !== []) {
            throw new ShopifyAppointmentValidationException($errors);
        }

        $db = db_connect();
        $bookingModel = new ShopifyAppointmentBookingModel();
        $existing = $bookingModel
            ->where('shopify_store_domain', $scope['store_domain'])
            ->groupStart()
                ->where('idempotency_key', $idempotencyKey)
                ->orWhere('external_reference', $externalReference)
            ->groupEnd()
            ->first();

        if ($existing !== null) {
            if ((int) ($existing['patient_id'] ?? 0) !== $scope['patient_id']) {
                throw new ShopifyAppointmentConflictException('This booking reference is already linked to another patient.');
            }

            $appointment = $db->table('appointments')
                ->select('id, appointment_number, status, appointment_date, appointment_time')
                ->where('id', (int) ($existing['appointment_id'] ?? 0))
                ->where('patient_id', $scope['patient_id'])
                ->where('clinic_id', $scope['clinic_id'])
                ->get()
                ->getRowArray();

            if ($appointment !== null) {
                return $this->confirmation($appointment, $externalReference, true);
            }

            throw new ShopifyAppointmentConflictException('The previous booking could not be resolved safely.');
        }

        $doctor = $db->table('doctors')
            ->where('id', $doctorId)
            ->where('clinic_id', $scope['clinic_id'])
            ->where('status', 1)
            ->where('online_booking', 1)
            ->get()
            ->getRowArray();
        $service = $db->table('admin_services')
            ->groupStart()
                ->where('clinic_id', $scope['clinic_id'])
                ->orWhere('clinic_id', null)
            ->groupEnd()
            ->where('id', $serviceId)
            ->where('status', 1)
            ->get()
            ->getRowArray();

        if ($doctor === null) {
            throw new ShopifyAppointmentValidationException(['doctor_id' => 'Doctor is not available for booking.']);
        }

        if ($service === null) {
            throw new ShopifyAppointmentValidationException(['service_id' => 'Service is not available for booking.']);
        }

        $handoffService = $this->handoffService ?? new ShopifyHandoffService();
        $handoffService->consume((string) ($input['handoff_token'] ?? ''));
        $day = (int) date('N', strtotime((string) $date));
        $db->transBegin();

        try {
            $schedule = $this->lockSchedule($db, $doctorId, $day, (string) $time);

            if ($schedule === null || (new DoctorLeaveModel())->isOnLeave($doctorId, (string) $date)) {
                throw new ShopifyAppointmentConflictException('The selected time is unavailable or closed.');
            }

            $conflict = $db->table('appointments')
                ->groupStart()
                    ->where('doctor_id', $doctorId)
                    ->orWhere('patient_id', $scope['patient_id'])
                ->groupEnd()
                ->where('clinic_id', $scope['clinic_id'])
                ->where('appointment_date', $date)
                ->where('appointment_time', $time)
                ->whereIn('status', self::ACTIVE_APPOINTMENT_STATUSES)
                ->countAllResults();

            $capacity = (int) ($schedule['max_patients'] ?? 0);
            $doctorSlotCount = $db->table('appointments')
                ->where('doctor_id', $doctorId)
                ->where('clinic_id', $scope['clinic_id'])
                ->where('appointment_date', $date)
                ->where('appointment_time', $time)
                ->whereIn('status', self::ACTIVE_APPOINTMENT_STATUSES)
                ->countAllResults();

            if ($conflict > 0 || ($capacity > 0 && $doctorSlotCount >= $capacity)) {
                throw new ShopifyAppointmentConflictException('The selected time is already booked.');
            }

            $now = date('Y-m-d H:i:s');
            $inserted = $db->table('appointments')->insert([
                'appointment_number' => 'TMP-' . bin2hex(random_bytes(8)),
                'patient_id' => $scope['patient_id'],
                'doctor_id' => $doctorId,
                'clinic_id' => $scope['clinic_id'],
                'appointment_date' => $date,
                'appointment_time' => $time,
                'duration_minutes' => (int) ($schedule['slot_duration'] ?? 15),
                'appointment_type' => $type,
                'service_name' => (string) $service['name'],
                'status' => 'scheduled',
                'created_by_type' => 'patient',
                'created_by_id' => $scope['patient_id'],
                'booking_source' => 'shopify',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (! $inserted) {
                throw new RuntimeException('Shopify appointment insert failed.');
            }

            $appointmentId = (int) $db->insertID();
            $appointmentNumber = sprintf('APT-%06d', $appointmentId);

            if (! $db->table('appointments')->where('id', $appointmentId)->update([
                'appointment_number' => $appointmentNumber,
                'updated_at' => $now,
            ])) {
                throw new RuntimeException('Shopify appointment number update failed.');
            }

            if (! $db->table('appointment_services')->insert([
                'appointment_id' => $appointmentId,
                'service_id' => $serviceId,
                'service_name' => (string) $service['name'],
                'created_at' => $now,
            ])) {
                throw new RuntimeException('Shopify appointment service link failed.');
            }

            if (! $bookingModel->insert([
                'shopify_store_domain' => $scope['store_domain'],
                'external_reference' => $externalReference,
                'idempotency_key' => $idempotencyKey,
                'patient_id' => $scope['patient_id'],
                'clinic_id' => $scope['clinic_id'],
                'appointment_id' => $appointmentId,
                'status' => 'created',
                'created_at' => $now,
                'updated_at' => $now,
            ])) {
                throw new RuntimeException('Shopify booking reference insert failed.');
            }

            if (! $db->transCommit()) {
                throw new RuntimeException('Shopify appointment transaction failed.');
            }
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        service('audit')->record('shopify.appointment_booked', [
            'appointment_id' => $appointmentId,
            'patient_id' => $scope['patient_id'],
            'doctor_id' => $doctorId,
            'clinic_id' => $scope['clinic_id'],
            'store_domain' => $scope['store_domain'],
        ]);

        return $this->confirmation([
            'id' => $appointmentId,
            'appointment_number' => $appointmentNumber,
            'status' => 'scheduled',
            'appointment_date' => $date,
            'appointment_time' => $time,
        ], $externalReference, false);
    }

    /** @return array<string, mixed> */
    public function cancel(array $handoff, int $appointmentId, string $reason, bool $confirmed, string $handoffToken): array
    {
        $scope = $this->scope($handoff);
        $reason = trim($reason);

        if (! $confirmed) {
            throw new ShopifyAppointmentValidationException(['confirmation' => 'Confirm that you want to cancel this appointment.']);
        }

        if ($reason === '') {
            throw new ShopifyAppointmentValidationException(['reason' => 'A cancellation reason is required.']);
        }

        $db = db_connect();
        $appointment = $this->patientAppointment($db, $scope, $appointmentId);

        if ($appointment === null) {
            throw new ShopifyAppointmentNotFoundException('Appointment not found.');
        }

        if (! in_array((string) $appointment['status'], self::PATIENT_EDITABLE_STATUSES, true)
            || (string) $appointment['appointment_date'] < date('Y-m-d')) {
            throw new ShopifyAppointmentConflictException('This appointment cannot be cancelled.');
        }

        $handoffService = $this->handoffService ?? new ShopifyHandoffService();
        $handoffService->consume($handoffToken);
        $updated = $db->table('appointments')
            ->where('id', $appointmentId)
            ->where('patient_id', $scope['patient_id'])
            ->where('clinic_id', $scope['clinic_id'])
            ->whereIn('status', self::PATIENT_EDITABLE_STATUSES)
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => date('Y-m-d H:i:s'),
                'cancelled_by' => $scope['patient_id'],
                'cancellation_reason' => $reason,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        if (! $updated) {
            throw new RuntimeException('Shopify appointment cancellation failed.');
        }

        service('audit')->record('shopify.appointment_cancelled', [
            'appointment_id' => $appointmentId,
            'patient_id' => $scope['patient_id'],
            'clinic_id' => $scope['clinic_id'],
        ]);

        return ['appointment_id' => $appointmentId, 'status' => 'cancelled'];
    }

    /** @return array<string, mixed> */
    public function reschedule(array $handoff, int $appointmentId, string $displayDate, string $time, bool $confirmed, string $handoffToken): array
    {
        $scope = $this->scope($handoff);
        $date = $this->parseDisplayDate($displayDate);
        $time = $this->normalizeTime($time);

        if (! $confirmed) {
            throw new ShopifyAppointmentValidationException(['confirmation' => 'Confirm that you want to reschedule this appointment.']);
        }

        $errors = [];

        if ($date === null || $date < date('Y-m-d')) {
            $errors['appointment_date'] = 'Enter a valid appointment date as dd-mm-yyyy.';
        }

        if ($time === null) {
            $errors['appointment_time'] = 'Enter a valid appointment time as hh:mm AM/PM.';
        }

        if ($date === date('Y-m-d') && $time !== null && $time <= date('H:i:s')) {
            $errors['appointment_time'] = 'The selected appointment time has already passed.';
        }

        if ($errors !== []) {
            throw new ShopifyAppointmentValidationException($errors);
        }

        $db = db_connect();
        $appointment = $this->patientAppointment($db, $scope, $appointmentId);

        if ($appointment === null) {
            throw new ShopifyAppointmentNotFoundException('Appointment not found.');
        }

        if (! in_array((string) $appointment['status'], self::PATIENT_EDITABLE_STATUSES, true)
            || (string) $appointment['appointment_date'] < date('Y-m-d')) {
            throw new ShopifyAppointmentConflictException('This appointment cannot be rescheduled.');
        }

        $doctorId = (int) $appointment['doctor_id'];
        $day = (int) date('N', strtotime($date));
        $schedule = $this->schedule($db, $doctorId, $day, $time);

        if ($schedule === null || (new DoctorLeaveModel())->isOnLeave($doctorId, $date)) {
            throw new ShopifyAppointmentConflictException('The selected time is unavailable or closed.');
        }

        $conflict = $db->table('appointments')
            ->groupStart()
                ->where('doctor_id', $doctorId)
                ->orWhere('patient_id', $scope['patient_id'])
            ->groupEnd()
            ->where('clinic_id', $scope['clinic_id'])
            ->where('appointment_date', $date)
            ->where('appointment_time', $time)
            ->where('id !=', $appointmentId)
            ->whereIn('status', self::ACTIVE_APPOINTMENT_STATUSES)
            ->countAllResults();

        if ($conflict > 0) {
            throw new ShopifyAppointmentConflictException('The selected time is already booked.');
        }

        $handoffService = $this->handoffService ?? new ShopifyHandoffService();
        $handoffService->consume($handoffToken);
        $updated = $db->table('appointments')
            ->where('id', $appointmentId)
            ->where('patient_id', $scope['patient_id'])
            ->where('clinic_id', $scope['clinic_id'])
            ->whereIn('status', self::PATIENT_EDITABLE_STATUSES)
            ->update([
                'appointment_date' => $date,
                'appointment_time' => $time,
                'duration_minutes' => (int) ($schedule['slot_duration'] ?? 15),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        if (! $updated) {
            throw new RuntimeException('Shopify appointment reschedule failed.');
        }

        service('audit')->record('shopify.appointment_rescheduled', [
            'appointment_id' => $appointmentId,
            'patient_id' => $scope['patient_id'],
            'clinic_id' => $scope['clinic_id'],
        ]);

        return [
            'appointment_id' => $appointmentId,
            'appointment_date' => $this->formatDisplayDate($date),
            'appointment_time' => rootins_format_time($time),
        ];
    }

    /** @return array<string, int|string> */
    private function scope(array $handoff): array
    {
        $configuration = $this->configuration ?? new ShopifyConfiguration();
        $store = strtolower(trim((string) ($handoff['store_domain'] ?? '')));
        $clinicId = $configuration->clinicId();
        $patientId = (int) ($handoff['patient_id'] ?? 0);
        $customerId = trim((string) ($handoff['customer_id'] ?? ''));

        if ($clinicId === null || $store === '' || $store !== strtolower($configuration->storeDomain()) || $patientId < 1 || $customerId === '') {
            throw new InvalidArgumentException('Shopify patient scope is invalid.');
        }

        $link = (new ShopifyPatientIdentityLinkModel())
            ->where('shopify_store_domain', $store)
            ->where('shopify_customer_id', $customerId)
            ->where('patient_id', $patientId)
            ->where('link_status', 'linked')
            ->first();
        $patient = (new PatientModel())
            ->where('id', $patientId)
            ->where('status', 1)
            ->first();

        if ($link === null || $patient === null) {
            throw new InvalidArgumentException('Shopify patient scope is invalid.');
        }

        return [
            'store_domain' => $store,
            'customer_id' => $customerId,
            'patient_id' => $patientId,
            'clinic_id' => $clinicId,
        ];
    }

    /** @return array<int, array<string, string|int>> */
    private function slotsForDoctor(int $clinicId, int $patientId, int $doctorId, string $date): array
    {
        if ($this->dayStatus($doctorId, $date) === 'leave') {
            return [];
        }

        $db = db_connect();
        $day = (int) date('N', strtotime($date));
        $schedules = $db->table('doctor_schedules')
            ->where('doctor_id', $doctorId)
            ->where('day_of_week', $day)
            ->where('status', 1)
            ->orderBy('start_time', 'ASC')
            ->get()
            ->getResultArray();
        $slots = [];

        foreach ($schedules as $schedule) {
            $duration = max(1, (int) ($schedule['slot_duration'] ?? 15));
            $cursor = new DateTimeImmutable($date . ' ' . substr((string) $schedule['start_time'], 0, 5));
            $end = new DateTimeImmutable($date . ' ' . substr((string) $schedule['end_time'], 0, 5));

            while ($cursor < $end && $cursor->modify('+' . $duration . ' minutes') <= $end) {
                $time = $cursor->format('H:i:s');
                $breakStart = (string) ($schedule['break_start_time'] ?? '');
                $breakEnd = (string) ($schedule['break_end_time'] ?? '');
                $inBreak = $breakStart !== '' && $breakEnd !== '' && $time >= $breakStart && $time < $breakEnd;

                if (! $inBreak) {
                    if ($date === date('Y-m-d') && $time <= date('H:i:s')) {
                        $cursor = $cursor->modify('+' . $duration . ' minutes');

                        continue;
                    }

                    $count = $db->table('appointments')
                        ->where('doctor_id', $doctorId)
                        ->where('clinic_id', $clinicId)
                        ->where('appointment_date', $date)
                        ->where('appointment_time', $time)
                        ->whereIn('status', self::ACTIVE_APPOINTMENT_STATUSES)
                        ->countAllResults();
                    $patientConflict = $db->table('appointments')
                        ->where('patient_id', $patientId)
                        ->where('clinic_id', $clinicId)
                        ->where('appointment_date', $date)
                        ->where('appointment_time', $time)
                        ->whereIn('status', self::ACTIVE_APPOINTMENT_STATUSES)
                        ->countAllResults() > 0;
                    $capacity = (int) ($schedule['max_patients'] ?? 0);
                    $state = $patientConflict || ($capacity > 0 && $count >= $capacity) ? 'full' : 'available';
                    $slots[] = ['time' => rootins_format_time($time), 'state' => $state];
                }

                $cursor = $cursor->modify('+' . $duration . ' minutes');
            }
        }

        return $slots;
    }

    private function dayStatus(int $doctorId, string $date): string
    {
        if ((new DoctorLeaveModel())->isOnLeave($doctorId, $date)) {
            return 'leave';
        }

        $day = (int) date('N', strtotime($date));
        $schedule = db_connect()->table('doctor_schedules')
            ->where('doctor_id', $doctorId)
            ->where('day_of_week', $day)
            ->where('status', 1)
            ->countAllResults();

        return $schedule > 0 ? 'available' : 'unavailable';
    }

    private function schedule($db, int $doctorId, int $day, string $time): ?array
    {
        return $db->table('doctor_schedules')
            ->where('doctor_id', $doctorId)
            ->where('day_of_week', $day)
            ->where('status', 1)
            ->where('start_time <=', $time)
            ->where('end_time >', $time)
            ->get()
            ->getRowArray();
    }

    private function lockSchedule($db, int $doctorId, int $day, string $time): ?array
    {
        $schedule = $this->schedule($db, $doctorId, $day, $time);

        if ($schedule === null) {
            return null;
        }

        if (strtolower((string) $db->DBDriver) === 'mysqli') {
            return $db->query('SELECT * FROM doctor_schedules WHERE id = ? FOR UPDATE', [(int) $schedule['id']])->getRowArray();
        }

        return $schedule;
    }

    private function patientAppointment($db, array $scope, int $appointmentId): ?array
    {
        return $db->table('appointments')
            ->where('id', $appointmentId)
            ->where('patient_id', $scope['patient_id'])
            ->where('clinic_id', $scope['clinic_id'])
            ->get()
            ->getRowArray();
    }

    /** @return array<string, mixed> */
    private function confirmation(array $appointment, string $externalReference, bool $duplicate): array
    {
        return [
            'appointment_id' => (int) $appointment['id'],
            'appointment_number' => (string) $appointment['appointment_number'],
            'appointment_date' => $this->formatDisplayDate((string) $appointment['appointment_date']),
            'appointment_time' => rootins_format_time($appointment['appointment_time'] ?? ''),
            'status' => (string) $appointment['status'],
            'external_reference' => $externalReference,
            'idempotent_replay' => $duplicate,
        ];
    }

    private function parseDisplayDate(string $value): ?string
    {
        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat('!' . self::DATE_FORMAT, $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format(self::DATE_FORMAT) !== $value) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private function formatDisplayDate(string $isoDate): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $isoDate);

        return $date === false ? '' : $date->format(self::DATE_FORMAT);
    }

    private function normalizeTime(string $value): ?string
    {
        return rootins_parse_time($value);
    }
}

final class ShopifyAppointmentValidationException extends InvalidArgumentException
{
    /** @param array<string, string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Shopify appointment validation failed.');
    }
}

final class ShopifyAppointmentConflictException extends RuntimeException
{
}

final class ShopifyAppointmentNotFoundException extends RuntimeException
{
}
