<?php

namespace Admin\Controllers\Appointment;

use Admin\Models\AppointmentModel;
use Admin\Models\Clinic\ClinicModel;
use Admin\Models\Doctor\DoctorModel;
use Admin\Models\PatientModel;
use App\Controllers\BaseAdminController;
use App\Services\AppointmentConsultationEligibility;

class Appointment extends BaseAdminController
{
    protected AppointmentModel $appointment;

    protected PatientModel $patient;

    protected DoctorModel $doctor;

    protected ClinicModel $clinic;

    public function __construct()
    {
        $this->appointment = new AppointmentModel();
        $this->patient = new PatientModel();
        $this->doctor = new DoctorModel();
        $this->clinic = new ClinicModel();
    }

    public function index()
    {
        $search = trim((string) $this->request->getGet('search'));
        $status = trim((string) $this->request->getGet('status'));
        $appointmentType = trim((string) $this->request->getGet('appointment_type'));
        $clinicId = (int) $this->request->getGet('clinic_id');
        $doctorId = (int) $this->request->getGet('doctor_id');
        $dateFromInput = trim((string) $this->request->getGet('date_from'));
        $dateToInput = trim((string) $this->request->getGet('date_to'));
        $dateFrom = rootins_parse_date($dateFromInput) ?? '';
        $dateTo = rootins_parse_date($dateToInput) ?? '';
        $pageSize = (int) $this->request->getGet('page_size');
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $query = $this->appointment->select('
                appointments.*,
                patients.registration_number,
                patients.first_name AS patient_first_name,
                patients.middle_name AS patient_middle_name,
                patients.last_name AS patient_last_name,
                patients.mobile_number AS patient_mobile,
                patients.email AS patient_email,
                patients.source_type AS patient_source_type,
                doctors.first_name AS doctor_first_name,
                doctors.last_name AS doctor_last_name,
                doctors.doctor_code,
                clinics.clinic_name
            ')->join('patients', 'patients.id = appointments.patient_id')->join('doctors', 'doctors.id = appointments.doctor_id')->join('clinics', 'clinics.id = appointments.clinic_id');

        if ($search !== '') {
            $query->groupStart()->like('appointments.appointment_number', $search)->orLike('patients.registration_number', $search)->orLike('patients.first_name', $search)->orLike('patients.last_name', $search)->orLike('patients.mobile_number', $search)->orLike('patients.mobile_normalized', $search)->orLike('patients.email', $search)->orLike('doctors.first_name', $search)->orLike('doctors.last_name', $search)->orLike('clinics.clinic_name', $search)->groupEnd();
        }

        if (in_array($status, ['scheduled', 'confirmed', 'checked_in', 'in_queue', 'completed', 'cancelled', 'no_show'], true)) {
            $query->where('appointments.status', $status);
        }

        if (in_array($appointmentType, ['in_person', 'video', 'phone'], true)) {
            $query->where('appointments.appointment_type', $appointmentType);
        }

        if ($clinicId > 0) {
            $query->where('appointments.clinic_id', $clinicId);
        }

        if ($doctorId > 0) {
            $query->where('appointments.doctor_id', $doctorId);
        }

        if ($dateFrom !== '') {
            $query->where('appointments.appointment_date >=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->where('appointments.appointment_date <=', $dateTo);
        }
        $appointments = $query->orderBy('appointments.appointment_date', 'DESC')->orderBy('appointments.appointment_time', 'DESC')->paginate($pageSize, 'appointments');
        $eligibility = new AppointmentConsultationEligibility();
        $appointments = array_map(static function (array $appointment) use ($eligibility): array {
            $appointment['consultation_date_today'] = $eligibility->isToday($appointment);
            $appointment['consultation_date_passed'] = $eligibility->isPast($appointment);
            $appointment['consultation_unavailable_reason'] = $eligibility->unavailableReason($appointment);
            $appointment['consultation_eligible'] = $eligibility->isConsultable($appointment)
                && in_array((string) ($appointment['status'] ?? ''), ['scheduled', 'confirmed', 'checked_in', 'in_queue'], true);

            return $appointment;
        }, $appointments);

        return view(ADMIN_VIEW . 'pages/appointment/index', ['appointments' => $appointments, 'pager' => $this->appointment->pager, 'filters' => ['search' => $search, 'status' => $status, 'appointment_type' => $appointmentType, 'clinic_id' => $clinicId > 0 ? $clinicId : '', 'doctor_id' => $doctorId > 0 ? $doctorId : '', 'date_from' => rootins_format_date($dateFrom), 'date_to' => rootins_format_date($dateTo), 'page_size' => $pageSize], 'canCreate' => $this->hasPermission('appointment.create'), 'canEdit' => $this->hasPermission('appointment.edit'), 'canDelete' => $this->hasPermission('appointment.delete'), 'canConsult' => $this->hasPermission('consultation.view') && $this->hasPermission('consultation.create'), 'canBilling' => $this->hasPermission('billing.view'), 'patients' => $this->patient->select('id, registration_number, first_name, middle_name, last_name')->where('status', 1)->orderBy('first_name', 'ASC')->orderBy('last_name', 'ASC')->findAll(), 'doctors' => $this->doctor->select('id, first_name, last_name, doctor_code')->where('status', 1)->orderBy('first_name', 'ASC')->orderBy('last_name', 'ASC')->findAll(), 'clinics' => $this->clinic->dropdown()]);
    }

    public function view(int $id)
    {
        $appointment = $this->appointment->select('
                appointments.*,
                patients.registration_number,
                patients.first_name AS patient_first_name,
                patients.middle_name AS patient_middle_name,
                patients.last_name AS patient_last_name,
                patients.mobile_number AS patient_mobile,
                patients.email AS patient_email,
                patients.source_type AS patient_source_type,
                doctors.first_name AS doctor_first_name,
                doctors.last_name AS doctor_last_name,
                doctors.doctor_code,
                clinics.clinic_name
            ')->join('patients', 'patients.id = appointments.patient_id')->join('doctors', 'doctors.id = appointments.doctor_id')->join('clinics', 'clinics.id = appointments.clinic_id')->where('appointments.id', $id)->first();

        if ($appointment === null) {
            return redirect()->to(base_url('admin/appointments'))->with('error', 'Appointment not found.');
        }

        return view(ADMIN_VIEW . 'pages/appointment/view', ['appointment' => $appointment]);
    }

    public function calendar()
    {
        $timezone = new \DateTimeZone((string) config('App')->appTimezone);
        $today = new \DateTimeImmutable('now', $timezone);

        return view(ADMIN_VIEW . 'pages/appointment/calendar', [
            'calendarToday' => $today->format('Y-m-d'),
            'calendarTimezone' => $timezone->getName(),
            'clinics' => $this->clinic->dropdown(),
            'doctors' => $this->doctor->select('id, first_name, last_name, doctor_code')->where('status', 1)->orderBy('first_name', 'ASC')->orderBy('last_name', 'ASC')->findAll(),
        ]);
    }

    public function calendarData()
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'GET') {
            return $this->invalidRequest();
        }
        $search = trim((string) $this->request->getGet('search'));
        $status = trim((string) $this->request->getGet('status'));
        $appointmentType = trim((string) $this->request->getGet('appointment_type'));
        $clinicId = (int) $this->request->getGet('clinic_id');
        $doctorId = (int) $this->request->getGet('doctor_id');
        $dateFromInput = trim((string) $this->request->getGet('date_from'));
        $dateToInput = trim((string) $this->request->getGet('date_to'));
        $dateFrom = $dateFromInput === '' ? '' : rootins_parse_date($dateFromInput);
        $dateTo = $dateToInput === '' ? '' : rootins_parse_date($dateToInput);
        $errors = [];

        if ($dateFromInput !== '' && $dateFrom === null) {
            $errors['date_from'] = 'Enter the start date as dd-mm-yyyy.';
        }
        if ($dateToInput !== '' && $dateTo === null) {
            $errors['date_to'] = 'Enter the end date as dd-mm-yyyy.';
        }
        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            $errors['date_to'] = 'The end date must be on or after the start date.';
        }
        if ($errors !== []) {
            return $this->validationFailure($errors);
        }

        $query = $this->appointment->select('
                appointments.id,
                appointments.appointment_number,
                appointments.appointment_date,
                appointments.appointment_time,
                appointments.duration_minutes,
                appointments.appointment_type,
                appointments.service_name,
                appointments.queue_position,
                appointments.status,
                patients.registration_number,
                patients.first_name AS patient_first_name,
                patients.middle_name AS patient_middle_name,
                patients.last_name AS patient_last_name,
                patients.mobile_number AS patient_mobile,
                patients.email AS patient_email,
                patients.source_type AS patient_source_type,
                doctors.first_name AS doctor_first_name,
                doctors.last_name AS doctor_last_name,
                doctors.doctor_code,
                appointments.clinic_id,
                clinics.clinic_name
            ')->join('patients', 'patients.id = appointments.patient_id')->join('doctors', 'doctors.id = appointments.doctor_id')->join('clinics', 'clinics.id = appointments.clinic_id');

        if ($search !== '') {
            $query->groupStart()->like('appointments.appointment_number', $search)->orLike('appointments.service_name', $search)->orLike('patients.registration_number', $search)->orLike('patients.first_name', $search)->orLike('patients.last_name', $search)->orLike('patients.mobile_number', $search)->orLike('patients.mobile_normalized', $search)->orLike('patients.email', $search)->orLike('doctors.first_name', $search)->orLike('doctors.last_name', $search)->orLike('doctors.doctor_code', $search)->orLike('clinics.clinic_name', $search)->groupEnd();
        }

        if (in_array($status, ['scheduled', 'confirmed', 'checked_in', 'in_queue', 'completed', 'cancelled', 'no_show'], true)) {
            $query->where('appointments.status', $status);
        }
        if (in_array($appointmentType, ['in_person', 'video', 'phone'], true)) {
            $query->where('appointments.appointment_type', $appointmentType);
        }
        if ($clinicId > 0) {
            $query->where('appointments.clinic_id', $clinicId);
        }
        if ($doctorId > 0) {
            $query->where('appointments.doctor_id', $doctorId);
        }
        if ($dateFrom !== '') {
            $query->where('appointments.appointment_date >=', $dateFrom);
        }
        if ($dateTo !== '') {
            $query->where('appointments.appointment_date <=', $dateTo);
        }

        $appointments = $query->orderBy('appointments.appointment_date', 'ASC')->orderBy('appointments.appointment_time', 'ASC')->findAll();
        $timezone = new \DateTimeZone((string) config('App')->appTimezone);
        $events = array_map(static function (array $appointment) use ($timezone): array {
            $patientName = trim(implode(' ', array_filter([$appointment['patient_first_name'] ?? '', $appointment['patient_middle_name'] ?? '', $appointment['patient_last_name'] ?? ''], static fn ($part) => $part !== null && $part !== '')));
            $doctorName = trim(implode(' ', array_filter([$appointment['doctor_first_name'] ?? '', $appointment['doctor_last_name'] ?? ''], static fn ($part) => $part !== null && $part !== '')));
            $start = new \DateTimeImmutable($appointment['appointment_date'] . ' ' . $appointment['appointment_time'], $timezone);
            $end = $start->modify('+' . max(1, (int) $appointment['duration_minutes']) . ' minutes');

            return [
                'id' => (int) $appointment['id'],
                'appointment_number' => $appointment['appointment_number'],
                'patient_name' => $patientName,
                'registration_number' => $appointment['registration_number'],
                'patient_mobile' => $appointment['patient_mobile'] ?? null,
                'patient_email' => $appointment['patient_email'] ?? null,
                'doctor_name' => $doctorName,
                'doctor_code' => $appointment['doctor_code'] ?? null,
                'clinic_name' => $appointment['clinic_name'],
                'appointment_date' => $appointment['appointment_date'],
                'appointment_time' => substr((string) $appointment['appointment_time'], 0, 5),
                'service_name' => $appointment['service_name'] ?? null,
                'queue_position' => $appointment['queue_position'] ?? null,
                'appointment_type' => $appointment['appointment_type'],
                'status' => $appointment['status'],
                'start' => $start->format('Y-m-d\TH:i:s'),
                'end' => $end->format('Y-m-d\TH:i:s'),
            ];
        }, $appointments);

        return $this->response->setJSON(['status' => true, 'msg' => '', 'data' => $events, 'timezone' => $timezone->getName()]);
    }

    public function queue()
    {
        $clinics = $this->clinic->dropdown();
        $clinicId = (int) ($clinics[0]['id'] ?? 0);

        $today = (new \DateTimeImmutable('now', new \DateTimeZone(config('App')->appTimezone)))->format('Y-m-d');
        return view(ADMIN_VIEW . 'pages/appointment/queue', ['clinics' => $clinics, 'selectedClinic' => $clinicId, 'selectedDate' => rootins_format_date($today)]);
    }

    public function queueData()
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'GET') {
            return $this->invalidRequest();
        }
        $clinicId = (int) $this->request->getGet('clinic_id');
        $date = (string) $this->request->getGet('appointment_date');
        $errors = $this->validateQueueScope($clinicId, $date);

        if ($errors !== []) {
            return $this->validationFailure($errors);
        }

        return $this->response->setJSON(['status' => true, 'msg' => '', 'data' => $this->queueAppointments($clinicId, $date)]);
    }

    public function checkIn(int $id)
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->invalidRequest();
        }
        $appointment = $this->appointment->find($id);
        $today = date('Y-m-d');

        if ($appointment === null) {
            return $this->failure('Appointment not found.');
        }

        if (!in_array($appointment['status'], ['scheduled', 'confirmed'], true)) {
            return $this->failure('Only scheduled or confirmed appointments can be checked in.');
        }

        if ($appointment['appointment_date'] !== $today) {
            return $this->failure('Only appointments for today can be checked in.');
        }

        if ($this->clinic->where('id', (int) $appointment['clinic_id'])->where('status', 1)->first() === null) {
            return $this->failure('The appointment clinic is inactive.');
        }
        $db = db_connect();
        $db->transBegin();

        try {
            $result = $db->query('SELECT COALESCE(MAX(queue_position), 0) AS last_position
                 FROM appointments
                 WHERE clinic_id = ? AND appointment_date = ?
                   AND status IN (\'checked_in\', \'in_queue\')
                 FOR UPDATE', [(int) $appointment['clinic_id'], $today])->getRowArray();
            $now = date('Y-m-d H:i:s');

            if (!$this->appointment->update($id, ['status' => 'checked_in', 'checked_in_at' => $now, 'queue_position' => (int) ($result['last_position'] ?? 0) + 1, 'updated_at' => $now]) || !$db->transCommit()) {
                throw new \RuntimeException('Check-in failed.');
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Appointment check-in failed: ' . $e->getMessage());

            return $this->failure('Unable to check in appointment.');
        }

        return $this->response->setJSON(['status' => true, 'msg' => 'Appointment checked in successfully.']);
    }

    public function moveQueue(int $id, string $direction)
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->invalidRequest();
        }

        if (!in_array($direction, ['up', 'down'], true)) {
            return $this->failure('Invalid queue direction.');
        }
        $appointment = $this->appointment->find($id);

        if ($appointment === null) {
            return $this->failure('Appointment not found.');
        }

        if (!in_array($appointment['status'], ['checked_in', 'in_queue'], true)) {
            return $this->failure('Only checked-in appointments can be moved in the queue.');
        }
        $db = db_connect();
        $db->transBegin();

        try {
            $rows = $db->query('SELECT id, queue_position
                 FROM appointments
                 WHERE clinic_id = ? AND appointment_date = ?
                   AND status IN (\'checked_in\', \'in_queue\')
                 ORDER BY queue_position ASC, checked_in_at ASC, appointment_time ASC, id ASC
                 FOR UPDATE', [(int) $appointment['clinic_id'], $appointment['appointment_date']])->getResultArray();
            $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
            $index = array_search($id, $ids, true);
            $neighborIndex = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || !isset($rows[$neighborIndex])) {
                $db->transRollback();

                return $this->failure('Appointment is already at the queue boundary.');
            }
            $current = $rows[$index];
            $neighbor = $rows[$neighborIndex];
            $now = date('Y-m-d H:i:s');
            $table = $db->table('appointments');
            $table->where('id', (int) $current['id'])->update(['queue_position' => null, 'updated_at' => $now]);
            $table->where('id', (int) $neighbor['id'])->update(['queue_position' => (int) $current['queue_position'], 'updated_at' => $now]);
            $table->where('id', (int) $current['id'])->update(['queue_position' => (int) $neighbor['queue_position'], 'updated_at' => $now]);

            if (!$db->transCommit()) {
                throw new \RuntimeException('Queue movement failed.');
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Appointment queue movement failed: ' . $e->getMessage());

            return $this->failure('Unable to move appointment in the queue.');
        }

        return $this->response->setJSON(['status' => true, 'msg' => 'Queue order updated successfully.']);
    }

    public function queueStatus(int $id)
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->invalidRequest();
        }
        $appointment = $this->appointment->find($id);
        $status = (string) $this->request->getPost('status');

        if ($appointment === null) {
            return $this->failure('Appointment not found.');
        }

        if (!$this->isValidStatusTransition((string) $appointment['status'], $status)) {
            return $this->failure('This appointment status transition is not allowed.');
        }

        if (!in_array($status, ['in_queue', 'completed', 'no_show', 'cancelled'], true)) {
            return $this->failure('Invalid queue status.');
        }
        $now = date('Y-m-d H:i:s');
        $data = ['status' => $status, 'updated_at' => $now];

        if ($status !== 'in_queue') {
            $data['queue_position'] = null;
        }

        if ($status === 'cancelled') {
            $data['cancelled_at'] = $now;
            $data['cancelled_by'] = $this->authenticatedUserId();
            $data['cancellation_reason'] = $this->nullableString('cancellation_reason');
        }

        if (!$this->appointment->update($id, $data)) {
            return $this->failure('Unable to update appointment status.');
        }

        return $this->response->setJSON(['status' => true, 'msg' => 'Appointment status updated successfully.']);
    }

    public function reports()
    {
        $bookingSources = $this->appointment->select('booking_source')->distinct()->orderBy('booking_source')->findAll();
        $creatorTypes = $this->appointment->select('created_by_type')->distinct()->orderBy('created_by_type')->findAll();
        $services = $this->appointment->select('service_name')->where('service_name !=', '')->distinct()->orderBy('service_name')->findAll();

        return view(ADMIN_VIEW . 'pages/appointment/reports', [
            'clinics' => $this->clinic->dropdown(),
            'doctors' => $this->doctor->select('id, first_name, last_name, doctor_code')->orderBy('first_name', 'ASC')->orderBy('last_name', 'ASC')->findAll(),
            'services' => array_values(array_filter(array_column($services, 'service_name'), static fn ($service): bool => trim((string) $service) !== '')),
            'bookingSources' => array_column($bookingSources, 'booking_source'),
            'creatorTypes' => array_column($creatorTypes, 'created_by_type'),
            'canEdit' => $this->hasPermission('appointment.edit'),
        ]);
    }

    public function reportsData()
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'GET') {
            return $this->invalidRequest();
        }

        $filters = $this->reportFilters();

        if ($filters['errors'] !== []) {
            return $this->validationFailure($filters['errors']);
        }

        $result = $this->reportAppointments($filters['data']);
        $summary = $this->reportSummary($filters['data']);

        return $this->response->setJSON([
            'status' => true,
            'msg' => '',
            'data' => [
                'summary' => $summary,
                'rows' => $result['rows'],
                'pagination' => [
                    'page' => $filters['data']['page'],
                    'page_size' => $filters['data']['page_size'],
                    'total' => $result['total'],
                    'pages' => $result['pages'],
                ],
            ],
        ]);
    }

    public function edit(int $id)
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'GET') {
            return $this->invalidRequest();
        }
        $appointment = $this->appointment->find($id);

        if ($appointment === null) {
            return $this->failure('Appointment not found.');
        }

        return $this->response->setJSON(['status' => true, 'msg' => '', 'data' => $appointment]);
    }

    public function save()
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->invalidRequest();
        }
        $validation = $this->validateAppointment();

        if ($validation !== []) {
            return $this->validationFailure($validation);
        }

        if (!in_array((string) $this->request->getPost('status'), ['scheduled', 'confirmed'], true)) {
            return $this->validationFailure(['status' => 'New appointments must be scheduled or confirmed.']);
        }
        $references = $this->validateReferences();

        if ($references !== []) {
            return $this->validationFailure($references);
        }
        $userId = $this->authenticatedUserId();

        if ($userId === null) {
            return $this->failure('Authenticated user not found.');
        }
        $data = $this->appointmentData($userId);
        $temporaryNumber = 'TMP-' . bin2hex(random_bytes(8));
        $data['appointment_number'] = $temporaryNumber;
        $now = date('Y-m-d H:i:s');
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        $db = db_connect();
        $db->transBegin();

        try {
            $appointmentId = $this->appointment->insert($data);

            if (!$appointmentId) {
                throw new \RuntimeException('Appointment insert failed.');
            }
            $appointmentNumber = sprintf('APT-%06d', (int) $appointmentId);

            if (!$this->appointment->update($appointmentId, ['appointment_number' => $appointmentNumber, 'updated_at' => date('Y-m-d H:i:s')])) {
                throw new \RuntimeException('Appointment number assignment failed.');
            }

            if (!$db->transCommit()) {
                throw new \RuntimeException('Appointment transaction failed.');
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Appointment creation failed: ' . $e->getMessage());

            return $this->failure('Unable to create appointment.');
        }

        return $this->response->setJSON(['status' => true, 'msg' => 'Appointment created successfully.', 'data' => ['id' => (int) $appointmentId, 'appointment_number' => $appointmentNumber]]);
    }

    public function update(int $id)
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->invalidRequest();
        }
        $existing = $this->appointment->find($id);

        if ($existing === null) {
            return $this->failure('Appointment not found.');
        }
        $newStatus = (string) $this->request->getPost('status');

        if (!$this->isValidStatusTransition((string) $existing['status'], $newStatus)) {
            return $this->validationFailure(['status' => 'This appointment status transition is not allowed.']);
        }

        if ($newStatus !== $existing['status'] && in_array($newStatus, ['checked_in', 'in_queue'], true)) {
            return $this->validationFailure(['status' => 'Use the Waiting Queue to check in or queue an appointment.']);
        }
        $validation = $this->validateAppointment();

        if ($validation !== []) {
            return $this->validationFailure($validation);
        }
        $references = $this->validateReferences();

        if ($references !== []) {
            return $this->validationFailure($references);
        }
        $userId = $this->authenticatedUserId();

        if ($userId === null) {
            return $this->failure('Authenticated user not found.');
        }
        $data = $this->appointmentData($userId);
        unset($data['created_by_type'], $data['created_by_id'], $data['booking_source']);

        if (in_array($existing['status'], ['checked_in', 'in_queue'], true) && !in_array($newStatus, ['checked_in', 'in_queue'], true)) {
            $data['queue_position'] = null;
        }

        if (!$this->appointment->update($id, $data)) {
            log_message('error', 'Appointment update failed for ID ' . $id . '.');

            return $this->failure('Unable to update appointment.');
        }

        return $this->response->setJSON(['status' => true, 'msg' => 'Appointment updated successfully.']);
    }

    public function delete(int $id)
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->invalidRequest();
        }

        if ($this->appointment->find($id) === null) {
            return $this->failure('Appointment not found.');
        }

        if (!$this->appointment->delete($id)) {
            return $this->failure('Unable to delete appointment.');
        }

        return $this->response->setJSON(['status' => true, 'msg' => 'Appointment deleted successfully.']);
    }

    private function validateAppointment(): array
    {
        $rules = ['patient_id' => ['label' => 'Patient', 'rules' => 'required|is_natural_no_zero'], 'doctor_id' => ['label' => 'Doctor', 'rules' => 'required|is_natural_no_zero'], 'clinic_id' => ['label' => 'Clinic', 'rules' => 'required|is_natural_no_zero'], 'appointment_date' => ['label' => 'Appointment Date', 'rules' => 'required'], 'appointment_time' => ['label' => 'Appointment Time', 'rules' => 'required|regex_match[/^\d{2}:\d{2}(:\d{2})?$/]'], 'duration_minutes' => ['label' => 'Duration', 'rules' => 'required|integer|greater_than[0]|less_than_equal_to[999]'], 'appointment_type' => ['label' => 'Appointment Type', 'rules' => 'required|in_list[in_person,video,phone]'], 'status' => ['label' => 'Status', 'rules' => 'required|in_list[scheduled,confirmed,checked_in,in_queue,completed,cancelled,no_show]'], 'reason_for_visit' => ['label' => 'Reason for Visit', 'rules' => 'permit_empty'], 'notes' => ['label' => 'Notes', 'rules' => 'permit_empty'], 'cancellation_reason' => ['label' => 'Cancellation Reason', 'rules' => 'permit_empty']];

        if (! $this->validate($rules)) {
            return $this->validator->getErrors();
        }

        return rootins_parse_date($this->request->getPost('appointment_date')) === null ? ['appointment_date' => 'Enter a valid appointment date as dd-mm-yyyy.'] : [];
    }

    private function validateReferences(): array
    {
        $patientId = (int) $this->request->getPost('patient_id');
        $doctorId = (int) $this->request->getPost('doctor_id');
        $clinicId = (int) $this->request->getPost('clinic_id');
        $errors = [];
        $patient = $this->patient->where('id', $patientId)->where('status', 1)->first();
        $doctor = $this->doctor->where('id', $doctorId)->where('status', 1)->first();
        $clinic = $this->clinic->where('id', $clinicId)->where('status', 1)->first();

        if ($patient === null) {
            $errors['patient_id'] = 'The selected patient is invalid.';
        }

        if ($doctor === null) {
            $errors['doctor_id'] = 'The selected doctor is invalid or inactive.';
        }

        if ($clinic === null) {
            $errors['clinic_id'] = 'The selected clinic is invalid or inactive.';
        }

        if ($doctor !== null && $clinic !== null && (int) $doctor['clinic_id'] !== $clinicId) {
            $errors['doctor_id'] = 'The selected doctor is not assigned to the selected clinic.';
        }

        return $errors;
    }

    private function validateQueueScope(int $clinicId, string $date): array
    {
        $errors = [];

        if ($clinicId <= 0 || $this->clinic->where('id', $clinicId)->where('status', 1)->first() === null) {
            $errors['clinic_id'] = 'The selected clinic is invalid or inactive.';
        }
        if (rootins_parse_date($date) === null) {
            $errors['appointment_date'] = 'The appointment date is invalid.';
        }

        return $errors;
    }

    private function queueAppointments(int $clinicId, string $date): array
    {
        return $this->appointment->select('
                appointments.id,
                appointments.queue_position,
                appointments.appointment_number,
                appointments.appointment_time,
                appointments.appointment_type,
                appointments.checked_in_at,
                appointments.status,
                patients.registration_number,
                patients.first_name AS patient_first_name,
                patients.middle_name AS patient_middle_name,
                patients.last_name AS patient_last_name,
                doctors.first_name AS doctor_first_name,
                doctors.last_name AS doctor_last_name,
                clinics.clinic_name
            ')->join('patients', 'patients.id = appointments.patient_id')->join('doctors', 'doctors.id = appointments.doctor_id')->join('clinics', 'clinics.id = appointments.clinic_id')->where('appointments.clinic_id', $clinicId)->where('appointments.appointment_date', $date)->whereIn('appointments.status', ['scheduled', 'confirmed', 'checked_in', 'in_queue'])->orderBy('appointments.queue_position', 'ASC')->orderBy('appointments.checked_in_at', 'ASC')->orderBy('appointments.appointment_time', 'ASC')->orderBy('appointments.id', 'ASC')->findAll();
    }

    private function isValidStatusTransition(string $from, string $to): bool
    {
        $transitions = ['scheduled' => ['scheduled', 'confirmed', 'checked_in', 'cancelled', 'no_show'], 'confirmed' => ['confirmed', 'checked_in', 'cancelled', 'no_show'], 'checked_in' => ['checked_in', 'in_queue', 'cancelled', 'no_show'], 'in_queue' => ['in_queue', 'completed', 'cancelled', 'no_show'], 'completed' => ['completed'], 'cancelled' => ['cancelled'], 'no_show' => ['no_show']];

        return in_array($to, $transitions[$from] ?? [], true);
    }

    private function reportFilters(): array
    {
        $dateFromInput = trim((string) $this->request->getGet('date_from'));
        $dateToInput = trim((string) $this->request->getGet('date_to'));
        $dateFrom = $dateFromInput !== '' ? rootins_parse_date($dateFromInput) : null;
        $dateTo = $dateToInput !== '' ? rootins_parse_date($dateToInput) : null;
        $data = [
            'date_from' => $dateFrom ?? '',
            'date_to' => $dateTo ?? '',
            'search' => trim((string) $this->request->getGet('search')),
            'clinic_id' => trim((string) $this->request->getGet('clinic_id')),
            'doctor_id' => trim((string) $this->request->getGet('doctor_id')),
            'service_name' => trim((string) $this->request->getGet('service_name')),
            'appointment_type' => trim((string) $this->request->getGet('appointment_type')),
            'status' => trim((string) $this->request->getGet('status')),
            'booking_source' => trim((string) $this->request->getGet('booking_source')),
            'created_by_type' => trim((string) $this->request->getGet('created_by_type')),
            'page' => max(1, (int) $this->request->getGet('page')),
            'page_size' => in_array((int) $this->request->getGet('page_size'), [10, 15, 25, 50], true) ? (int) $this->request->getGet('page_size') : 15,
        ];
        $errors = [];

        if ($dateFromInput !== '' && $dateFrom === null) {
            $errors['date_from'] = 'Enter a valid From Date as dd-mm-yyyy.';
        }
        if ($dateToInput !== '' && $dateTo === null) {
            $errors['date_to'] = 'Enter a valid To Date as dd-mm-yyyy.';
        }
        if ($dateFrom !== null && $dateTo !== null && $dateTo < $dateFrom) {
            $errors['date_to'] = 'To Date must be on or after From Date.';
        }

        foreach (['clinic_id' => $this->clinic, 'doctor_id' => $this->doctor] as $field => $model) {
            if ($data[$field] !== '' && (!ctype_digit($data[$field]) || (int) $data[$field] <= 0 || $model->find((int) $data[$field]) === null)) {
                $errors[$field] = 'The selected value is invalid.';
            }
        }

        if ($data['service_name'] !== '' && mb_strlen($data['service_name']) > 150) {
            $errors['service_name'] = 'The service filter is too long.';
        }
        if ($data['appointment_type'] !== '' && !in_array($data['appointment_type'], ['in_person', 'video', 'phone'], true)) {
            $errors['appointment_type'] = 'The appointment type is invalid.';
        }
        if ($data['status'] !== '' && !in_array($data['status'], ['scheduled', 'confirmed', 'checked_in', 'in_queue', 'completed', 'cancelled', 'no_show'], true)) {
            $errors['status'] = 'The appointment status is invalid.';
        }
        foreach (['booking_source', 'created_by_type'] as $field) {
            if ($data[$field] !== '' && (strlen($data[$field]) > 30 || !preg_match('/^[A-Za-z0-9_-]+$/', $data[$field]))) {
                $errors[$field] = 'The filter value is invalid.';
            }
        }

        return ['data' => $data, 'errors' => $errors];
    }

    private function buildReportQuery(array $filters, bool $summary = false)
    {
        $select = $summary
            ? 'appointments.status, COUNT(*) AS status_count'
            : 'appointments.id, appointments.appointment_number, appointments.appointment_date, appointments.appointment_time, appointments.appointment_type, appointments.service_name, appointments.queue_position, appointments.status, appointments.booking_source, appointments.created_at, patients.registration_number, patients.first_name AS patient_first_name, patients.middle_name AS patient_middle_name, patients.last_name AS patient_last_name, patients.mobile_number AS patient_mobile, doctors.first_name AS doctor_first_name, doctors.last_name AS doctor_last_name, doctors.doctor_code, clinics.clinic_name, (SELECT c.status FROM consultations c WHERE c.appointment_id = appointments.id ORDER BY c.id DESC LIMIT 1) AS consultation_status, (SELECT i.payment_status FROM invoices i WHERE i.appointment_id = appointments.id ORDER BY i.id DESC LIMIT 1) AS payment_status';
        $builder = $this->appointment->select($select, false)->join('patients', 'patients.id = appointments.patient_id')->join('doctors', 'doctors.id = appointments.doctor_id')->join('clinics', 'clinics.id = appointments.clinic_id');

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $builder->groupStart()->like('appointments.appointment_number', $search)->orLike('appointments.service_name', $search)->orLike('patients.registration_number', $search)->orLike('patients.first_name', $search)->orLike('patients.last_name', $search)->orLike('patients.mobile_number', $search)->orLike('patients.email', $search)->orLike('doctors.first_name', $search)->orLike('doctors.last_name', $search)->orLike('doctors.doctor_code', $search)->orLike('clinics.clinic_name', $search)->groupEnd();
        }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $field => $operator) {
            if ($filters[$field] !== '') {
                $builder->where('appointments.appointment_date ' . $operator, $filters[$field]);
            }
        }
        foreach (['clinic_id', 'doctor_id', 'appointment_type', 'status', 'booking_source', 'created_by_type'] as $field) {
            if ($filters[$field] !== '') {
                $builder->where('appointments.' . $field, $filters[$field]);
            }
        }
        if ($filters['service_name'] !== '') {
            $builder->where('appointments.service_name', $filters['service_name']);
        }
        if ($summary) {
            $builder->groupBy('appointments.status');
        }

        return $builder;
    }

    private function reportAppointments(array $filters): array
    {
        $total = $this->buildReportQuery($filters)->countAllResults();
        $pages = max(1, (int) ceil($total / $filters['page_size']));
        $page = min($filters['page'], $pages);
        $builder = $this->buildReportQuery($filters);
        $rows = $builder->orderBy('appointments.appointment_date', 'DESC')->orderBy('appointments.appointment_time', 'DESC')->get($filters['page_size'], ($page - 1) * $filters['page_size'])->getResultArray();

        return [
            'total' => $total,
            'pages' => $pages,
            'rows' => array_map(static function (array $row): array {
                return [
                    'id' => (int) $row['id'],
                    'appointment_number' => $row['appointment_number'],
                    'appointment_date' => rootins_format_date($row['appointment_date']),
                    'appointment_time' => substr((string) $row['appointment_time'], 0, 5),
                    'registration_number' => $row['registration_number'] ?? null,
                    'patient_name' => trim(implode(' ', array_filter([$row['patient_first_name'] ?? '', $row['patient_middle_name'] ?? '', $row['patient_last_name'] ?? ''], static fn ($part) => $part !== null && $part !== ''))),
                    'patient_mobile' => $row['patient_mobile'] ?? null,
                    'doctor' => trim(implode(' ', array_filter([$row['doctor_first_name'] ?? '', $row['doctor_last_name'] ?? ''], static fn ($part) => $part !== null && $part !== ''))),
                    'doctor_code' => $row['doctor_code'] ?? null,
                    'clinic' => $row['clinic_name'] ?? null,
                    'service_name' => $row['service_name'] ?? null,
                    'queue_position' => $row['queue_position'] ?? null,
                    'appointment_type' => $row['appointment_type'] ?? null,
                    'status' => $row['status'] ?? null,
                    'consultation_status' => $row['consultation_status'] ?? null,
                    'payment_status' => $row['payment_status'] ?? null,
                ];
            }, $rows),
        ];
    }

    private function reportSummary(array $filters): array
    {
        $summary = ['total' => 0, 'scheduled' => 0, 'confirmed' => 0, 'checked_in' => 0, 'in_queue' => 0, 'completed' => 0, 'cancelled' => 0, 'no_show' => 0];

        foreach ($this->buildReportQuery($filters, true)->get()->getResultArray() as $row) {
            $status = (string) ($row['status'] ?? '');
            $count = (int) ($row['status_count'] ?? 0);
            if (array_key_exists($status, $summary)) {
                $summary[$status] += $count;
            }
            $summary['total'] += $count;
        }

        return $summary;
    }

    private function appointmentData(int $userId): array
    {
        $status = (string) $this->request->getPost('status');
        $cancelled = $status === 'cancelled';
        $cancellationReason = trim((string) $this->request->getPost('cancellation_reason'));

        return ['patient_id' => (int) $this->request->getPost('patient_id'), 'doctor_id' => (int) $this->request->getPost('doctor_id'), 'clinic_id' => (int) $this->request->getPost('clinic_id'), 'appointment_date' => rootins_parse_date($this->request->getPost('appointment_date')) ?? '', 'appointment_time' => trim((string) $this->request->getPost('appointment_time')), 'duration_minutes' => (int) $this->request->getPost('duration_minutes'), 'appointment_type' => $this->request->getPost('appointment_type'), 'status' => $status, 'created_by_type' => 'user', 'created_by_id' => $userId, 'booking_source' => 'admin', 'reason_for_visit' => $this->nullableString('reason_for_visit'), 'notes' => $this->nullableString('notes'), 'cancelled_at' => $cancelled ? date('Y-m-d H:i:s') : null, 'cancelled_by' => $cancelled ? $userId : null, 'cancellation_reason' => $cancelled && $cancellationReason !== '' ? $cancellationReason : null, 'updated_at' => date('Y-m-d H:i:s')];
    }

    private function authenticatedUserId(): ?int
    {
        return isset($this->user['id']) && (int) $this->user['id'] > 0 ? (int) $this->user['id'] : null;
    }

    private function nullableString(string $field): ?string
    {
        $value = trim((string) $this->request->getPost($field));

        return $value === '' ? null : $value;
    }

    private function validationFailure(array $errors)
    {
        return $this->response->setJSON(['status' => false, 'msg' => reset($errors), 'errors' => $errors]);
    }

    private function failure(string $message)
    {
        return $this->response->setJSON(['status' => false, 'msg' => $message]);
    }

    private function invalidRequest()
    {
        return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
    }
}
