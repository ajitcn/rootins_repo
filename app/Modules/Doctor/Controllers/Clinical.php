<?php

namespace Doctor\Controllers;

use Admin\Models\ConsultationModel;
use Admin\Models\Doctor\DoctorLeaveModel;
use Admin\Models\Doctor\DoctorScheduleModel;
use Admin\Models\MedicineModel;
use Admin\Models\PrescriptionItemModel;
use Admin\Models\PrescriptionOptionModel;
use Admin\Models\PrescriptionModel;
use Admin\Services\MedicineLookupService;
use Admin\Models\User;
use App\Controllers\BaseController;
use App\Services\AppointmentConsultationEligibility;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Doctor\Models\FollowUpTypeModel;
use Throwable;

class Clinical extends BaseController
{
    private const QUEUE_TRANSITIONS = ['scheduled' => ['scheduled', 'confirmed', 'checked_in', 'cancelled', 'no_show'], 'confirmed' => ['confirmed', 'checked_in', 'cancelled', 'no_show'], 'checked_in' => ['checked_in', 'in_queue', 'cancelled', 'no_show'], 'in_queue' => ['in_queue', 'in_consultation', 'completed', 'no_show'], 'in_consultation' => ['in_consultation', 'completed', 'no_show'], 'completed' => ['completed'], 'cancelled' => ['cancelled'], 'no_show' => ['no_show']];

    protected ConsultationModel $consultations;

    protected PrescriptionModel $prescriptions;

    protected PrescriptionItemModel $prescriptionItems;

    protected PrescriptionOptionModel $prescriptionOptions;

    protected MedicineLookupService $medicineLookups;

    protected FollowUpTypeModel $followUpTypes;

    protected AppointmentConsultationEligibility $appointmentConsultationEligibility;

    public function __construct()
    {
        $this->consultations = new ConsultationModel();
        $this->prescriptions = new PrescriptionModel();
        $this->prescriptionItems = new PrescriptionItemModel();
        $this->prescriptionOptions = new PrescriptionOptionModel();
        $this->medicineLookups = new MedicineLookupService();
        $this->followUpTypes = new FollowUpTypeModel();
        $this->appointmentConsultationEligibility = new AppointmentConsultationEligibility();
    }

    public function medicineSearch(): ResponseInterface
    {
        if ($this->request->getMethod(true) !== 'GET') {
            return $this->json(405, false, 'Invalid request.');
        }

        if ($this->context() === null) {
            return $this->json(403, false, 'Doctor scope is invalid.');
        }
        $term = trim((string) $this->request->getGet('q'));

        if (mb_strlen($term) < 2) {
            return $this->json(200, true, 'Medicine search requires at least two characters.', ['items' => []]);
        }
        $items = (new MedicineModel())
            ->select('id, medicine_name, generic_name, medicine_code, strength')
            ->where('status', 'active')
            ->groupStart()
            ->like('medicine_name', $term)
            ->orLike('generic_name', $term)
            ->orLike('medicine_code', $term)
            ->orLike('strength', $term)
            ->groupEnd()
            ->orderBy('medicine_name', 'ASC')
            ->findAll(20);

        $items = array_map(static function (array $item): array {
            $item['display_name'] = trim(implode(' · ', array_filter([
                $item['medicine_name'] ?? '',
                $item['strength'] ?? '',
            ], static fn ($value) => $value !== null && $value !== '')));

            return $item;
        }, $items);

        return $this->json(200, true, 'Medicines loaded.', ['items' => $items]);
    }

    public function medicineAutofill(int $medicineId): ResponseInterface
    {
        if ($this->request->getMethod(true) !== 'GET') {
            return $this->json(405, false, 'Invalid request.');
        }
        $context = $this->context();

        if ($context === null || $medicineId <= 0) {
            return $this->json(403, false, 'Doctor scope is invalid.');
        }
        $medicine = (new MedicineModel())
            ->select('id, prescription_duration_id, prescription_frequency_id, prescription_dose_id, prescription_intake_id, prescription_note')
            ->where('id', $medicineId)
            ->where('status', 'active')
            ->first();

        if ($medicine === null) {
            return $this->json(404, false, 'Medicine not found.');
        }

        $autofill = [
            'prescription_duration_id' => $medicine['prescription_duration_id'] ?? '',
            'prescription_frequency_id' => $medicine['prescription_frequency_id'] ?? '',
            'prescription_dose_id' => $medicine['prescription_dose_id'] ?? '',
            'prescription_intake_id' => $medicine['prescription_intake_id'] ?? '',
            'prescription_note' => trim((string) ($medicine['prescription_note'] ?? '')),
        ];
        $this->audit('doctor.medicine_autofill_viewed', $context, ['medicine_id' => $medicineId]);

        return $this->json(200, true, 'Medicine values loaded.', $autofill);
    }

    public function index(): string|RedirectResponse
    {
        $context = $this->context();

        if ($context === null) {
            return redirect()->to(base_url('permission-denied'));
        }
        $db = db_connect();
        $today = date('Y-m-d');
        $clinic = $db->table('clinics')
            ->select('clinics.clinic_name, clinics.logo, clinics.address, clinics.mobile, clinics.telephone_country_code_id, telephone_country_codes.normalized_code AS telephone_country_code')
            ->join('telephone_country_codes', 'telephone_country_codes.id = clinics.telephone_country_code_id', 'left')
            ->where('clinics.id', $context['clinic_id'])
            ->get()
            ->getRowArray() ?? [];
        $clinic['mobile_display'] = service('telephoneContact')->formatDisplay($clinic['telephone_country_code'] ?? null, $clinic['mobile'] ?? null);
        $selectedAppointmentId = max(0, (int) $this->request->getGet('appointment_id'));
        $selectedPatientId = max(0, (int) $this->request->getGet('patient_id'));
        $selectedAppointment = null;

        if ($selectedAppointmentId > 0) {
            $selectedQuery = $this->todayAppointmentQuery($context, $today)
                ->where('appointments.id', $selectedAppointmentId);

            if ($selectedPatientId > 0) {
                $selectedQuery->where('appointments.patient_id', $selectedPatientId);
            }
            $selectedAppointment = $selectedQuery->get()->getRowArray();

            if ($selectedAppointment !== null && ! $this->appointmentConsultationEligibility->isConsultable($selectedAppointment)) {
                return redirect()->to(base_url('doctor/appointments'))->with('error', $this->appointmentConsultationEligibility->unavailableReason($selectedAppointment));
            }
        }
        $selectedPrescription = null;
        $selectedPrescriptionItems = [];

        if ($selectedAppointment !== null && ! empty($selectedAppointment['consultation_id'])) {
            $selectedPrescription = $this->prescriptions
                ->where('consultation_id', (int) $selectedAppointment['consultation_id'])
                ->where('doctor_id', $context['doctor_id'])
                ->where('clinic_id', $context['clinic_id'])
                ->first();

            if ($selectedPrescription !== null) {
                $selectedPrescriptionItems = $this->prescriptionItems
                    ->where('prescription_id', (int) $selectedPrescription['id'])
                    ->orderBy('id')
                    ->findAll();
                $selectedPrescriptionItems = $this->attachMedicineIds($selectedPrescriptionItems);
            }
        }
        $prescriptionOptionSets = $this->prescriptionOptionSets((int) $context['clinic_id']);
        $pageSize = (int) $this->request->getGet('page_size');
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $page = max(1, (int) $this->request->getGet('page'));
        $historyQuery = $db->table('consultations')->select('consultations.consultation_number, consultations.consultation_date, consultations.status, consultations.follow_up_date, patients.first_name AS patient_first_name, patients.last_name AS patient_last_name')->join('patients', 'patients.id = consultations.patient_id')->where('consultations.doctor_id', $context['doctor_id'])->where('consultations.clinic_id', $context['clinic_id'])->where('patients.clinic_id', $context['clinic_id']);
        $historyTotal = $historyQuery->countAllResults(false);
        $historyPages = max(1, (int) ceil($historyTotal / $pageSize));
        $history = $historyQuery->orderBy('consultations.consultation_date', 'DESC')->orderBy('consultations.id', 'DESC')->limit($pageSize, ($page - 1) * $pageSize)->get()->getResultArray();

        return view(DOCTOR_VIEW . 'pages/clinical', ['doctor' => $context['doctor'], 'clinic' => $clinic, 'selectedAppointment' => $selectedAppointment, 'selectedPrescription' => $selectedPrescription, 'selectedPrescriptionItems' => $selectedPrescriptionItems, 'prescriptionOptionSets' => $prescriptionOptionSets, 'medicineLookupOptions' => $this->medicineLookups->options(), 'followUpTypes' => $this->followUpTypes->activeForClinic($context['clinic_id']), 'prescriptionStatusDefault' => $this->prescriptionStatusDefault($prescriptionOptionSets), 'history' => $history, 'historyPagination' => ['page' => $page, 'pages' => $historyPages, 'page_size' => $pageSize, 'total' => $historyTotal], 'filters' => ['page' => $page, 'page_size' => $pageSize], 'schedules' => (new DoctorScheduleModel())->where('doctor_id', $context['doctor_id'])->where('status', 1)->orderBy('day_of_week')->orderBy('start_time')->findAll(), 'leaves' => (new DoctorLeaveModel())->getUpcomingLeaves($context['doctor_id'])]);
    }

    public function appointmentSearch(): ResponseInterface
    {
        if ($this->request->getMethod(true) !== 'GET') {
            return $this->json(405, false, 'Invalid request.');
        }
        $context = $this->context();

        if ($context === null) {
            return $this->json(403, false, 'Doctor scope is invalid.');
        }
        $search = trim((string) $this->request->getGet('q'));
        $requestedAppointmentId = max(0, (int) $this->request->getGet('appointment_id'));
        $requestedPatientId = max(0, (int) $this->request->getGet('patient_id'));
        $requestedSize = (int) $this->request->getGet('page_size');
        $pageSize = in_array($requestedSize, [10, 20, 50], true) ? $requestedSize : 20;
        $page = max(1, (int) $this->request->getGet('page'));
        $query = $this->todayAppointmentQuery($context, date('Y-m-d'));

        if ($requestedAppointmentId > 0) {
            $query->where('appointments.id', $requestedAppointmentId);

            if ($requestedPatientId > 0) {
                $query->where('appointments.patient_id', $requestedPatientId);
            }
        } elseif ($search !== '') {
            $query->groupStart()
                ->like('appointments.appointment_number', $search)
                ->orLike('patients.first_name', $search)
                ->orLike('patients.last_name', $search)
                ->orLike('patients.registration_number', $search)
                ->orLike('patients.mobile_normalized', $search)
                ->orLike('patients.mobile_number', $search)
                ->groupEnd();
        }
        $total = $query->countAllResults(false);
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $rows = $query->orderBy('appointments.queue_position')->orderBy('appointments.appointment_time')->orderBy('appointments.id')->limit($pageSize, ($page - 1) * $pageSize)->get()->getResultArray();
        $items = array_map(function (array $appointment): array {
            return [
                'id' => (int) $appointment['id'],
                'appointment_number' => (string) ($appointment['appointment_number'] ?? ''),
                'patient_id' => (int) ($appointment['patient_id'] ?? 0),
                'patient_name' => trim(($appointment['patient_first_name'] ?? '') . ' ' . ($appointment['patient_last_name'] ?? '')),
                'mobile' => service('telephoneContact')->formatDisplay($appointment['patient_telephone_country_code'] ?? null, $appointment['patient_mobile_normalized'] ?? $appointment['patient_mobile_number'] ?? null, $appointment['patient_legacy_country_code'] ?? null),
                'registration_number' => (string) ($appointment['registration_number'] ?? ''),
                'queue_number' => (int) ($appointment['queue_position'] ?? 0),
                'appointment_date' => $appointment['appointment_date'] !== null ? date('d-m-Y', strtotime((string) $appointment['appointment_date'])) : '',
                'appointment_time' => substr((string) ($appointment['appointment_time'] ?? ''), 0, 5),
                'appointment_type' => (string) ($appointment['appointment_type'] ?? ''),
                'service_name' => (string) ($appointment['service_name'] ?? ''),
                'status' => (string) ($appointment['status'] ?? ''),
                'allowed_transitions' => self::QUEUE_TRANSITIONS[(string) ($appointment['status'] ?? '')] ?? [],
            ];
        }, $rows);

        return $this->json(200, true, 'Today\'s appointments loaded.', ['items' => $items, 'pagination' => ['page' => $page, 'pages' => $pages, 'page_size' => $pageSize, 'total' => $total]]);
    }

    public function pastVisits(): ResponseInterface
    {
        if ($this->request->getMethod(true) !== 'GET') {
            return $this->json(405, false, 'Invalid request.');
        }
        $context = $this->context();
        $patientId = max(0, (int) $this->request->getGet('patient_id'));

        if ($context === null || $patientId <= 0) {
            return $this->json(422, false, 'A valid patient is required.');
        }

        $db = db_connect();
        $patientScope = $db->table('patients')
            ->select('patients.id')
            ->where('patients.id', $patientId)
            ->where('patients.clinic_id', $context['clinic_id'])
            ->groupStart()
            ->whereIn('patients.id', $db->table('appointments')->select('patient_id')->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id']))
            ->orWhereIn('patients.id', $db->table('consultations')->select('patient_id')->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id']))
            ->groupEnd()
            ->get()
            ->getRowArray();

        if ($patientScope === null) {
            return $this->json(404, false, 'Patient not found in the assigned clinic.');
        }

        $errors = [];
        $fromInput = trim((string) $this->request->getGet('date_from'));
        $toInput = trim((string) $this->request->getGet('date_to'));
        $dateFrom = $fromInput === '' ? null : $this->normalizeDisplayDate($fromInput);
        $dateTo = $toInput === '' ? null : $this->normalizeDisplayDate($toInput);

        if ($fromInput !== '' && $dateFrom === null) {
            $errors['date_from'] = 'Enter a valid start date as dd-mm-yyyy.';
        }

        if ($toInput !== '' && $dateTo === null) {
            $errors['date_to'] = 'Enter a valid end date as dd-mm-yyyy.';
        }

        if ($errors !== []) {
            return $this->validation($errors);
        }

        $pageSize = (int) $this->request->getGet('page_size');
        $pageSize = in_array($pageSize, [5, 10, 15, 25], true) ? $pageSize : 10;
        $page = max(1, (int) $this->request->getGet('page'));
        $query = $this->pastVisitsQuery($context, $patientId);
        $search = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));
        $appointmentType = trim((string) $this->request->getGet('appointment_type'));

        if ($search !== '') {
            $query->groupStart()
                ->like('consultations.consultation_number', $search)
                ->orLike('appointments.appointment_number', $search)
                ->orLike('appointments.service_name', $search)
                ->orLike('appointments.reason_for_visit', $search)
                ->groupEnd();
        }

        if ($dateFrom !== null) {
            $query->where('consultations.consultation_date >=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->where('consultations.consultation_date <=', $dateTo);
        }

        if ($status !== '') {
            $query->where('consultations.status', $status);
        }

        if ($appointmentType !== '') {
            $query->where('appointments.appointment_type', $appointmentType);
        }
        $total = $query->countAllResults(false);
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $rows = $query
            ->orderBy('consultations.consultation_date', 'DESC')
            ->orderBy('consultations.id', 'DESC')
            ->limit($pageSize, ($page - 1) * $pageSize)
            ->get()
            ->getResultArray();
        $items = array_map(static function (array $visit): array {
            return [
                'id' => (int) $visit['id'],
                'appointment_id' => (int) ($visit['appointment_id'] ?? 0),
                'date' => $visit['consultation_date'] !== null ? date('d-m-Y', strtotime((string) $visit['consultation_date'])) : '',
                'time' => substr((string) ($visit['appointment_time'] ?? $visit['consultation_time'] ?? ''), 0, 5),
                'queue_number' => (int) ($visit['queue_position'] ?? 0),
                'service_name' => (string) ($visit['service_name'] ?? ''),
                'appointment_type' => (string) ($visit['appointment_type'] ?? ''),
                'status' => (string) ($visit['status'] ?? ''),
                'appointment_number' => (string) ($visit['appointment_number'] ?? ''),
            ];
        }, $rows);
        $filterRows = $this->pastVisitsQuery($context, $patientId)
            ->select('consultations.status, appointments.appointment_type')
            ->get()
            ->getResultArray();
        $statuses = array_values(array_unique(array_filter(array_map(static fn (array $row): string => (string) ($row['status'] ?? ''), $filterRows))));
        $appointmentTypes = array_values(array_unique(array_filter(array_map(static fn (array $row): string => (string) ($row['appointment_type'] ?? ''), $filterRows))));

        return $this->json(200, true, 'Past visits loaded.', [
            'items' => $items,
            'statuses' => $statuses,
            'appointment_types' => $appointmentTypes,
            'filters' => ['q' => $search, 'date_from' => $fromInput, 'date_to' => $toInput, 'status' => $status, 'appointment_type' => $appointmentType],
            'pagination' => ['page' => $page, 'pages' => $pages, 'page_size' => $pageSize, 'total' => $total],
        ]);
    }

    public function pastVisit(int $consultationId): ResponseInterface
    {
        if ($this->request->getMethod(true) !== 'GET') {
            return $this->json(405, false, 'Invalid request.');
        }
        $context = $this->context();

        if ($context === null || $consultationId <= 0) {
            return $this->json(422, false, 'A valid visit is required.');
        }
        $visit = $this->pastVisitsQuery($context, 0)
            ->where('consultations.id', $consultationId)
            ->get()
            ->getRowArray();

        if ($visit === null) {
            return $this->json(404, false, 'Visit not found in the assigned clinic.');
        }
        $prescription = $this->prescriptions
            ->where('consultation_id', $consultationId)
            ->where('doctor_id', $context['doctor_id'])
            ->where('clinic_id', $context['clinic_id'])
            ->first();
        $items = $prescription === null ? [] : $this->prescriptionItems
            ->where('prescription_id', (int) $prescription['id'])
            ->orderBy('id', 'ASC')
            ->findAll();
        $this->audit('doctor.past_visit_viewed', $context, ['consultation_id' => $consultationId, 'patient_id' => (int) $visit['patient_id']]);

        return $this->json(200, true, 'Visit details loaded.', ['appointment' => [
            'date' => $visit['appointment_date'] !== null ? date('d-m-Y', strtotime((string) $visit['appointment_date'])) : '',
            'time' => substr((string) ($visit['appointment_time'] ?? ''), 0, 5),
            'queue_number' => (int) ($visit['queue_position'] ?? 0),
            'service_name' => (string) ($visit['service_name'] ?? ''),
            'appointment_type' => (string) ($visit['appointment_type'] ?? ''),
            'status' => (string) ($visit['appointment_status'] ?? ''),
            'doctor' => trim((string) ($visit['doctor_first_name'] ?? '') . ' ' . (string) ($visit['doctor_last_name'] ?? '')),
            'remarks' => (string) ($visit['reason_for_visit'] ?? ''),
        ], 'patient' => [
            'id' => (int) $visit['patient_id'],
            'name' => trim((string) ($visit['patient_first_name'] ?? '') . ' ' . (string) ($visit['patient_last_name'] ?? '')),
            'registration_number' => (string) ($visit['registration_number'] ?? ''),
        ], 'consultation' => [
            'chief_complaint' => (string) ($visit['chief_complaint'] ?? ''),
            'diagnosis' => (string) ($visit['diagnosis'] ?? ''),
            'advice' => (string) ($visit['treatment_plan'] ?? ''),
            'diagnostic_tests' => (string) ($visit['diagnostic_tests'] ?? ''),
            'diet_plan' => (string) ($visit['diet_plan'] ?? ''),
            'private_notes' => (string) ($visit['clinical_notes'] ?? ''),
            'follow_up_date' => $visit['follow_up_date'] !== null ? date('d-m-Y', strtotime((string) $visit['follow_up_date'])) : '',
            'follow_up_after_days' => $visit['follow_up_after_days'] ?? null,
            'follow_up_time' => substr((string) ($visit['follow_up_time'] ?? ''), 0, 5),
            'follow_up_type' => (string) ($visit['follow_up_type'] ?? ''),
            'follow_up_remarks' => (string) ($visit['follow_up_remarks'] ?? ''),
            'next_appointment_after_days' => $visit['next_appointment_after_days'] ?? null,
            'next_appointment_date' => $visit['next_appointment_date'] !== null ? date('d-m-Y', strtotime((string) $visit['next_appointment_date'])) : '',
            'next_appointment_time' => substr((string) ($visit['next_appointment_time'] ?? ''), 0, 5),
            'next_appointment_type' => (string) ($visit['next_appointment_type'] ?? ''),
            'status' => (string) ($visit['status'] ?? ''),
        ], 'prescription' => $prescription === null ? null : [
            'number' => (string) ($prescription['prescription_number'] ?? ''),
            'date' => $prescription['prescription_date'] !== null ? date('d-m-Y', strtotime((string) $prescription['prescription_date'])) : '',
            'status' => (string) ($prescription['status'] ?? ''),
            'notes' => (string) ($prescription['notes'] ?? ''),
            'items' => $items,
        ]]);
    }

    public function queue(int $appointmentId): ResponseInterface
    {
        if (!$this->isAjaxPost()) {
            return $this->json(405, false, 'Invalid request.');
        }
        $context = $this->context();
        $status = strtolower(trim((string) $this->request->getPost('status')));

        if ($context === null || !array_key_exists($status, self::QUEUE_TRANSITIONS)) {
            return $this->json(403, false, 'Doctor scope or status is invalid.');
        }
        $appointment = db_connect()->table('appointments')->where('id', $appointmentId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->get()->getRowArray();

        if ($appointment === null) {
            return $this->json(404, false, 'Appointment not found in the assigned clinic.');
        }

        if ($appointment['status'] === $status) {
            return $this->json(200, true, 'Appointment status was already recorded.', ['duplicate' => true]);
        }

        if (!in_array($status, self::QUEUE_TRANSITIONS[$appointment['status']] ?? [], true)) {
            return $this->json(409, false, 'This appointment status transition is not allowed.');
        }
        $data = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];

        if ($status === 'in_queue') {
            $data['queue_position'] = $this->nextQueuePosition($context['clinic_id'], $appointment['appointment_date']);
        } else {
            $data['queue_position'] = null;
        }

        if (!db_connect()->table('appointments')->where('id', $appointmentId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->update($data)) {
            return $this->json(500, false, 'Appointment status could not be updated safely.');
        }
        $this->audit('doctor.appointment_status', $context, ['appointment_id' => $appointmentId, 'status' => $status]);

        return $this->json(200, true, 'Appointment status updated.');
    }

    public function saveConsultation(): ResponseInterface
    {
        if (!$this->isAjaxPost()) {
            return $this->json(405, false, 'Invalid request.');
        }
        $context = $this->context();
        $appointmentId = (int) $this->request->getPost('appointment_id');
        $status = strtolower(trim((string) $this->request->getPost('status')));

        if ($context === null || $appointmentId <= 0 || !in_array($status, ['draft', 'in_progress', 'completed'], true)) {
            return $this->json(422, false, 'Appointment and consultation status are required.');
        }
        $errors = $this->textErrors(['chief_complaint' => 20000, 'clinical_notes' => 30000, 'diagnosis' => 20000, 'treatment_plan' => 20000, 'diagnostic_tests' => 20000, 'diet_plan' => 20000]);
        $dateInput = trim((string) $this->request->getPost('consultation_date'));
        $date = $dateInput === '' ? date('Y-m-d') : $this->normalizeDisplayDate($dateInput);

        if ($date === null) {
            $errors['consultation_date'] = 'Consultation date is invalid.';
        }
        $followUpInput = trim((string) $this->request->getPost('follow_up_date'));
        $followUpDate = $followUpInput === '' ? null : $this->normalizeDisplayDate($followUpInput);

        if ($followUpInput !== '' && $followUpDate === null) {
            $errors['follow_up_date'] = 'Enter a valid follow-up date as dd-mm-yyyy.';
        }

        $followUpAfterDays = $this->optionalDays('follow_up_after_days', $errors);
        $followUpTime = $this->normalizeTime('follow_up_time', $errors);
        [$followUpTypeId, $followUpType] = $this->optionalFollowUpType($context['clinic_id'], $errors);
        $nextAppointmentAfterDays = $this->optionalDays('next_appointment_after_days', $errors);
        $nextAppointmentDateInput = trim((string) $this->request->getPost('next_appointment_date'));
        $nextAppointmentDate = $nextAppointmentDateInput === '' ? null : $this->normalizeDisplayDate($nextAppointmentDateInput);

        if ($nextAppointmentDateInput !== '' && $nextAppointmentDate === null) {
            $errors['next_appointment_date'] = 'Enter a valid appointment date as dd-mm-yyyy.';
        }
        $nextAppointmentTime = $this->normalizeTime('next_appointment_time', $errors);
        $nextAppointmentType = $this->optionalText('next_appointment_type', 30, $errors);
        $errors = array_merge($errors, $this->textErrors(['follow_up_remarks' => 65535]));
        $appointment = db_connect()->table('appointments')->where('id', $appointmentId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->get()->getRowArray();

        if ($appointment === null) {
            $errors['appointment_id'] = 'Appointment is not in the assigned doctor scope.';
        }

        if ($appointment !== null && ! $this->appointmentConsultationEligibility->isConsultable($appointment)) {
            return $this->json(409, false, $this->appointmentConsultationEligibility->unavailableReason($appointment));
        }

        if ($errors !== []) {
            return $this->validation($errors);
        }
        $existing = $this->consultations->where('appointment_id', $appointmentId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->first();

        if ($existing !== null && $existing['status'] === 'completed') {
            return $this->json(409, false, 'Completed consultations cannot be modified.');
        }
        $data = ['patient_id' => (int) $appointment['patient_id'], 'doctor_id' => $context['doctor_id'], 'clinic_id' => $context['clinic_id'], 'appointment_id' => $appointmentId, 'consultation_date' => $date, 'consultation_time' => $this->nullable('consultation_time'), 'chief_complaint' => $this->nullable('chief_complaint'), 'clinical_notes' => $this->nullable('clinical_notes'), 'diagnosis' => $this->nullable('diagnosis'), 'treatment_plan' => $this->nullable('treatment_plan'), 'diagnostic_tests' => $this->nullable('diagnostic_tests'), 'diet_plan' => $this->nullable('diet_plan'), 'follow_up_date' => $followUpDate, 'follow_up_type_id' => $followUpTypeId, 'follow_up_type' => $followUpType, 'status' => $status, 'updated_at' => date('Y-m-d H:i:s')];

        try {
            $db = db_connect();
            $db->transBegin();

            if ($existing === null) {
                $data['consultation_number'] = 'TMP-CON-' . bin2hex(random_bytes(8));
                $data['created_at'] = date('Y-m-d H:i:s');
                $id = $this->consultations->insert($data);

                if (!$id || !$this->consultations->update($id, ['consultation_number' => sprintf('CON-%06d', $id)])) {
                    throw new \RuntimeException('Consultation insert failed.');
                }
            } else {
                $id = (int) $existing['id'];

                if (!$this->consultations->update($id, $data)) {
                    throw new \RuntimeException('Consultation update failed.');
                }
            }

            if ($status === 'completed' && ! in_array((string) $appointment['status'], ['cancelled', 'no_show'], true)) {
                if (!$db->table('appointments')->where('id', $appointmentId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->update(['status' => 'completed', 'queue_position' => null, 'updated_at' => date('Y-m-d H:i:s')])) {
                    throw new \RuntimeException('Appointment completion update failed.');
                }
            }

            if (!$db->transCommit()) {
                throw new \RuntimeException('Consultation transaction failed.');
            }
        } catch (Throwable $e) {
            db_connect()->transRollback();
            log_message('error', 'Doctor consultation save failed: ' . $e->getMessage());

            return $this->json(500, false, 'Consultation could not be saved safely.');
        }
        $this->audit('doctor.consultation_saved', $context, ['consultation_id' => $id, 'appointment_id' => $appointmentId, 'status' => $status]);

        return $this->json(200, true, 'Consultation saved.', ['consultation_id' => $id]);
    }

    public function savePrescription(): ResponseInterface
    {
        if (!$this->isAjaxPost()) {
            return $this->json(405, false, 'Invalid request.');
        }
        $context = $this->context();
        $consultationId = (int) $this->request->getPost('consultation_id');
        $status = strtolower(trim((string) $this->request->getPost('status')));

        if ($context === null || $consultationId <= 0 || $this->prescriptionOptions->activeByCode(PrescriptionOptionModel::TYPES['status'], $status, $context['clinic_id']) === null) {
            return $this->json(422, false, 'Consultation and prescription status are required.');
        }
        $consultation = $this->consultations->where('id', $consultationId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->first();

        if ($consultation === null) {
            return $this->json(404, false, 'Consultation not found in the assigned clinic.');
        }

        if ($status === 'submitted' && $consultation['status'] !== 'completed') {
            return $this->json(409, false, 'Consultation must be completed before prescription submission.');
        }
        $errors = [];
        $items = $this->normalizePrescriptionItems($this->request->getPost('items'), $errors, $context['clinic_id']);

        if ($errors !== []) {
            return $this->validation($errors);
        }
        $existing = $this->prescriptions->where('consultation_id', $consultationId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->first();

        if ($existing !== null && $existing['status'] === 'approved') {
            return $this->json(409, false, 'Approved prescriptions cannot be modified.');
        }

        try {
            $db = db_connect();
            $db->transBegin();
            $now = date('Y-m-d H:i:s');
            $data = ['consultation_id' => $consultationId, 'patient_id' => (int) $consultation['patient_id'], 'doctor_id' => $context['doctor_id'], 'clinic_id' => $context['clinic_id'], 'prescription_date' => trim((string) $this->request->getPost('prescription_date')) ?: date('Y-m-d'), 'notes' => $this->nullable('notes'), 'status' => $status, 'updated_at' => $now];

            if ($existing === null) {
                $data['prescription_number'] = 'TMP-RX-' . bin2hex(random_bytes(8));
                $data['created_at'] = $now;
                $id = $this->prescriptions->insert($data);

                if (!$id || !$this->prescriptions->update($id, ['prescription_number' => sprintf('RXN-%06d', $id)])) {
                    throw new \RuntimeException('Prescription insert failed.');
                }
            } else {
                $id = (int) $existing['id'];

                if (!$this->prescriptions->update($id, $data)) {
                    throw new \RuntimeException('Prescription update failed.');
                }
                $this->prescriptionItems->where('prescription_id', $id)->delete();
            }

            foreach ($items as &$item) {
                $item['prescription_id'] = $id;
                $item['created_at'] = $now;
                $item['updated_at'] = $now;
            }
            unset($item);

            if ($this->prescriptionItems->insertBatch($items) === false || !$db->transCommit()) {
                throw new \RuntimeException('Prescription item transaction failed.');
            }
        } catch (Throwable $e) {
            db_connect()->transRollback();
            log_message('error', 'Doctor prescription save failed: ' . $e->getMessage());

            return $this->json(500, false, 'Prescription could not be saved safely.');
        }
        $this->audit('doctor.prescription_saved', $context, ['prescription_id' => $id, 'consultation_id' => $consultationId, 'status' => $status]);

        return $this->json(200, true, $status === 'submitted' ? 'Prescription submitted for review.' : 'Prescription draft saved.', ['prescription_id' => $id]);
    }

    public function saveWorkspace(): ResponseInterface
    {
        if (! $this->isAjaxPost()) {
            return $this->json(405, false, 'Invalid request.');
        }

        $context = $this->context();

        if ($context === null) {
            return $this->json(403, false, 'Doctor scope is invalid.');
        }

        $appointmentId = (int) $this->request->getPost('appointment_id');
        $consultationStatus = strtolower(trim((string) $this->request->getPost('consultation_status')));
        $postedPrescriptionItems = $this->request->getPost('items');
        $hasPrescriptionRows = $this->hasNonEmptyPrescriptionRows($postedPrescriptionItems);
        $medicineDurationInput = trim((string) $this->request->getPost('medicine_duration'));
        $prescriptionStatus = strtolower(trim((string) $this->request->getPost('prescription_status')));
        $errors = [];
        $medicineDuration = $this->normalizeMedicineDuration($medicineDurationInput, $errors);

        if ($appointmentId <= 0) {
            $errors['appointment_id'] = 'Select an appointment before saving the consultation.';
        }

        if (! in_array($consultationStatus, ['draft', 'in_progress', 'completed'], true)) {
            $errors['consultation_status'] = 'Choose a valid consultation status.';
        }

        if ($hasPrescriptionRows && $this->prescriptionOptions->activeByCode(PrescriptionOptionModel::TYPES['status'], $prescriptionStatus, $context['clinic_id']) === null) {
            $errors['prescription_status'] = 'Choose a valid prescription status.';
        }

        if ($hasPrescriptionRows && ! in_array('prescription.create', session('permissions') ?? [], true)) {
            return $this->json(403, false, 'You do not have permission to save prescriptions.');
        }

        $errors = array_merge($errors, $this->textErrors([
            'chief_complaint' => 20000,
            'clinical_notes' => 30000,
            'diagnosis' => 20000,
            'treatment_plan' => 20000,
            'diagnostic_tests' => 20000,
            'diet_plan' => 20000,
        ]));

        $dateInput = trim((string) $this->request->getPost('consultation_date'));
        $consultationDate = $dateInput === '' ? date('Y-m-d') : $this->normalizeDisplayDate($dateInput);

        if ($consultationDate === null) {
            $errors['consultation_date'] = 'Consultation date is invalid.';
        }

        $followUpInput = trim((string) $this->request->getPost('follow_up_date'));
        $followUpDate = $followUpInput === '' ? null : $this->normalizeDisplayDate($followUpInput);

        if ($followUpInput !== '' && $followUpDate === null) {
            $errors['follow_up_date'] = 'Enter a valid follow-up date as dd-mm-yyyy.';
        }

        $followUpAfterDays = $this->optionalDays('follow_up_after_days', $errors);
        $followUpTime = $this->normalizeTime('follow_up_time', $errors);
        [$followUpTypeId, $followUpType] = $this->optionalFollowUpType($context['clinic_id'], $errors);
        $nextAppointmentAfterDays = $this->optionalDays('next_appointment_after_days', $errors);
        $nextAppointmentDateInput = trim((string) $this->request->getPost('next_appointment_date'));
        $nextAppointmentDate = $nextAppointmentDateInput === '' ? null : $this->normalizeDisplayDate($nextAppointmentDateInput);

        if ($nextAppointmentDateInput !== '' && $nextAppointmentDate === null) {
            $errors['next_appointment_date'] = 'Enter a valid appointment date as dd-mm-yyyy.';
        }
        $nextAppointmentTime = $this->normalizeTime('next_appointment_time', $errors);
        $nextAppointmentType = $this->optionalText('next_appointment_type', 30, $errors);
        $errors = array_merge($errors, $this->textErrors(['follow_up_remarks' => 65535]));

        $db = db_connect();
        $appointment = $appointmentId > 0
            ? $db->table('appointments')
                ->where('id', $appointmentId)
                ->where('doctor_id', $context['doctor_id'])
                ->where('clinic_id', $context['clinic_id'])
                ->get()
                ->getRowArray()
            : null;

        if ($appointmentId > 0 && $appointment === null) {
            $errors['appointment_id'] = 'Appointment is not in the assigned doctor scope.';
        }

        if ($appointment !== null && ! $this->appointmentConsultationEligibility->isConsultable($appointment)) {
            return $this->json(409, false, $this->appointmentConsultationEligibility->unavailableReason($appointment));
        }

        $existingConsultation = null;

        if ($appointment !== null) {
            $existingConsultation = $this->consultations
                ->where('appointment_id', $appointmentId)
                ->where('doctor_id', $context['doctor_id'])
                ->where('clinic_id', $context['clinic_id'])
                ->first();

            if ($existingConsultation !== null && $existingConsultation['status'] === 'completed') {
                return $this->json(409, false, 'Completed consultations cannot be modified.');
            }
        }

        $items = [];
        $existingPrescription = null;

        if ($hasPrescriptionRows) {
            $items = $this->normalizePrescriptionItems($postedPrescriptionItems, $errors, $context['clinic_id']);

            if ($existingConsultation !== null) {
                $existingPrescription = $this->prescriptions
                    ->where('consultation_id', (int) $existingConsultation['id'])
                    ->where('doctor_id', $context['doctor_id'])
                    ->where('clinic_id', $context['clinic_id'])
                    ->first();

                if ($existingPrescription !== null && $existingPrescription['status'] === 'approved') {
                    return $this->json(409, false, 'Approved prescriptions cannot be modified.');
                }
            }

            if ($prescriptionStatus === 'submitted' && $consultationStatus !== 'completed') {
                $errors['prescription_status'] = 'Complete the consultation before submitting the prescription.';
            }
        }
        $this->validatePrescriptionRequirements($hasPrescriptionRows, $medicineDurationInput, $medicineDuration, $errors);
        $prescriptionPresent = $hasPrescriptionRows;

        if ($errors !== []) {
            return $this->validation($errors);
        }

        $consultationId = null;
        $prescriptionId = null;

        try {
            $db->transBegin();
            $now = date('Y-m-d H:i:s');
            $consultationData = [
                'patient_id' => (int) $appointment['patient_id'],
                'doctor_id' => $context['doctor_id'],
                'clinic_id' => $context['clinic_id'],
                'appointment_id' => $appointmentId,
                'consultation_date' => $consultationDate,
                'consultation_time' => $this->nullable('consultation_time'),
                'chief_complaint' => $this->nullable('chief_complaint'),
                'clinical_notes' => $this->nullable('clinical_notes'),
                'diagnosis' => $this->nullable('diagnosis'),
                'treatment_plan' => $this->nullable('treatment_plan'),
                'diagnostic_tests' => $this->nullable('diagnostic_tests'),
                'diet_plan' => $this->nullable('diet_plan'),
                'follow_up_date' => $followUpDate,
                'follow_up_after_days' => $followUpAfterDays,
                'follow_up_time' => $followUpTime,
                'follow_up_type_id' => $followUpTypeId,
                'follow_up_type' => $followUpType,
                'follow_up_remarks' => $this->nullable('follow_up_remarks'),
                'next_appointment_after_days' => $nextAppointmentAfterDays,
                'next_appointment_date' => $nextAppointmentDate,
                'next_appointment_time' => $nextAppointmentTime,
                'next_appointment_type' => $nextAppointmentType,
                'status' => $consultationStatus,
                'updated_at' => $now,
            ];

            if ($existingConsultation === null) {
                $consultationData['consultation_number'] = 'TMP-CON-' . bin2hex(random_bytes(8));
                $consultationData['created_at'] = $now;
                $consultationId = $this->consultations->insert($consultationData);

                if (! $consultationId || ! $this->consultations->update($consultationId, ['consultation_number' => sprintf('CON-%06d', $consultationId)])) {
                    throw new \RuntimeException('Consultation insert failed.');
                }
            } else {
                $consultationId = (int) $existingConsultation['id'];

                if (! $this->consultations->update($consultationId, $consultationData)) {
                    throw new \RuntimeException('Consultation update failed.');
                }
            }

            if ($consultationStatus === 'completed' && ! in_array((string) $appointment['status'], ['cancelled', 'no_show'], true)) {
                if (! $db->table('appointments')->where('id', $appointmentId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->update(['status' => 'completed', 'queue_position' => null, 'updated_at' => $now])) {
                    throw new \RuntimeException('Appointment completion update failed.');
                }
            }

            if ($prescriptionPresent) {
                $prescriptionData = [
                    'consultation_id' => $consultationId,
                    'patient_id' => (int) $appointment['patient_id'],
                    'doctor_id' => $context['doctor_id'],
                    'clinic_id' => $context['clinic_id'],
                    'prescription_date' => date('Y-m-d'),
                    'notes' => $this->nullable('notes'),
                    'status' => $prescriptionStatus,
                    'updated_at' => $now,
                ];

                if ($existingPrescription === null) {
                    $prescriptionData['prescription_number'] = 'TMP-RX-' . bin2hex(random_bytes(8));
                    $prescriptionData['created_at'] = $now;
                    $prescriptionId = $this->prescriptions->insert($prescriptionData);

                    if (! $prescriptionId || ! $this->prescriptions->update($prescriptionId, ['prescription_number' => sprintf('RXN-%06d', $prescriptionId)])) {
                        throw new \RuntimeException('Prescription insert failed.');
                    }
                } else {
                    $prescriptionId = (int) $existingPrescription['id'];

                    if (! $this->prescriptions->update($prescriptionId, $prescriptionData)) {
                        throw new \RuntimeException('Prescription update failed.');
                    }
                    $this->prescriptionItems->where('prescription_id', $prescriptionId)->delete();
                }

                foreach ($items as &$item) {
                    $item['prescription_id'] = $prescriptionId;
                    $item['created_at'] = $now;
                    $item['updated_at'] = $now;
                }
                unset($item);

                if ($this->prescriptionItems->insertBatch($items) === false) {
                    throw new \RuntimeException('Prescription item transaction failed.');
                }
            }

            if (! $db->transCommit()) {
                throw new \RuntimeException('Clinical workspace transaction failed.');
            }
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Doctor clinical workspace save failed: ' . $e->getMessage());

            return $this->json(500, false, 'Consultation could not be saved safely.');
        }

        $auditData = ['appointment_id' => $appointmentId, 'consultation_id' => $consultationId, 'status' => $consultationStatus];

        if ($prescriptionId !== null) {
            $auditData['prescription_id'] = $prescriptionId;
            $auditData['prescription_status'] = $prescriptionStatus;
        }
        $this->audit('doctor.consultation_workspace_saved', $context, $auditData);

        return $this->json(200, true, 'Consultation saved.', ['consultation_id' => $consultationId, 'prescription_id' => $prescriptionId]);
    }

    private function context(): ?array
    {
        if (!in_array('Doctor', session('roles') ?? [], true)) {
            return null;
        }
        $userId = (int) ((session('user') ?? [])['id'] ?? 0);

        if ($userId <= 0) {
            return null;
        }
        $user = (new User())->select('doctor_id')->where('id', $userId)->where('status', 1)->first();
        $doctorId = (int) ($user['doctor_id'] ?? 0);

        if ($doctorId <= 0) {
            return null;
        }
        $doctor = db_connect()->table('doctors')->select('id, clinic_id, first_name, last_name, doctor_code')->where('id', $doctorId)->where('status', 1)->get()->getRowArray();

        return $doctor !== null && (int) $doctor['clinic_id'] > 0 ? ['doctor_id' => $doctorId, 'clinic_id' => (int) $doctor['clinic_id'], 'doctor' => $doctor] : null;
    }

    private function todayAppointmentQuery(array $context, string $today)
    {
        return db_connect()->table('appointments')->select('appointments.*, patients.registration_number, patients.first_name AS patient_first_name, patients.middle_name AS patient_middle_name, patients.last_name AS patient_last_name, patients.date_of_birth AS patient_date_of_birth, patients.address AS patient_address, patients.postal_code AS patient_postal_code, patients.mobile_number AS patient_mobile_number, patients.mobile_normalized AS patient_mobile_normalized, patients.source_type AS patient_source_type, patients.country_code AS patient_legacy_country_code, telephone_country_codes.normalized_code AS patient_telephone_country_code, countries.name AS patient_country_name, states.name AS patient_state_name, cities.name AS patient_city_name, localities.name AS patient_locality_name, consultations.id AS consultation_id, consultations.consultation_number, consultations.status AS consultation_status, consultations.consultation_date, consultations.consultation_time, consultations.chief_complaint, consultations.clinical_notes, consultations.diagnosis, consultations.treatment_plan, consultations.diagnostic_tests, consultations.diet_plan, consultations.follow_up_date, consultations.follow_up_after_days, consultations.follow_up_time, consultations.follow_up_type_id, consultations.follow_up_type, follow_up_types.name AS follow_up_type_name, consultations.follow_up_remarks, consultations.next_appointment_after_days, consultations.next_appointment_date, consultations.next_appointment_time, consultations.next_appointment_type, prescriptions.id AS prescription_id')->join('patients', 'patients.id = appointments.patient_id')->join('telephone_country_codes', 'telephone_country_codes.id = patients.telephone_country_code_id', 'left')->join('countries', 'countries.id = patients.country_id', 'left')->join('states', 'states.id = patients.state_id', 'left')->join('cities', 'cities.id = patients.city_id', 'left')->join('localities', 'localities.id = patients.locality_id', 'left')->join('consultations', 'consultations.appointment_id = appointments.id', 'left')->join('follow_up_types', 'follow_up_types.id = consultations.follow_up_type_id', 'left')->join('prescriptions', 'prescriptions.consultation_id = consultations.id', 'left')->where('appointments.doctor_id', $context['doctor_id'])->where('appointments.clinic_id', $context['clinic_id'])->where('patients.clinic_id', $context['clinic_id'])->where('appointments.appointment_date', $today);
    }

    private function pastVisitsQuery(array $context, int $patientId)
    {
        $query = db_connect()->table('consultations')
            ->select('consultations.*, appointments.appointment_number, appointments.appointment_date, appointments.appointment_time, appointments.queue_position, appointments.appointment_type, appointments.service_name, appointments.reason_for_visit, appointments.status AS appointment_status, patients.registration_number, patients.first_name AS patient_first_name, patients.last_name AS patient_last_name, doctors.first_name AS doctor_first_name, doctors.last_name AS doctor_last_name')
            ->join('appointments', 'appointments.id = consultations.appointment_id')
            ->join('patients', 'patients.id = consultations.patient_id')
            ->join('doctors', 'doctors.id = consultations.doctor_id')
            ->where('consultations.doctor_id', $context['doctor_id'])
            ->where('consultations.clinic_id', $context['clinic_id'])
            ->where('patients.clinic_id', $context['clinic_id']);

        if ($patientId > 0) {
            $query->where('consultations.patient_id', $patientId);
        }

        return $query;
    }

    private function nextQueuePosition(int $clinicId, string $date): int
    {
        $row = db_connect()->query("SELECT COALESCE(MAX(queue_position), 0) AS last_position FROM appointments WHERE clinic_id = ? AND appointment_date = ? AND status IN ('checked_in','in_queue')", [$clinicId, $date])->getRowArray();

        return (int) ($row['last_position'] ?? 0) + 1;
    }

    private function isAjaxPost(): bool
    {
        return $this->request->isAJAX() && $this->request->getMethod(true) === 'POST';
    }

    private function nullable(string $field): ?string
    {
        $value = trim((string) $this->request->getPost($field));

        return $value === '' ? null : $value;
    }

    private function optionalDays(string $field, array &$errors): ?int
    {
        $value = trim((string) $this->request->getPost($field));

        if ($value === '') {
            return null;
        }

        if (! ctype_digit($value) || (int) $value > 65535) {
            $errors[$field] = 'Enter a valid number of days.';

            return null;
        }

        return (int) $value;
    }

    private function optionalFollowUpType(int $clinicId, array &$errors): array
    {
        $value = trim((string) $this->request->getPost('follow_up_type_id'));

        if ($value === '') {
            return [null, null];
        }

        if (! ctype_digit($value) || (int) $value <= 0) {
            $errors['follow_up_type_id'] = 'Select an active follow-up type.';

            return [null, null];
        }
        $type = $this->followUpTypes->activeForClinicById((int) $value, $clinicId);

        if ($type === null) {
            $errors['follow_up_type_id'] = 'Select an active follow-up type.';

            return [null, null];
        }

        return [(int) $type['id'], (string) $type['name']];
    }

    private function normalizeTime(string $field, array &$errors): ?string
    {
        $value = trim((string) $this->request->getPost($field));

        if ($value === '') {
            return null;
        }
        $time = \DateTimeImmutable::createFromFormat('!H:i', $value);

        if ($time === false || $time->format('H:i') !== $value) {
            $errors[$field] = 'Enter a valid time.';

            return null;
        }

        return $time->format('H:i:s');
    }

    private function optionalText(string $field, int $maxLength, array &$errors): ?string
    {
        $value = trim((string) $this->request->getPost($field));

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $maxLength) {
            $errors[$field] = "This field cannot exceed {$maxLength} characters.";

            return null;
        }

        return $value;
    }

    private function item(array $item, string $field, int $max): ?string
    {
        $value = trim((string) ($item[$field] ?? ''));

        return $value === '' ? null : substr($value, 0, $max);
    }

    private function hasNonEmptyPrescriptionRows(mixed $posted): bool
    {
        if (! is_array($posted)) {
            return is_scalar($posted) && trim((string) $posted) !== '';
        }

        foreach ($posted as $item) {
            if (! is_array($item)) {
                return true;
            }

            if ($this->isNonEmptyPrescriptionItem($item)) {
                return true;
            }
        }

        return false;
    }

    private function isNonEmptyPrescriptionItem(array $item): bool
    {
        // The initial UI row may carry the shared default intake option. It is
        // still a placeholder until another prescription value is entered.
        foreach (['medicine_id', 'medicine_name', 'duration', 'frequency', 'dosage', 'notes', 'quantity'] as $field) {
            if (trim((string) ($item[$field] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function normalizeMedicineDuration(string $value, array &$errors): ?int
    {
        if ($value === '') {
            return null;
        }

        if (! ctype_digit($value) || (int) $value < 1 || (int) $value > 3650) {
            $errors['medicine_duration'] = 'Medicine duration must be a positive whole number.';

            return null;
        }

        return (int) $value;
    }

    private function validatePrescriptionRequirements(bool $hasRows, string $durationInput, ?int $duration, array &$errors): void
    {
        if ($hasRows && $duration === null && ! array_key_exists('medicine_duration', $errors)) {
            $errors['medicine_duration'] = 'Medicine duration is required when medicines are added.';
        }

        if (! $hasRows && $durationInput !== '') {
            $errors['items'] = 'Add at least one medicine or clear the duration.';
        }
    }

    private function normalizePrescriptionItems(mixed $posted, array &$errors, int $clinicId): array
    {
        $items = [];

        if (! is_array($posted) || $posted === []) {
            $errors['items'] = 'At least one prescription item is required.';

            return $items;
        }

        foreach ($posted as $index => $item) {
            if (! is_array($item)) {
                $errors['items'] = 'Prescription items are invalid.';

                continue;
            }

            if (! $this->isNonEmptyPrescriptionItem($item)) {
                continue;
            }
            $medicineIdValue = trim((string) ($item['medicine_id'] ?? ''));
            $legacyMedicineName = trim((string) ($item['medicine_name'] ?? ''));
            $medicine = null;
            $medicineId = null;

            if ($medicineIdValue !== '') {
                if (ctype_digit($medicineIdValue) && (int) $medicineIdValue > 0) {
                    $medicineId = (int) $medicineIdValue;
                    $medicine = (new MedicineModel())->where('id', $medicineId)->where('status', 'active')->first();
                }
            } elseif ($legacyMedicineName !== '') {
                // Compatibility for older clients and historical drafts. New
                // Doctor forms submit medicine_id only.
                $medicine = ctype_digit($legacyMedicineName)
                    ? (new MedicineModel())->where('id', (int) $legacyMedicineName)->where('status', 'active')->first()
                    : (new MedicineModel())->where('medicine_name', $legacyMedicineName)->where('status', 'active')->first();
                $medicineId = $medicine === null ? null : (int) $medicine['id'];
            }

            if ($medicine === null) {
                $errors["items.{$index}.medicine_id"] = 'Medicine is required and must be valid.';
            }
            $duration = $this->prescriptionOption($item['duration'] ?? '', PrescriptionOptionModel::TYPES['duration'], $clinicId);

            if ($duration === null && trim((string) ($item['duration'] ?? '')) !== '') {
                $errors["items.{$index}.duration"] = 'Choose a valid medicine duration.';
            }
            $frequency = $this->prescriptionOption($item['frequency'] ?? '', PrescriptionOptionModel::TYPES['frequency'], $clinicId);

            if ($frequency === null && trim((string) ($item['frequency'] ?? '')) !== '') {
                $errors["items.{$index}.frequency"] = 'Choose a valid medicine frequency.';
            }
            $dose = $this->prescriptionOption($item['dosage'] ?? '', PrescriptionOptionModel::TYPES['dose'], $clinicId);

            if ($dose === null && trim((string) ($item['dosage'] ?? '')) !== '') {
                $errors["items.{$index}.dosage"] = 'Choose a valid medicine dose.';
            }
            $intake = $this->prescriptionOption($item['intake'] ?? '', PrescriptionOptionModel::TYPES['intake'], $clinicId);

            if ($intake === null && trim((string) ($item['intake'] ?? '')) !== '') {
                $errors["items.{$index}.intake"] = 'Choose a valid medicine intake instruction.';
            }
            $quantity = trim((string) ($item['quantity'] ?? ''));

            if ($quantity !== '' && (! ctype_digit($quantity) || (int) $quantity <= 0)) {
                $errors["items.{$index}.quantity"] = 'Quantity must be a positive whole number.';
            }
            $items[] = [
                'medicine_id' => $medicineId,
                'medicine_name' => $medicine['medicine_name'] ?? $legacyMedicineName,
                'dosage' => $dose['name'] ?? null,
                'frequency' => $frequency['name'] ?? null,
                'duration' => $duration === null ? null : (int) $duration['code'],
                'duration_unit' => 'days',
                'instructions' => $intake['name'] ?? null,
                'quantity' => $quantity === '' ? null : (int) $quantity,
                'notes' => $this->item($item, 'notes', 65535),
            ];
        }

        return $items;
    }

    private function prescriptionOption(mixed $value, string $type, int $clinicId): ?array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            $option = $this->prescriptionOptions->activeById($type, (int) $value, $clinicId);

            if ($option !== null) {
                return $option;
            }
        }

        foreach ($this->prescriptionOptions->activeOptions($type, $clinicId) as $option) {
            if ((string) $option['code'] === $value || (string) $option['name'] === $value) {
                return $option;
            }
        }

        return null;
    }

    private function prescriptionOptionSets(int $clinicId): array
    {
        return [
            'duration' => $this->prescriptionOptions->activeOptions(PrescriptionOptionModel::TYPES['duration'], $clinicId),
            'frequency' => $this->prescriptionOptions->activeOptions(PrescriptionOptionModel::TYPES['frequency'], $clinicId),
            'dose' => $this->prescriptionOptions->activeOptions(PrescriptionOptionModel::TYPES['dose'], $clinicId),
            'intake' => $this->prescriptionOptions->activeOptions(PrescriptionOptionModel::TYPES['intake'], $clinicId),
            'status' => $this->prescriptionOptions->activeOptions(PrescriptionOptionModel::TYPES['status'], $clinicId),
        ];
    }

    private function prescriptionStatusDefault(array $optionSets): string
    {
        foreach ($optionSets['status'] ?? [] as $option) {
            if ((string) $option['code'] === 'draft') {
                return 'draft';
            }
        }

        return (string) ($optionSets['status'][0]['code'] ?? '');
    }

    private function attachMedicineIds(array $items): array
    {
        $model = new MedicineModel();

        foreach ($items as &$item) {
            $medicineId = (int) ($item['medicine_id'] ?? 0);

            if ($medicineId > 0) {
                continue;
            }
            $medicine = $model->select('id')->where('medicine_name', $item['medicine_name'] ?? '')->where('status', 'active')->first();
            $item['medicine_id'] = (int) ($medicine['id'] ?? 0);
        }
        unset($item);

        return $items;
    }

    private function textErrors(array $fields): array
    {
        $errors = [];

        foreach ($fields as $field => $max) {
            if (strlen((string) $this->request->getPost($field)) > $max) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is too long.';
            }
        }

        return $errors;
    }

    private function normalizeDisplayDate(string $value): ?string
    {
        $value = trim($value);
        $date = \DateTimeImmutable::createFromFormat('!d-m-Y', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if ($value === '' || $date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('d-m-Y') !== $value) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private function validation(array $errors): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => (string) reset($errors), 'errors' => $errors]);
    }

    private function json(int $code, bool $status, string $message, array $data = []): ResponseInterface
    {
        $payload = ['status' => $status, 'msg' => $message];

        if ($data !== []) {
            $payload['data'] = $data;
        }

        return $this->response->setStatusCode($code)->setJSON($payload);
    }

    private function audit(string $action, array $context, array $data): void
    {
        service('audit')->record($action, array_merge(['doctor_id' => $context['doctor_id'], 'clinic_id' => $context['clinic_id']], $data));
    }
}
