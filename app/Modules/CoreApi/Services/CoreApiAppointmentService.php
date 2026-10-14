<?php

namespace App\Modules\CoreApi\Services;

use Admin\Models\Doctor\DoctorLeaveModel;
use DateTimeImmutable;
use DateTimeZone;

final class CoreApiAppointmentService
{
    /** @return array<string, mixed> */
    public function availability(array $credential, array $input): array
    {
        $timezone = $this->timezone();
        $dates = $this->requestedDates($input, $timezone);
        $db = db_connect();
        $clinicId = $this->optionalClinic($input);
        $doctorReference = trim((string) ($input['doctor_reference'] ?? $input['doctor'] ?? ''));
        $serviceReference = trim((string) ($input['service_reference'] ?? $input['service'] ?? ''));
        $specializationReference = trim((string) ($input['specialization_reference'] ?? $input['specialization'] ?? ''));
        $search = trim((string) ($input['search'] ?? ''));

        $service = $this->resolveService($serviceReference, $clinicId);
        $specializationId = $this->resolveSpecialization($specializationReference);
        $this->assertDoctorFilter($doctorReference, $clinicId);

        $query = $db->table('doctors')->select('doctors.id, doctors.clinic_id, doctors.doctor_code, doctors.first_name, doctors.last_name, doctors.qualification, doctors.experience, doctors.profile_photo')
            ->where('doctors.status', 1)->where('doctors.online_booking', 1);
        if ($clinicId !== null) $query->where('doctors.clinic_id', $clinicId);
        if ($doctorReference !== '') $query->where('doctors.doctor_code', $doctorReference);
        if ($specializationId !== null) $query->join('doctor_specialization_map filter_specialization', 'filter_specialization.doctor_id = doctors.id', 'inner')->where('filter_specialization.specialization_id', $specializationId);
        if ($search !== '') $query->groupStart()->like('doctors.first_name', $search)->orLike('doctors.last_name', $search)->orLike('doctors.doctor_code', $search)->orLike('doctors.qualification', $search)->groupEnd();

        $allDoctors = $query->orderBy('doctors.display_order', 'ASC')->orderBy('doctors.first_name', 'ASC')->get()->getResultArray();
        $pageSize = $this->pageSize($input['page_size'] ?? 20);
        $page = max(1, (int) ($input['page'] ?? 1));
        $total = count($allDoctors);
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $doctors = [];
        foreach (array_slice($allDoctors, ($page - 1) * $pageSize, $pageSize) as $doctor) {
            $doctorDates = [];
            foreach ($dates as $date) $doctorDates[] = $this->dateAvailability((int) $doctor['clinic_id'], (int) $doctor['id'], $date, $service, $timezone);
            $first = $doctorDates[0];
            $specializations = $this->specializations((int) $doctor['id']);
            $servicesForDoctor = $this->services((int) $doctor['clinic_id'], $serviceReference === '' ? null : $service);
            $doctors[] = [
                'reference' => (string) $doctor['doctor_code'],
                'name' => trim((string) $doctor['first_name'] . ' ' . (string) ($doctor['last_name'] ?? '')),
                'profile_image' => $this->publicImage($doctor['profile_photo'] ?? null),
                'qualification' => $doctor['qualification'] ?: null,
                'specializations' => $specializations,
                'experience' => (int) ($doctor['experience'] ?? 0),
                'consultation_fee' => $this->fee((int) $doctor['id'], $dates[0]),
                'services' => $servicesForDoctor,
                'availability_status' => $this->overallStatus($doctorDates),
                'dates' => $doctorDates,
                // Kept for clients of the original single-date contract.
                'slots' => $first['slots'],
                'state' => $first['status'],
            ];
        }

        return ['date' => $this->formatDate($dates[0]), 'date_iso' => $dates[0], 'dates' => array_map(fn (string $date): string => $this->formatDate($date), $dates), 'doctors' => $doctors, 'services' => $this->services(null, $service), 'pagination' => ['page' => $page, 'pages' => $pages, 'page_size' => $pageSize, 'total' => $total]];
    }

    /** @return array<string, mixed> */
    public function book(array $credential, array $input): array
    {
        $doctorReference = trim((string) ($input['doctor_reference'] ?? ''));
        $serviceReference = trim((string) ($input['service_reference'] ?? ''));
        $patientReference = trim((string) ($input['patient_reference'] ?? ''));
        $date = $this->parseDate((string) ($input['appointment_date'] ?? ''));
        $time = trim((string) ($input['appointment_time'] ?? ''));
        $errors = [];

        if ($doctorReference === '') {
            $errors['doctor_reference'] = 'Doctor is required.';
        }
        if (! preg_match('/^service-(\d+)$/', $serviceReference, $serviceMatch)) {
            $errors['service_reference'] = 'Service is required.';
        }
        if ($patientReference === '') {
            $errors['patient_reference'] = 'Patient reference is required.';
        }
        if ($date === null || $date < date('Y-m-d')) {
            $errors['appointment_date'] = 'Enter a valid appointment date as dd-mm-yyyy.';
        }
        if (! preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
            $errors['appointment_time'] = 'Enter a valid appointment time as HH:mm.';
        }
        if ($errors !== []) {
            throw new CoreApiException(422, 'VALIDATION_FAILED', 'Review the appointment fields.', $errors);
        }

        $resolver = new CoreApiClinicResolver();
        $clinicId = $resolver->patientClinic($input);
        $db = db_connect();
        $patient = $db->table('patients')->select('id')->where('clinic_id', $clinicId)->where('registration_number', $patientReference)->where('status', 1)->get()->getRowArray();
        $doctor = $db->table('doctors')->where('clinic_id', $clinicId)->where('doctor_code', $doctorReference)->where('status', 1)->where('online_booking', 1)->get()->getRowArray();
        $service = $db->table('admin_services')->groupStart()->where('clinic_id', $clinicId)->orWhere('clinic_id', null)->groupEnd()->where('id', (int) $serviceMatch[1])->where('status', 1)->get()->getRowArray();

        if ($doctor === null) {
            throw new CoreApiException(422, 'DOCTOR_UNAVAILABLE', 'The selected doctor is unavailable.', ['doctor_reference' => 'The selected doctor is unavailable.']);
        }
        if ($patient === null) {
            throw new CoreApiException(404, 'PATIENT_NOT_FOUND', 'The patient reference was not found.', ['patient_reference' => 'The patient reference was not found.']);
        }
        if ($service === null) {
            throw new CoreApiException(422, 'SERVICE_UNAVAILABLE', 'The selected service is unavailable.', ['service_reference' => 'The selected service is unavailable.']);
        }

        $day = (int) date('N', strtotime($date));
        $schedule = $db->table('doctor_schedules')->where('doctor_id', (int) $doctor['id'])->where('day_of_week', $day)->where('status', 1)->where('start_time <=', $time)->where('end_time >', $time)->get()->getRowArray();

        if ($schedule === null || (new DoctorLeaveModel())->isOnLeave((int) $doctor['id'], $date)) {
            throw new CoreApiException(409, 'SLOT_UNAVAILABLE', 'The selected appointment slot is unavailable.');
        }

        $db->transBegin();
        try {
            $conflict = $db->query('SELECT id FROM appointments WHERE clinic_id = ? AND doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status NOT IN (\'cancelled\', \'no_show\') FOR UPDATE', [$clinicId, (int) $doctor['id'], $date, $time])->getRowArray();

            if ($conflict !== null) {
                throw new CoreApiException(409, 'SLOT_UNAVAILABLE', 'The selected appointment slot is already booked.');
            }

            $now = date('Y-m-d H:i:s');
            $db->table('appointments')->insert([
                'appointment_number' => 'TMP-' . bin2hex(random_bytes(8)),
                'patient_id' => (int) $patient['id'],
                'doctor_id' => (int) $doctor['id'],
                'clinic_id' => $clinicId,
                'appointment_date' => $date,
                'appointment_time' => $time,
                'duration_minutes' => (int) ($schedule['slot_duration'] ?? 15),
                'appointment_type' => strtolower(trim((string) ($input['appointment_type'] ?? 'in_person'))),
                'service_name' => (string) $service['name'],
                'status' => 'scheduled',
                'created_by_type' => 'api',
                'booking_source' => 'core_api',
                'reason_for_visit' => trim((string) ($input['reason_for_visit'] ?? '')) ?: null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $id = (int) $db->insertID();

            if ($id < 1 || ! $db->table('appointments')->where('id', $id)->update(['appointment_number' => sprintf('APT-%06d', $id)])) {
                throw new \RuntimeException('Appointment creation failed.');
            }
            if (! $db->transCommit()) {
                throw new \RuntimeException('Appointment transaction failed.');
            }
        } catch (CoreApiException $exception) {
            $db->transRollback();
            throw $exception;
        } catch (\Throwable $exception) {
            $db->transRollback();
            throw $exception;
        }

        service('audit')->record('core_api.appointment_booked', ['clinic_id' => $clinicId, 'appointment_id' => $id]);

        return ['appointment_reference' => sprintf('APT-%06d', $id), 'date' => (new DateTimeImmutable($date))->format('d-m-Y'), 'time' => substr($time, 0, 5), 'status' => 'scheduled'];
    }

    /** @return array<string, mixed> */
    private function dateAvailability(int $clinicId, int $doctorId, string $date, ?array $service, DateTimeZone $timezone): array
    {
        $db = db_connect();
        $now = new DateTimeImmutable('now', $timezone);
        $today = $now->format('Y-m-d');
        $day = (int) (new DateTimeImmutable($date, $timezone))->format('N');
        $schedules = $db->table('doctor_schedules')->where('doctor_id', $doctorId)->where('day_of_week', $day)->where('status', 1)->orderBy('start_time', 'ASC')->get()->getResultArray();
        $leave = (new DoctorLeaveModel())->where('doctor_id', $doctorId)->where('status', 1)->where('start_date <=', $date)->where('end_date >=', $date)->orderBy('start_date', 'ASC')->first();
        $leaveStart = trim((string) ($leave['start_time'] ?? ''));
        $leaveEnd = trim((string) ($leave['end_time'] ?? ''));
        $fullDayLeave = $leave !== null && ($leaveStart === '' || $leaveEnd === '');
        $slots = [];
        $durations = [];
        foreach ($schedules as $schedule) {
            $duration = max(1, (int) ($schedule['slot_duration'] ?? 15));
            $durations[] = $duration;
            $start = new DateTimeImmutable($date . ' ' . substr((string) $schedule['start_time'], 0, 8), $timezone);
            $end = new DateTimeImmutable($date . ' ' . substr((string) $schedule['end_time'], 0, 8), $timezone);
            for ($cursor = $start; $cursor < $end && $cursor->modify('+' . $duration . ' minutes') <= $end; $cursor = $cursor->modify('+' . $duration . ' minutes')) {
                $slotStart = $cursor->format('H:i:s');
                $slotEnd = $cursor->modify('+' . $duration . ' minutes')->format('H:i:s');
                $breakStart = trim((string) ($schedule['break_start_time'] ?? ''));
                $breakEnd = trim((string) ($schedule['break_end_time'] ?? ''));
                $inBreak = $breakStart !== '' && $breakEnd !== '' && $slotStart < $breakEnd && $slotEnd > $breakStart;
                $slotLeave = $leave !== null && ! $fullDayLeave && $slotStart < $leaveEnd && $slotEnd > $leaveStart;
                $count = (int) $db->table('appointments')->where('clinic_id', $clinicId)->where('doctor_id', $doctorId)->where('appointment_date', $date)->where('appointment_time', $slotStart)->whereNotIn('status', ['cancelled', 'no_show'])->countAllResults();
                $capacity = (int) ($schedule['max_patients'] ?? 0);
                $remaining = $capacity > 0 ? max(0, $capacity - $count) : null;
                $past = $date < $today || ($date === $today && $slotStart <= $now->format('H:i:s'));
                $status = $past ? 'past' : ($fullDayLeave || $slotLeave ? 'leave' : ($inBreak ? 'unavailable' : (($count > 0 || ($capacity > 0 && $count >= $capacity)) ? 'full' : 'available')));
                $slots[] = ['slot_duration' => $duration, 'start_time' => substr($slotStart, 0, 5), 'end_time' => substr($slotEnd, 0, 5), 'time' => substr($slotStart, 0, 5), 'slot_available' => $status === 'available', 'remaining_capacity' => $remaining, 'booking_status' => $status, 'state' => $status];
            }
        }
        $status = $fullDayLeave ? 'leave' : ($schedules === [] ? 'unavailable' : ($slots !== [] && count(array_filter($slots, static fn (array $slot): bool => $slot['booking_status'] === 'available')) > 0 ? 'available' : (count(array_filter($slots, static fn (array $slot): bool => $slot['booking_status'] === 'full')) > 0 ? 'full' : 'unavailable')));
        return ['date' => $this->formatDate($date), 'date_iso' => $date, 'working_day' => $schedules !== [], 'working_day_status' => $schedules !== [] ? 'working' : 'non_working', 'leave' => $leave !== null, 'leave_status' => $leave === null ? 'none' : ($fullDayLeave ? 'full_day' : 'partial'), 'leave_message' => $leave === null ? null : 'Doctor is on leave for the selected date.', 'slot_duration' => $durations === [] ? null : min($durations), 'slots' => $slots, 'status' => $status];
    }

    private function overallStatus(array $dates): string
    {
        $statuses = array_column($dates, 'status');
        foreach (['available', 'full', 'leave', 'unavailable'] as $status) if (in_array($status, $statuses, true)) return $status;
        return 'unavailable';
    }

    /** @return list<array<string, mixed>> */
    private function specializations(int $doctorId): array
    {
        return array_map(static fn (array $row): array => ['reference' => 'specialization-' . (int) $row['id'], 'name' => (string) $row['specialization_name']], db_connect()->table('doctor_specializations')->select('doctor_specializations.id, doctor_specializations.specialization_name')->join('doctor_specialization_map', 'doctor_specialization_map.specialization_id = doctor_specializations.id')->where('doctor_specialization_map.doctor_id', $doctorId)->where('doctor_specializations.status', 1)->orderBy('doctor_specializations.specialization_name', 'ASC')->get()->getResultArray());
    }

    /** @return list<array<string, mixed>> */
    private function services(?int $clinicId, ?array $selected = null): array
    {
        $query = db_connect()->table('admin_services')->select('id, name, description')->where('status', 1);
        if ($clinicId !== null) $query->groupStart()->where('clinic_id', $clinicId)->orWhere('clinic_id', null)->groupEnd();
        if ($selected !== null) $query->where('id', (int) $selected['id']);
        return array_map(static fn (array $row): array => ['reference' => 'service-' . (int) $row['id'], 'name' => (string) $row['name'], 'description' => (string) ($row['description'] ?? '')], $query->orderBy('name', 'ASC')->get()->getResultArray());
    }

    private function resolveService(string $reference, ?int $clinicId): ?array
    {
        if ($reference === '') return null;
        if (! preg_match('/^service-(\d+)$/', $reference, $match)) throw new CoreApiException(422, 'SERVICE_NOT_FOUND', 'The requested service could not be resolved safely.', ['service' => 'Use a valid service reference.']);
        $services = $this->services($clinicId);
        foreach ($services as $service) if ($service['reference'] === $reference) return ['id' => (int) substr($reference, 8), ...$service];
        throw new CoreApiException(422, 'SERVICE_NOT_FOUND', 'The requested service could not be resolved safely.', ['service' => 'The requested service is unavailable.']);
    }

    private function resolveSpecialization(string $reference): ?int
    {
        if ($reference === '') return null;
        if (! preg_match('/^specialization-(\d+)$/', $reference, $match)) throw new CoreApiException(422, 'SPECIALIZATION_NOT_FOUND', 'The requested specialization could not be resolved safely.');
        if (db_connect()->table('doctor_specializations')->where('id', (int) $match[1])->where('status', 1)->countAllResults() < 1) throw new CoreApiException(422, 'SPECIALIZATION_NOT_FOUND', 'The requested specialization could not be resolved safely.');
        return (int) $match[1];
    }

    private function assertDoctorFilter(string $reference, ?int $clinicId): void
    {
        if ($reference === '') return;
        $query = db_connect()->table('doctors')->where('doctor_code', $reference)->where('status', 1)->where('online_booking', 1);
        if ($clinicId !== null) $query->where('clinic_id', $clinicId);
        if ($query->countAllResults() < 1) throw new CoreApiException(422, 'DOCTOR_NOT_FOUND', 'The requested doctor could not be resolved safely.', ['doctor' => 'The requested doctor is unavailable.']);
    }

    private function optionalClinic(array $input): ?int
    {
        $value = trim((string) ($input['clinic_id'] ?? ''));
        if ($value === '') return null;
        $id = ctype_digit($value) ? (int) $value : 0;
        if ($id < 1 || db_connect()->table('clinics')->where('id', $id)->where('status', 1)->countAllResults() < 1) throw new CoreApiException(422, 'CLINIC_NOT_FOUND', 'The requested clinic could not be resolved safely.');
        return $id;
    }

    private function fee(int $doctorId, string $date): ?float
    {
        $row = db_connect()->table('doctor_consultation_fees')->where('doctor_id', $doctorId)->where('status', 1)->where('effective_from <=', $date)->groupStart()->where('effective_to', null)->orWhere('effective_to >=', $date)->groupEnd()->orderBy('effective_from', 'DESC')->get()->getRowArray();
        return $row === null ? null : (float) $row['fee'];
    }

    private function publicImage(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        if (preg_match('#^https?://#i', $value) === 1) return $value;
        $publicUrl = trim((string) env('R2_PUBLIC_URL'));
        return $publicUrl === '' ? null : rtrim($publicUrl, '/') . '/' . ltrim($value, '/');
    }

    /** @return list<string> */
    private function requestedDates(array $input, DateTimeZone $timezone): array
    {
        $raw = $input['dates'] ?? ($input['date'] ?? '');
        $values = is_array($raw) ? $raw : (preg_split('/\s*,\s*/', trim((string) $raw)) ?: []);
        if ($values === [] || $values === ['']) $values = [(new DateTimeImmutable('now', $timezone))->format('d-m-Y')];
        $dates = [];
        foreach ($values as $value) { $date = $this->parseDate((string) $value, $timezone); if ($date === null) throw new CoreApiException(422, 'VALIDATION_FAILED', 'Review the availability fields.', ['date' => 'Enter valid dates as dd-mm-yyyy.']); $dates[$date] = $date; }
        return array_slice(array_values($dates), 0, 31);
    }

    private function pageSize(mixed $value): int
    {
        $size = (int) $value;
        if ($size < 1 || $size > 100) throw new CoreApiException(422, 'VALIDATION_FAILED', 'Review the availability fields.', ['page_size' => 'Page size must be between 1 and 100.']);
        return $size;
    }

    private function timezone(): DateTimeZone { return new DateTimeZone((string) (config('App')->appTimezone ?: date_default_timezone_get())); }
    private function formatDate(string $date): string { return (new DateTimeImmutable($date, $this->timezone()))->format('d-m-Y'); }

    private function parseDate(string $value, ?DateTimeZone $timezone = null): ?string
    {
        $date = DateTimeImmutable::createFromFormat('!d-m-Y', trim($value), $timezone ?? $this->timezone());
        $errors = DateTimeImmutable::getLastErrors();

        return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) && $date->format('d-m-Y') === trim($value) ? $date->format('Y-m-d') : null;
    }
}
