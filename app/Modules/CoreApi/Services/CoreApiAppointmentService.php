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

        $query = $db->table('doctors')->select('doctors.id, doctors.clinic_id, doctors.doctor_code, doctors.first_name, doctors.last_name, doctors.qualification, doctors.experience, doctors.profile_photo, clinics.clinic_code, clinics.clinic_name, clinics.address AS clinic_address, clinics.pincode AS clinic_pincode, cities.name AS clinic_city, states.name AS clinic_state, countries.name AS clinic_country')
            ->join('clinics', 'clinics.id = doctors.clinic_id AND clinics.status = 1')
            ->join('cities', 'cities.id = clinics.city_id', 'left')
            ->join('states', 'states.id = clinics.state_id', 'left')
            ->join('countries', 'countries.id = clinics.country_id', 'left')
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
            foreach ($dates as $date) $doctorDates[] = $this->dateAvailability((int) $doctor['clinic_id'], (int) $doctor['id'], $date, $service, $timezone, (string) $doctor['doctor_code']);
            $first = $doctorDates[0];
            $specializations = $this->specializations((int) $doctor['id']);
            $servicesForDoctor = $this->services((int) $doctor['clinic_id'], $serviceReference === '' ? null : $service);
            $modes = $this->consultationModes((int) $doctor['id']);
            $clinic = $this->clinicDetails($doctor);
            $doctors[] = [
                'doctor_reference' => (string) $doctor['doctor_code'],
                'reference' => (string) $doctor['doctor_code'],
                'name' => trim((string) $doctor['first_name'] . ' ' . (string) ($doctor['last_name'] ?? '')),
                'professional_code' => (string) $doctor['doctor_code'],
                'profile_image_url' => $this->publicImage($doctor['profile_photo'] ?? null),
                'profile_image' => $this->publicImage($doctor['profile_photo'] ?? null),
                'qualifications' => $doctor['qualification'] ? [(string) $doctor['qualification']] : [],
                'qualification' => $doctor['qualification'] ?: null,
                'specializations' => $specializations,
                'experience' => (int) ($doctor['experience'] ?? 0),
                'consultation_fee' => number_format($this->fee((int) $doctor['id'], $dates[0]) ?? 0, 2, '.', ''),
                'services' => $servicesForDoctor,
                'clinics' => [$clinic],
                'consultation_mode' => $this->consultationModeLabel($modes),
                'consultation_modes' => $modes,
                'timezone' => $timezone->getName(),
                'availability_status' => $this->overallStatus($doctorDates),
                'dates' => $doctorDates,
                // Kept for clients of the original single-date contract.
                'slots' => $first['slots'],
                'state' => $first['status'],
            ];
        }

        return ['requested_date_range' => ['from' => $dates[0], 'to' => $dates[count($dates) - 1], 'from_display' => $this->formatDate($dates[0]), 'to_display' => $this->formatDate($dates[count($dates) - 1])], 'date' => $this->formatDate($dates[0]), 'date_iso' => $dates[0], 'dates' => array_map(fn (string $date): string => $this->formatDate($date), $dates), 'doctors' => $doctors, 'services' => $this->services(null, $service), 'pagination' => ['page' => $page, 'pages' => $pages, 'page_size' => $pageSize, 'total' => $total]];
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
    private function dateAvailability(int $clinicId, int $doctorId, string $date, ?array $service, DateTimeZone $timezone, string $doctorReference): array
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
        $breaks = [];
        $conflicts = [];
        $durations = [];
        foreach ($schedules as $schedule) {
            $duration = max(1, (int) ($schedule['slot_duration'] ?? 15));
            $durations[] = $duration;
            $start = new DateTimeImmutable($date . ' ' . substr((string) $schedule['start_time'], 0, 8), $timezone);
            $end = new DateTimeImmutable($date . ' ' . substr((string) $schedule['end_time'], 0, 8), $timezone);
            $breakStart = trim((string) ($schedule['break_start_time'] ?? ''));
            $breakEnd = trim((string) ($schedule['break_end_time'] ?? ''));
            if ($breakStart !== '' && $breakEnd !== '') $breaks[] = ['start_time' => $this->displayTime($breakStart, $timezone), 'end_time' => $this->displayTime($breakEnd, $timezone)];
            for ($cursor = $start; $cursor < $end && $cursor->modify('+' . $duration . ' minutes') <= $end; $cursor = $cursor->modify('+' . $duration . ' minutes')) {
                $slotStart = $cursor->format('H:i:s');
                $slotEnd = $cursor->modify('+' . $duration . ' minutes')->format('H:i:s');
                $inBreak = $breakStart !== '' && $breakEnd !== '' && $slotStart < $breakEnd && $slotEnd > $breakStart;
                $slotLeave = $leave !== null && ! $fullDayLeave && $slotStart < $leaveEnd && $slotEnd > $leaveStart;
                $count = (int) $db->table('appointments')->where('clinic_id', $clinicId)->where('doctor_id', $doctorId)->where('appointment_date', $date)->where('appointment_time', $slotStart)->whereNotIn('status', ['cancelled', 'no_show'])->countAllResults();
                $capacity = (int) ($schedule['max_patients'] ?? 0);
                $remaining = $capacity > 0 ? max(0, $capacity - $count) : null;
                $past = $date < $today || ($date === $today && $slotStart <= $now->format('H:i:s'));
                if ($count > 0) $conflicts[] = ['start_time' => $this->displayTime($slotStart, $timezone), 'end_time' => $this->displayTime($slotEnd, $timezone), 'count' => $count];
                $status = $past ? 'past' : ($fullDayLeave || $slotLeave ? 'leave' : ($inBreak ? 'unavailable' : ($capacity > 0 && $count >= $capacity ? 'full' : ($count > 0 ? 'booked' : 'available'))));
                $slots[] = ['slot_reference' => $this->slotReference($doctorReference, $date, $slotStart, $slotEnd), 'slot_duration' => $duration, 'start_time' => $this->displayTime($slotStart, $timezone), 'end_time' => $this->displayTime($slotEnd, $timezone), 'time' => $this->displayTime($slotStart, $timezone), 'status' => $status, 'available' => $status === 'available', 'slot_available' => $status === 'available', 'capacity' => $capacity > 0 ? $capacity : null, 'booked_count' => $count, 'available_capacity' => $remaining, 'remaining_capacity' => $remaining, 'booking_status' => $status, 'state' => $status];
            }
        }
        $status = $fullDayLeave ? 'leave' : ($schedules === [] ? 'unavailable' : ($slots !== [] && count(array_filter($slots, static fn (array $slot): bool => $slot['booking_status'] === 'available')) > 0 ? 'available' : (count(array_filter($slots, static fn (array $slot): bool => $slot['booking_status'] === 'full')) > 0 ? 'full' : 'unavailable')));
        return ['date' => $this->formatDate($date), 'date_iso' => $date, 'timezone' => $timezone->getName(), 'working_day' => $schedules !== [], 'working_day_status' => $schedules !== [] ? 'working' : 'non_working', 'day_status' => $schedules !== [] ? 'working' : 'unavailable', 'leave' => $leave !== null, 'leave_status' => $leave === null ? 'none' : ($fullDayLeave ? 'full_day' : 'partial'), 'leave_message' => $leave === null ? null : 'Doctor is on leave for the selected date.', 'break_periods' => $breaks, 'existing_appointment_conflicts' => $conflicts, 'slot_duration' => $durations === [] ? null : min($durations), 'slots' => $slots, 'status' => $status];
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

    /** @return list<string> */
    private function consultationModes(int $doctorId): array
    {
        $rows = db_connect()->table('doctor_consultation_fees')->select('consultation_mode')->where('doctor_id', $doctorId)->where('status', 1)->get()->getResultArray();
        $modes = [];
        foreach ($rows as $row) {
            $mode = strtolower(trim((string) ($row['consultation_mode'] ?? '')));
            $mode = $mode === 'offline' ? 'in-person' : ($mode === 'online' ? 'video' : 'home-visit');
            if ($mode !== '' && ! in_array($mode, $modes, true)) $modes[] = $mode;
        }
        sort($modes);
        return $modes;
    }

    private function consultationModeLabel(array $modes): string
    {
        $hasInPerson = in_array('in-person', $modes, true);
        $hasVideo = in_array('video', $modes, true);
        return $hasInPerson && $hasVideo ? 'both' : ($hasVideo ? 'video' : 'in-person');
    }

    /** @param array<string, mixed> $doctor */
    private function clinicDetails(array $doctor): array
    {
        return [
            'clinic_reference' => (string) ($doctor['clinic_code'] ?? ''),
            'name' => (string) ($doctor['clinic_name'] ?? ''),
            'address' => $doctor['clinic_address'] ?: null,
            'city' => $doctor['clinic_city'] ?: null,
            'state' => $doctor['clinic_state'] ?: null,
            'country' => $doctor['clinic_country'] ?: null,
            'pincode' => $doctor['clinic_pincode'] ?: null,
        ];
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
        if (preg_match('#^https://#i', $value) === 1) return $value;
        if (preg_match('#^(?:https?://|javascript:|data:|//|[\\x00-\\x1F])#i', $value) === 1 || str_contains($value, '..')) return null;
        $publicUrl = trim((string) env('R2_PUBLIC_URL'));
        return $publicUrl === '' ? null : rtrim($publicUrl, '/') . '/' . ltrim($value, '/');
    }

    /** @return list<string> */
    private function requestedDates(array $input, DateTimeZone $timezone): array
    {
        $from = $input['from_date'] ?? ($input['start_date'] ?? null);
        $to = $input['to_date'] ?? ($input['end_date'] ?? null);
        if (trim((string) $from) !== '' || trim((string) $to) !== '') {
            $start = $this->parseDate((string) $from, $timezone);
            $end = $this->parseDate((string) ($to ?: $from), $timezone);
            if ($start === null || $end === null || $end < $start) throw new CoreApiException(422, 'VALIDATION_FAILED', 'Review the availability fields.', ['date' => 'Enter a valid date range as dd-mm-yyyy.']);
            $dates = [];
            $cursor = new DateTimeImmutable($start, $timezone);
            $last = new DateTimeImmutable($end, $timezone);
            while ($cursor <= $last && count($dates) < 31) { $dates[] = $cursor->format('Y-m-d'); $cursor = $cursor->modify('+1 day'); }
            return $dates;
        }
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

    private function displayTime(string $time, DateTimeZone $timezone): string
    {
        return (new DateTimeImmutable('2000-01-01 ' . substr($time, 0, 8), $timezone))->format('h:i A');
    }

    private function slotReference(string $doctorReference, string $date, string $start, string $end): string
    {
        return 'slot-' . substr(hash('sha256', $doctorReference . '|' . $date . '|' . $start . '|' . $end), 0, 32);
    }

    private function parseDate(string $value, ?DateTimeZone $timezone = null): ?string
    {
        $date = DateTimeImmutable::createFromFormat('!d-m-Y', trim($value), $timezone ?? $this->timezone());
        $errors = DateTimeImmutable::getLastErrors();

        return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) && $date->format('d-m-Y') === trim($value) ? $date->format('Y-m-d') : null;
    }
}
