<?php

namespace Doctor\Controllers;

use Admin\Models\Doctor\DoctorModel;
use Admin\Models\Configuration\ServiceModel;
use Admin\Models\PrescriptionOptionModel;
use Admin\Models\User;
use App\Controllers\BaseController;
use App\Services\AppointmentConsultationEligibility;
use App\Services\Shopify\ShopifyConfiguration;
use App\Services\Common\PrescriptionPdfService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Doctor\Models\FollowUpStatusModel;
use Doctor\Models\FollowUpTypeModel;
use Throwable;

class Records extends BaseController
{
    public function createAppointment(): ResponseInterface
    {
        if (!$this->isAjaxPost()) {
            return $this->json(405, false, 'Invalid request.');
        }
        $context = $this->context();
        $patientId = (int) $this->request->getPost('patient_id');
        $dateInput = trim((string) $this->request->getPost('appointment_date'));
        $time = rootins_parse_time($this->request->getPost('appointment_time')) ?? '';
        $type = strtolower(trim((string) $this->request->getPost('appointment_type')));
        $service = trim((string) $this->request->getPost('service_name'));
        $status = strtolower(trim((string) ($this->request->getPost('appointment_status') ?? $this->request->getPost('status'))));
        $reason = trim((string) $this->request->getPost('reason_for_visit'));
        $errors = [];

        if ($context === null) {
            return $this->json(403, false, 'Doctor scope is invalid.');
        }
        $patientQuery = db_connect()->table('patients')->where('id', $patientId);
        $this->applyDoctorPatientScope($patientQuery, $context);

        if ($patientId <= 0 || $patientQuery->get()->getRowArray() === null) {
            $errors['patient_id'] = 'Select a patient in your clinic scope.';
        }
        $date = $this->normalizeDisplayDate($dateInput);

        if ($date === null) {
            $errors['appointment_date'] = 'A valid appointment date is required.';
        }

        if ($time === '') {
            $errors['appointment_time'] = 'A valid appointment time is required.';
        }

        if (!in_array($type, ['in_person', 'video', 'phone'], true)) {
            $errors['appointment_type'] = 'Select a supported consultation type.';
        }

        if ($service === '' || mb_strlen($service) > 150) {
            $errors['service_name'] = 'Select a valid service.';
        }

        if (!in_array($status, ['scheduled', 'confirmed'], true)) {
            $errors['appointment_status'] = 'Select a valid appointment status.';
        }

        if (mb_strlen($reason) > 1000) {
            $errors['reason_for_visit'] = 'Reason for visit must be 1000 characters or fewer.';
        }
        [$serviceIds, $serviceNames, $serviceError] = $this->resolveServices($context);

        if ($serviceError !== null) {
            $errors['service_ids[]'] = $serviceError;
        }

        if ($errors !== []) {
            return $this->validation($errors);
        }
        $db = db_connect();

        if (($availability = $this->availabilityError($context['doctor_id'], $date, $time)) !== null) {
            return $this->validation(['appointment_date' => $availability]);
        }
        $databaseTime = strlen($time) === 5 ? $time . ':00' : $time;
        $now = date('Y-m-d H:i:s');
        $db->transBegin();

        try {
            $conflict = $db->table('appointments')
                ->where('doctor_id', $context['doctor_id'])
                ->where('clinic_id', $context['clinic_id'])
                ->where('appointment_date', $date)
                ->where('appointment_time', $databaseTime)
                ->whereIn('status', ['scheduled', 'confirmed', 'checked_in', 'in_queue'])
                ->countAllResults();

            if ($conflict > 0) {
                $db->transRollback();

                return $this->validation(['appointment_time' => 'This time is already booked for you.']);
            }
            $inserted = $db->table('appointments')->insert(['appointment_number' => 'TMP-' . bin2hex(random_bytes(8)), 'patient_id' => $patientId, 'doctor_id' => $context['doctor_id'], 'clinic_id' => $context['clinic_id'], 'appointment_date' => $date, 'appointment_time' => $databaseTime, 'duration_minutes' => 15, 'appointment_type' => $type, 'service_name' => $service, 'status' => $status, 'created_by_type' => 'user', 'created_by_id' => (int) (session('user')['id'] ?? 0), 'booking_source' => 'doctor', 'reason_for_visit' => $reason !== '' ? $reason : null, 'created_at' => $now, 'updated_at' => $now]);

            if (!$inserted) {
                throw new \RuntimeException('Appointment insert failed.');
            }
            $id = (int) $db->insertID();

            if ($id < 1) {
                throw new \RuntimeException('Appointment insert failed.');
            }

            if (!$db->table('appointments')->where('id', $id)->update(['appointment_number' => sprintf('APT-%06d', $id), 'updated_at' => date('Y-m-d H:i:s')])) {
                throw new \RuntimeException('Appointment number update failed.');
            }
            $serviceRows = array_map(static fn (int $serviceId, string $serviceName): array => [
                'appointment_id' => $id,
                'service_id' => $serviceId,
                'service_name' => $serviceName,
                'created_at' => $now,
            ], $serviceIds, $serviceNames);

            if ($serviceRows === [] || !$db->table('appointment_services')->insertBatch($serviceRows)) {
                throw new \RuntimeException('Appointment service relation failed.');
            }

            if (!$db->transCommit()) {
                throw new \RuntimeException('Appointment transaction failed.');
            }
            service('audit')->record('doctor.appointment_created', ['appointment_id' => $id, 'patient_id' => $patientId, 'doctor_id' => $context['doctor_id'], 'clinic_id' => $context['clinic_id']]);

            return $this->json(200, true, 'Appointment created successfully.', ['appointment_id' => $id]);
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Doctor appointment creation failed: ' . $e->getMessage());

            return $this->json(500, false, 'Appointment could not be created safely.');
        }
    }

    public function reschedule(int $appointmentId): ResponseInterface
    {
        return $this->updateAppointment($appointmentId, false);
    }

    public function availableSlots(): ResponseInterface
    {
        if ($this->request->getMethod(true) !== 'GET') {
            return $this->json(405, false, 'Invalid request.');
        }

        $context = $this->context();
        $date = $this->normalizeDisplayDate(trim((string) $this->request->getGet('appointment_date')));

        if ($context === null) {
            return $this->json(403, false, 'Doctor scope is invalid.');
        }

        if ($date === null) {
            return $this->json(422, false, 'A valid appointment date is required.', ['slots' => []]);
        }

        $db = db_connect();
        $day = (int) date('N', strtotime($date));
        $today = date('Y-m-d');
        $now = date('H:i:s');
        $leave = (new \Admin\Models\Doctor\DoctorLeaveModel())
            ->where('doctor_id', $context['doctor_id'])
            ->where('status', 1)
            ->where('start_date <=', $date)
            ->where('end_date >=', $date)
            ->countAllResults() > 0;
        $schedules = $db->table('doctor_schedules')
            ->where('doctor_id', $context['doctor_id'])
            ->where('day_of_week', $day)
            ->where('status', 1)
            ->orderBy('start_time', 'ASC')
            ->get()->getResultArray();
        $booked = array_fill_keys(array_map(static fn (array $row): string => substr((string) $row['appointment_time'], 0, 5), $db->table('appointments')
            ->select('appointment_time')
            ->where('doctor_id', $context['doctor_id'])
            ->where('clinic_id', $context['clinic_id'])
            ->where('appointment_date', $date)
            ->whereIn('status', ['scheduled', 'confirmed', 'checked_in', 'in_queue'])
            ->get()->getResultArray()), true);
        $slots = [];

        if (! $leave) {
            foreach ($schedules as $schedule) {
                $start = substr((string) $schedule['start_time'], 0, 5);
                $end = substr((string) $schedule['end_time'], 0, 5);
                $breakStart = substr((string) ($schedule['break_start_time'] ?? ''), 0, 5);
                $breakEnd = substr((string) ($schedule['break_end_time'] ?? ''), 0, 5);
                $scheduleStart = $this->timeToMinutes($start);
                $scheduleEnd = $this->timeToMinutes($end);
                $firstQuarter = intdiv($scheduleStart + 14, 15) * 15;
                $lastQuarter = intdiv($scheduleEnd, 15) * 15;

                for ($minutes = $firstQuarter; $minutes + 15 <= $lastQuarter; $minutes += 15) {
                    $value = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
                    $time = $value . ':00';
                    $inBreak = $breakStart !== '' && $breakEnd !== '' && $value >= $breakStart && $value < $breakEnd;
                    $past = $date === $today && $time <= $now;

                    if ($inBreak || $past || isset($booked[$value])) {
                        continue;
                    }

                    $slots[$value] = ['value' => $value, 'label' => rootins_format_time($time)];
                }
            }
        }

        $values = array_values($slots);
        return $this->json(200, true, $values === [] ? 'No appointment times are available for this date.' : 'Appointment times loaded.', [
            'slots' => $values,
            'min' => $values[0]['value'] ?? '',
            'max' => $values[count($values) - 1]['value'] ?? '',
        ]);
    }

    public function cancel(int $appointmentId): ResponseInterface
    {
        return $this->updateAppointment($appointmentId, true);
    }

    public function appointments(): string|RedirectResponse|ResponseInterface
    {
        $requestedViewForLog = strtolower(trim((string) $this->request->getGet('view')));
        $normalizedViewForLog = in_array($requestedViewForLog, ['list', 'grid'], true) ? $requestedViewForLog : 'list';
        $failedRowIndex = null;
        $failedRowKeys = [];

        try {
            $context = $this->context();

            if ($context === null) {
                return redirect()->to(base_url('permission-denied'));
            }
            $filters = $this->appointmentFilters();
            $serviceModel = new ServiceModel();
            $services = $serviceModel->forScope($context['clinic_id'])->where('status', 1)->orderBy('name')->findAll();
            $serviceFilterName = '';

            if ($filters['service_id'] > 0 && ! in_array($filters['service_id'], array_map(static fn (array $service): int => (int) $service['id'], $services), true)) {
                $filters['service_id'] = -1;
            }

            if ($filters['service_id'] > 0) {
                foreach ($services as $service) {
                    if ((int) $service['id'] === $filters['service_id']) {
                        $serviceFilterName = (string) $service['name'];

                        break;
                    }
                }
            }
        $query = db_connect()->table('appointments')->select('appointments.*, patients.first_name AS patient_first_name, patients.last_name AS patient_last_name, patients.registration_number, patients.mobile_number AS patient_mobile_number, patients.mobile_normalized AS patient_mobile_normalized, patients.country_code AS patient_legacy_country_code, telephone_country_codes.normalized_code AS patient_telephone_country_code, consultations.id AS consultation_id, prescriptions.id AS prescription_id')->join('patients', 'patients.id = appointments.patient_id')->join('telephone_country_codes', 'telephone_country_codes.id = patients.telephone_country_code_id', 'left')->join('consultations', 'consultations.appointment_id = appointments.id', 'left')->join('prescriptions', 'prescriptions.consultation_id = consultations.id', 'left')->where('appointments.doctor_id', $context['doctor_id'])->where('appointments.clinic_id', $context['clinic_id']);
            $applyAppointmentFilters = function ($builder, bool $includeStatus = true) use ($filters, $serviceFilterName): void {
                $this->applySearch($builder, $filters['search'], [
                    'appointments.appointment_number',
                    'patients.first_name',
                    'patients.last_name',
                    'patients.registration_number',
                    'patients.mobile_normalized',
                    'patients.mobile_number',
                    'appointments.service_name',
                    'appointments.queue_position',
                    'appointments.appointment_type',
                    'appointments.status',
                ]);

                if ($filters['appointment_type'] !== '') {
                    $builder->where('appointments.appointment_type', $filters['appointment_type']);
                }

                if ($includeStatus && $filters['status'] !== '') {
                    $builder->where('appointments.status', $filters['status']);
                }

                if ($filters['service_id'] !== 0) {
                    if ($filters['service_id'] < 0) {
                        $builder->where('appointments.id', 0);
                    } else {
                        $builder->groupStart()
                            ->where("EXISTS (SELECT 1 FROM appointment_services AS appointment_service_filter WHERE appointment_service_filter.appointment_id = appointments.id AND appointment_service_filter.service_id = {$filters['service_id']})", null, false)
                            ->orWhere('appointments.service_name', $serviceFilterName)
                            ->groupEnd();
                    }
                }

                if ($filters['queue_number'] > 0) {
                    $builder->where('appointments.queue_position', $filters['queue_number']);
                }

                if ($filters['date_from_iso'] !== null) {
                    $builder->where('appointments.appointment_date >=', $filters['date_from_iso']);
                }

                if ($filters['date_to_iso'] !== null) {
                    $builder->where('appointments.appointment_date <=', $filters['date_to_iso']);
                }
            };
            $applyAppointmentFilters($query);
            $db = db_connect();
            $summaryQuery = $db->table('appointments')
                ->select("COUNT(DISTINCT CASE WHEN appointments.appointment_date = " . $db->escape(date('Y-m-d')) . " THEN appointments.id END) AS today_count, COUNT(DISTINCT CASE WHEN appointments.status IN ('scheduled', 'checked_in', 'in_queue') THEN appointments.id END) AS pending_count, COUNT(DISTINCT CASE WHEN appointments.status = 'confirmed' THEN appointments.id END) AS confirmed_count, COUNT(DISTINCT CASE WHEN appointments.status = 'completed' THEN appointments.id END) AS completed_count, COUNT(DISTINCT CASE WHEN appointments.status IN ('cancelled', 'no_show') THEN appointments.id END) AS cancelled_count", false)
                ->join('patients', 'patients.id = appointments.patient_id')
                ->where('appointments.doctor_id', $context['doctor_id'])
                ->where('appointments.clinic_id', $context['clinic_id'])
                ;
            $applyAppointmentFilters($summaryQuery, false);
            $summary = $summaryQuery->get()->getRowArray() ?? [];
            $statusSummary = [
                'today' => (int) ($summary['today_count'] ?? 0),
                'pending' => (int) ($summary['pending_count'] ?? 0),
                'confirmed' => (int) ($summary['confirmed_count'] ?? 0),
                'completed' => (int) ($summary['completed_count'] ?? 0),
                'cancelled' => (int) ($summary['cancelled_count'] ?? 0),
            ];
            [$appointments, $pagination] = $this->window($query->orderBy('appointments.appointment_date', 'DESC')->orderBy('appointments.appointment_time', 'DESC')->orderBy('appointments.id', 'DESC'), $filters['page'], $filters['page_size']);
            $consultationEligibility = new AppointmentConsultationEligibility();
            $permissions = array_fill_keys((array) session('permissions'), true);
            $normalizedAppointments = [];

            foreach ($appointments as $rowIndex => $appointment) {
                $failedRowIndex = $rowIndex;
                $failedRowKeys = array_keys($appointment);
                $normalizedAppointments[] = $this->normalizeAppointmentRow($appointment, $consultationEligibility, [
                    'view' => ! empty($permissions['appointment.view']),
                    'edit' => ! empty($permissions['appointment.edit']),
                    'consult' => ! empty($permissions['consultation.edit']),
                    'follow_up' => ! empty($permissions['consultation.edit']),
                    'prescription' => ! empty($permissions['prescription.view']),
                    'billing' => ! empty($permissions['billing.view']),
                ]);
            }
            $appointments = $normalizedAppointments;

            return view(DOCTOR_VIEW . 'pages/appointments', ['services' => $services, 'appointments' => $appointments, 'filters' => $filters, 'pagination' => $pagination, 'statusSummary' => $statusSummary]);
        } catch (Throwable $e) {
            log_message('error', 'Doctor appointment view request failed: ' . json_encode([
                'route' => 'doctor/appointments',
                'controller_method' => __METHOD__,
                'requested_view' => $requestedViewForLog !== '' ? $requestedViewForLog : 'list',
                'normalized_view' => $normalizedViewForLog,
                'request_type' => $this->request->isAJAX() ? 'ajax' : 'normal',
                'view_file' => APPPATH . 'Modules/Doctor/Views/pages/appointments_' . $normalizedViewForLog . '.php',
                'data_keys' => ['services', 'appointments', 'filters', 'pagination', 'statusSummary'],
                'row_index' => $failedRowIndex,
                'row_data_keys' => $failedRowKeys,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Appointment view could not be loaded safely.']);
            }

            return $this->response->setStatusCode(500)->setBody('Appointment view could not be loaded safely.');
        }
    }

    public function patientSearch(): ResponseInterface
    {
        if ($this->request->getMethod(true) !== 'GET') {
            return $this->json(405, false, 'Invalid request.');
        }
        $context = $this->context();

        if ($context === null) {
            return $this->json(403, false, 'Doctor scope is invalid.');
        }
        $search = trim((string) $this->request->getGet('q'));
        $page = max(1, (int) $this->request->getGet('page'));
        $requestedSize = (int) $this->request->getGet('page_size');
        $pageSize = in_array($requestedSize, [10, 20, 50], true) ? $requestedSize : 20;

        if ($search === '' || mb_strlen($search) < 2) {
            return $this->json(200, true, 'Enter at least 2 characters to search patients.', ['items' => [], 'pagination' => ['page' => 1, 'pages' => 1, 'page_size' => $pageSize, 'total' => 0]]);
        }
        $query = db_connect()->table('patients')->select('patients.id, patients.first_name, patients.last_name, patients.email, patients.registration_number, patients.mobile_number, patients.mobile_normalized, patients.country_code, telephone_country_codes.normalized_code AS telephone_country_code, shopify_patient_identity_links.shopify_customer_id')->join('telephone_country_codes', 'telephone_country_codes.id = patients.telephone_country_code_id', 'left')->join('shopify_patient_identity_links', "shopify_patient_identity_links.patient_id = patients.id AND shopify_patient_identity_links.link_status = 'linked'", 'left');
        $this->applyDoctorPatientScope($query, $context);

        if ($search !== '') {
            $query->groupStart()->like('patients.first_name', $search)->orLike('patients.last_name', $search)->orLike('patients.registration_number', $search)->orLike('patients.mobile_normalized', $search)->orLike('patients.mobile_number', $search)->orLike('patients.email', $search)->orLike('shopify_patient_identity_links.shopify_customer_id', $search)->groupEnd();
        }
        $total = $query->countAllResults(false);
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $rows = $query->orderBy('patients.created_at', 'DESC')->orderBy('patients.id', 'DESC')->limit($pageSize, ($page - 1) * $pageSize)->get()->getResultArray();
        $items = array_map(static function (array $patient): array {
            $name = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));
            $mobile = service('telephoneContact')->formatDisplay($patient['telephone_country_code'] ?? null, $patient['mobile_normalized'] ?? $patient['mobile_number'] ?? null, $patient['country_code'] ?? null);

            return [
                'id' => (int) $patient['id'],
                'name' => $name,
                'mobile' => $mobile,
                'registration_number' => (string) ($patient['registration_number'] ?? ''),
                'shopify_customer_id' => (string) ($patient['shopify_customer_id'] ?? ''),
                'label' => $name . ' · ' . ($mobile !== '' ? $mobile . ' · ' : '') . 'ID ' . (int) $patient['id'],
            ];
        }, $rows);

        return $this->json(200, true, 'Patients loaded.', ['items' => $items, 'pagination' => ['page' => $page, 'pages' => $pages, 'page_size' => $pageSize, 'total' => $total]]);
    }

    public function followUpPatientSearch(): ResponseInterface
    {
        return $this->patientSearch();
    }

    public function followUps(): string|RedirectResponse
    {
        $context = $this->context();

        if ($context === null) {
            return redirect()->to(base_url('permission-denied'));
        }
        $filters = $this->followUpFilters();
        $shopifyPatientIds = $this->shopifyVisiblePatientIds($context);
        $query = db_connect()->table('consultations')->select('consultations.*, follow_up_types.name AS follow_up_type_name, follow_up_statuses.name AS follow_up_status_name, follow_up_statuses.code AS follow_up_status_code, appointments.appointment_number, appointments.appointment_type, patients.first_name AS patient_first_name, patients.last_name AS patient_last_name, patients.registration_number, patients.mobile_number AS patient_mobile_number, patients.mobile_normalized AS patient_mobile_normalized, patients.country_code AS patient_legacy_country_code, telephone_country_codes.normalized_code AS patient_telephone_country_code')->join('patients', 'patients.id = consultations.patient_id')->join('telephone_country_codes', 'telephone_country_codes.id = patients.telephone_country_code_id', 'left')->join('follow_up_types', 'follow_up_types.id = consultations.follow_up_type_id', 'left')->join('follow_up_statuses', 'follow_up_statuses.id = consultations.follow_up_status_id', 'left')->join('appointments', 'appointments.id = consultations.appointment_id AND appointments.clinic_id = consultations.clinic_id', 'left')->where('consultations.doctor_id', $context['doctor_id'])->where('consultations.clinic_id', $context['clinic_id'])->where('consultations.follow_up_date IS NOT NULL', null, false);
        $patientDb = db_connect();
        if ($patientDb->fieldExists('status', 'patients')) $query->where('patients.status', 1);
        if ($patientDb->fieldExists('deleted_at', 'patients')) $query->where('patients.deleted_at', null);
        $this->applySearch($query, $filters['search'], ['patients.first_name', 'patients.last_name', 'patients.registration_number', 'patients.mobile_normalized', 'patients.mobile_number', 'consultations.consultation_number', 'appointments.appointment_number', 'consultations.follow_up_time', 'follow_up_types.name', 'consultations.follow_up_type', 'follow_up_statuses.name', 'follow_up_statuses.code', 'consultations.status', 'consultations.follow_up_remarks']);

        if ($filters['status'] !== '') {
            if (ctype_digit($filters['status']) && (int) $filters['status'] > 0) {
                $query->where('consultations.follow_up_status_id', (int) $filters['status']);
            } else {
                $query->where('consultations.status', $filters['status']);
            }
        }

        if ($filters['follow_up_type_id'] > 0) {
            $query->where('consultations.follow_up_type_id', $filters['follow_up_type_id']);
        }

        if ($filters['date_from_iso'] !== null) {
            $query->where('consultations.follow_up_date >=', $filters['date_from_iso']);
        }

        if ($filters['date_to_iso'] !== null) {
            $query->where('consultations.follow_up_date <=', $filters['date_to_iso']);
        }
        [$followUps, $pagination] = $this->window($query->orderBy('consultations.follow_up_date', 'ASC')->orderBy('consultations.follow_up_time', 'ASC')->orderBy('consultations.id', 'DESC'), $filters['page'], $filters['page_size']);
        $followUpTypes = (new FollowUpTypeModel())->activeForClinic($context['clinic_id']);
        $followUpStatuses = (new FollowUpStatusModel())->activeForClinic($context['clinic_id']);

        return view(DOCTOR_VIEW . 'pages/follow-ups', ['followUps' => $followUps, 'followUpTypes' => $followUpTypes, 'followUpStatuses' => $followUpStatuses, 'filters' => $filters, 'pagination' => $pagination]);
    }

    public function createFollowUp(): ResponseInterface
    {
        return $this->saveFollowUp();
    }

    public function updateFollowUp(int $consultationId): ResponseInterface
    {
        return $this->saveFollowUp($consultationId);
    }

    private function saveFollowUp(?int $updateId = null): ResponseInterface
    {
        if (!$this->isAjaxPost()) {
            return $this->json(405, false, 'Invalid request.');
        }
        $context = $this->context();
        $patientId = (int) $this->request->getPost('patient_id');
        $consultationId = $updateId ?? 0;
        $dateInput = trim((string) $this->request->getPost('follow_up_date'));
        $date = $this->normalizeDisplayDate($dateInput);
        $timeInput = trim((string) $this->request->getPost('follow_up_time'));
        $time = $this->normalizeFollowUpTime($timeInput);
        $typeInput = trim((string) $this->request->getPost('follow_up_type_id'));
        $statusInput = trim((string) $this->request->getPost('status'));
        $remarks = trim((string) $this->request->getPost('remarks'));

        if ($context === null) {
            return $this->json(403, false, 'Doctor scope is invalid.');
        }
        $errors = [];

        if ($patientId <= 0) {
            $errors['patient_id'] = 'Select a patient in your clinic scope.';
        }

        if ($date === null) {
            $errors['follow_up_date'] = 'A valid follow-up date is required.';
        }

        if ($time === null) {
            $errors['follow_up_time'] = 'A valid follow-up time is required.';
        }
        $followUpType = null;

        if (! ctype_digit($typeInput) || (int) $typeInput <= 0) {
            $errors['follow_up_type_id'] = 'Select an active follow-up type.';
        } else {
            $typeId = (int) $typeInput;
            $followUpType = (new FollowUpTypeModel())->activeForClinicById($typeId, $context['clinic_id']);

            if ($followUpType === null) {
                $errors['follow_up_type_id'] = 'Select an active follow-up type.';
            }
        }
        $followUpStatus = null;

        if (ctype_digit($statusInput) && (int) $statusInput > 0) {
            $followUpStatus = (new FollowUpStatusModel())->activeForClinicById((int) $statusInput, $context['clinic_id']);
        } elseif ($statusInput !== '') {
            $followUpStatus = (new FollowUpStatusModel())->activeForClinicByCode($statusInput, $context['clinic_id']);
        }

        if ($followUpStatus === null) {
            $errors['status'] = 'Select an active follow-up status.';
        }

        if (mb_strlen($remarks) > 5000) {
            $errors['remarks'] = 'Remarks must be 5000 characters or fewer.';
        }

        if ($errors !== []) {
            return $this->validation($errors);
        }
        $db = db_connect();
        $patient = $db->table('patients')->where('id', $patientId)->where('status', 1)->get()->getRowArray();

        if ($patient === null) {
            return $this->validation(['patient_id' => 'Select a patient in your clinic scope.']);
        }
        $consultationQuery = $db->table('consultations')
            ->where('patient_id', $patientId)
            ->where('doctor_id', $context['doctor_id'])
            ->where('clinic_id', $context['clinic_id']);

        if ($consultationId > 0) {
            $consultationQuery->where('id', $consultationId);
        } else {
            $consultationQuery->orderBy('consultation_date', 'DESC')->orderBy('id', 'DESC');
        }
        $consultation = $consultationQuery->get()->getRowArray();

        if ($consultation === null) {
            return $this->validation(['patient_id' => 'No consultation is available for this patient in your clinic scope.']);
        }
        $consultationId = (int) $consultation['id'];
        $duplicateQuery = $db->table('consultations')
            ->where('doctor_id', $context['doctor_id'])
            ->where('clinic_id', $context['clinic_id'])
            ->where('patient_id', $patientId)
            ->where('follow_up_date', $date)
            ->where('id !=', $consultationId);

        if ($duplicateQuery->countAllResults() > 0) {
            return $this->validation(['follow_up_date' => 'A follow-up already exists for this patient on the selected date.']);
        }
        $effectiveStatus = (string) ($consultation['status'] ?? '') === 'completed' ? 'completed' : (string) $followUpStatus['code'];
        $effectiveStatusRecord = (new FollowUpStatusModel())->activeForClinicByCode($effectiveStatus, $context['clinic_id']);

        if ($effectiveStatusRecord === null) {
            return $this->validation(['status' => 'Select an active follow-up status.']);
        }
        $typeId = (int) $followUpType['id'];
        $statusId = (int) $effectiveStatusRecord['id'];
        $typeName = (string) ($followUpType['name'] ?? '');

        if ((string) ($consultation['follow_up_date'] ?? '') === $date && (string) ($consultation['follow_up_time'] ?? '') === $time && (int) ($consultation['follow_up_type_id'] ?? 0) === $typeId && (string) ($consultation['follow_up_type'] ?? '') === $typeName && (int) ($consultation['follow_up_status_id'] ?? 0) === $statusId && (string) ($consultation['status'] ?? '') === $effectiveStatus && (string) ($consultation['follow_up_remarks'] ?? '') === $remarks) {
            return $this->json(200, true, $updateId === null ? 'Follow-up already recorded.' : 'Follow-up is unchanged.', ['consultation_id' => $consultationId]);
        }
        $payload = ['follow_up_date' => $date, 'follow_up_time' => $time, 'follow_up_type_id' => $typeId, 'follow_up_type' => $typeName, 'follow_up_status_id' => $statusId, 'status' => $effectiveStatus, 'follow_up_remarks' => $remarks !== '' ? $remarks : null, 'updated_at' => date('Y-m-d H:i:s')];
        $db->transBegin();

        try {
            if (!$db->table('consultations')->where('id', $consultationId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->update($payload) || $db->transStatus() === false || !$db->transCommit()) {
                throw new \RuntimeException('Follow-up update failed.');
            }
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Doctor follow-up creation failed: ' . $e->getMessage());

            return $this->json(500, false, 'Follow-up could not be saved safely.');
        }
        $action = $updateId === null ? 'doctor.follow_up_created' : 'doctor.follow_up_updated';
        $this->audit($action, $context, ['consultation_id' => $consultationId, 'patient_id' => $patientId, 'appointment_id' => (int) ($consultation['appointment_id'] ?? 0) ?: null, 'follow_up_time' => $time, 'status' => $effectiveStatus]);

        return $this->json(200, true, $updateId === null ? 'Follow-up created successfully.' : 'Follow-up updated successfully.', ['consultation_id' => $consultationId]);
    }

    public function billing(): string|RedirectResponse
    {
        $context = $this->context();

        if ($context === null) {
            return redirect()->to(base_url('permission-denied'));
        }
        $filters = $this->listFilters();
        $query = db_connect()->table('invoices')->select('invoices.invoice_number, invoices.currency, invoices.total, invoices.status, invoices.payment_status, invoices.created_at, patients.first_name AS patient_first_name, patients.last_name AS patient_last_name')->join('appointments', 'appointments.id = invoices.appointment_id')->join('patients', 'patients.id = invoices.patient_id')->where('appointments.doctor_id', $context['doctor_id'])->where('invoices.clinic_id', $context['clinic_id']);
        $patientId = max(0, (int) $this->request->getGet('patient_id'));

        if ($patientId > 0) {
            $query->where('invoices.patient_id', $patientId);
        }
        $this->applySearch($query, $filters['search'], ['invoices.invoice_number', 'patients.first_name', 'patients.last_name']);
        [$invoices, $pagination] = $this->window($query->orderBy('invoices.id', 'DESC'), $filters['page'], $filters['page_size']);

        return view(DOCTOR_VIEW . 'pages/billing', ['invoices' => $invoices, 'filters' => $filters, 'pagination' => $pagination]);
    }

    public function prescriptions(): string|RedirectResponse
    {
        $context = $this->context();

        if ($context === null) {
            return redirect()->to(base_url('permission-denied'));
        }
        $filters = $this->prescriptionFilters();
        $db = db_connect();
        $cartSyncFields = $db->fieldExists('cart_sync_status', 'prescriptions')
            ? ', prescriptions.cart_sync_status, prescriptions.cart_pushed_at, prescriptions.cart_sync_failure_reason'
            : '';
        $query = $db->table('prescriptions')->select('prescriptions.*' . $cartSyncFields . ', patients.first_name AS patient_first_name, patients.last_name AS patient_last_name, patients.registration_number, patients.date_of_birth AS patient_date_of_birth, patients.mobile_number AS patient_mobile_number, patients.mobile_normalized AS patient_mobile_normalized, patients.country_code AS patient_legacy_country_code, telephone_country_codes.normalized_code AS patient_telephone_country_code, clinics.clinic_name, clinics.address AS clinic_address, clinics.mobile AS clinic_mobile, clinics.logo AS clinic_logo, (SELECT COUNT(*) FROM prescription_items AS prescription_item_count WHERE prescription_item_count.prescription_id = prescriptions.id) AS medicine_count', false)->join('patients', 'patients.id = prescriptions.patient_id')->join('telephone_country_codes', 'telephone_country_codes.id = patients.telephone_country_code_id', 'left')->join('clinics', 'clinics.id = prescriptions.clinic_id AND clinics.status = 1', 'left')->where('prescriptions.doctor_id', $context['doctor_id'])->where('prescriptions.clinic_id', $context['clinic_id']);
        $this->applySearch($query, $filters['search'], ['prescriptions.prescription_number', 'patients.first_name', 'patients.last_name', 'patients.registration_number', 'patients.mobile_number', 'patients.mobile_normalized', 'prescriptions.status']);

        if ($filters['status'] !== '') {
            $query->where('prescriptions.status', $filters['status']);
        }

        if ($filters['date_from_iso'] !== null) {
            $query->where('prescriptions.prescription_date >=', $filters['date_from_iso']);
        }

        if ($filters['date_to_iso'] !== null) {
            $query->where('prescriptions.prescription_date <=', $filters['date_to_iso']);
        }

        if ($filters['prescription_id'] > 0) {
            $query->where('prescriptions.id', $filters['prescription_id']);
        }
        [$prescriptions, $pagination] = $this->window($query->orderBy('prescriptions.prescription_date', 'DESC')->orderBy('prescriptions.id', 'DESC'), $filters['page'], $filters['page_size']);

        foreach ($prescriptions as &$prescription) {
            $dateOfBirth = trim((string) ($prescription['patient_date_of_birth'] ?? ''));
            $prescription['patient_age'] = '—';

            if ($dateOfBirth !== '') {
                try {
                    $prescription['patient_age'] = (string) (new \DateTimeImmutable($dateOfBirth))->diff(new \DateTimeImmutable('today'))->y;
                } catch (Throwable) {
                    $prescription['patient_age'] = '—';
                }
            }
            $prescription['patient_mobile_display'] = service('telephoneContact')->formatDisplay($prescription['patient_telephone_country_code'] ?? null, $prescription['patient_mobile_normalized'] ?? $prescription['patient_mobile_number'] ?? null, $prescription['patient_legacy_country_code'] ?? null);
            $prescription['cart_sync_status'] = in_array(($prescription['cart_sync_status'] ?? ''), ['not_synced', 'syncing', 'synced', 'already_in_cart', 'sync_failed', 'ordered', 'paid', 'expired', 'cancelled'], true) ? (string) $prescription['cart_sync_status'] : 'not_synced';
            $prescription['cart_pushed_at'] = $prescription['cart_pushed_at'] ?? null;
            $prescription['cart_sync_failure_reason'] = $prescription['cart_sync_failure_reason'] ?? null;
        }
        unset($prescription);
        $items = [];

        if ($prescriptions !== []) {
            foreach (db_connect()->table('prescription_items')->select('prescription_items.*, medicines.medicine_name AS medicine_catalog_name, medicines.strength AS medicine_strength, medicines.dosage_form AS medicine_form')->join('medicines', 'medicines.id = prescription_items.medicine_id', 'left')->whereIn('prescription_items.prescription_id', array_column($prescriptions, 'id'))->orderBy('prescription_items.id')->get()->getResultArray() as $item) {
                $items[(int) $item['prescription_id']][] = $item;
            }
        }
        $statusOptions = array_values(array_filter((new PrescriptionOptionModel())->activeOptions(PrescriptionOptionModel::TYPES['status'], $context['clinic_id']), static fn (array $option): bool => trim((string) ($option['code'] ?? '')) !== ''));

        return view(DOCTOR_VIEW . 'pages/prescriptions', ['prescriptions' => $prescriptions, 'items' => $items, 'filters' => $filters, 'pagination' => $pagination, 'statusOptions' => $statusOptions]);
    }

    public function prescriptionPdf(int $id): ResponseInterface
    {
        $context = $this->context();
        if ($context === null) {
            return $this->response->setStatusCode(403)->setBody('Doctor scope is unavailable.');
        }

        $db = db_connect();
        $prescription = $db->table('prescriptions')
            ->select('prescriptions.*, consultations.consultation_number, consultations.consultation_date, patients.registration_number, patients.email AS patient_email, patients.mobile_number AS patient_mobile, patients.first_name AS patient_first_name, patients.middle_name AS patient_middle_name, patients.last_name AS patient_last_name, patients.date_of_birth AS patient_date_of_birth, patients.gender AS patient_gender, patients.address AS patient_address, doctors.first_name AS doctor_first_name, doctors.last_name AS doctor_last_name, doctors.qualification, doctors.registration_number AS doctor_registration_number, (SELECT GROUP_CONCAT(ds.specialization_name ORDER BY ds.specialization_name SEPARATOR \' , \') FROM doctor_specialization_map dsm JOIN doctor_specializations ds ON ds.id = dsm.specialization_id WHERE dsm.doctor_id = doctors.id AND ds.status = 1) AS doctor_specializations, clinics.clinic_name, clinics.address AS clinic_address, clinics.mobile AS clinic_mobile, clinics.email AS clinic_email, clinics.pincode AS clinic_pincode, appointments.appointment_number')
            ->join('consultations', 'consultations.id = prescriptions.consultation_id')
            ->join('patients', 'patients.id = prescriptions.patient_id')
            ->join('doctors', 'doctors.id = prescriptions.doctor_id')
            ->join('clinics', 'clinics.id = prescriptions.clinic_id')
            ->join('appointments', 'appointments.id = consultations.appointment_id', 'left')
            ->where('prescriptions.id', $id)
            ->where('prescriptions.doctor_id', $context['doctor_id'])
            ->where('prescriptions.clinic_id', $context['clinic_id'])
            ->where('doctors.id', $context['doctor_id'])
            ->where('doctors.clinic_id', $context['clinic_id'])
            ->get(1)->getRowArray();

        if ($prescription === null) {
            return $this->response->setStatusCode(404)->setBody('Prescription not found.');
        }
        if ((string) ($prescription['status'] ?? '') !== 'completed') {
            return $this->response->setStatusCode(403)->setBody('Only completed prescriptions can be printed.');
        }

        $items = $db->table('prescription_items')->select('prescription_items.*, medicines.medicine_name AS medicine_catalog_name, medicines.strength AS medicine_strength, medicines.dosage_form AS medicine_form')->join('medicines', 'medicines.id = prescription_items.medicine_id', 'left')->where('prescription_id', $id)->orderBy('prescription_items.id')->get()->getResultArray();
        $pdf = (new PrescriptionPdfService())->generate($prescription, $items);
        $reference = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) ($prescription['prescription_number'] ?? $id)) ?: (string) $id;
        $this->audit('doctor.prescription_pdf_downloaded', $context, ['prescription_id' => $id, 'patient_id' => (int) $prescription['patient_id']]);

        return $this->response->setHeader('Content-Type', 'application/pdf')->setHeader('Content-Disposition', 'attachment; filename="ROOTINS-Prescription-' . $reference . '.pdf"')->setBody($pdf);
    }

    private function prescriptionFilters(): array
    {
        $filters = $this->listFilters();
        $filters['status'] = trim((string) $this->request->getGet('status'));
        $filters['date_from'] = trim((string) $this->request->getGet('date_from'));
        $filters['date_to'] = trim((string) $this->request->getGet('date_to'));
        $filters['view'] = in_array((string) $this->request->getGet('view'), ['list', 'grid'], true)
            ? (string) $this->request->getGet('view')
            : 'list';
        $filters['date_from_iso'] = $filters['date_from'] === '' ? null : $this->normalizeDisplayDate($filters['date_from']);
        $filters['date_to_iso'] = $filters['date_to'] === '' ? null : $this->normalizeDisplayDate($filters['date_to']);
        $filters['prescription_id'] = max(0, (int) $this->request->getGet('prescription_id'));
        $filters['filter_errors'] = [];

        if ($filters['date_from'] !== '' && $filters['date_from_iso'] === null) {
            $filters['filter_errors']['date_from'] = 'Enter a valid date as dd-mm-yyyy.';
        }

        if ($filters['date_to'] !== '' && $filters['date_to_iso'] === null) {
            $filters['filter_errors']['date_to'] = 'Enter a valid date as dd-mm-yyyy.';
        }

        if ($filters['date_from_iso'] !== null && $filters['date_to_iso'] !== null && $filters['date_from_iso'] > $filters['date_to_iso']) {
            $filters['filter_errors']['date_from'] = 'The start date must be on or before the end date.';
        }

        return $filters;
    }

    private function listFilters(): array
    {
        $size = (int) $this->request->getGet('page_size');

        return ['search' => trim((string) $this->request->getGet('search')), 'page' => max(1, (int) $this->request->getGet('page')), 'page_size' => in_array($size, [10, 15, 25, 50], true) ? $size : 15];
    }

    private function followUpFilters(): array
    {
        $filters = $this->listFilters();
        $filters['status'] = trim((string) $this->request->getGet('status'));

        if ($filters['status'] !== '' && ! ctype_digit($filters['status']) && ! in_array($filters['status'], ['draft', 'in_progress', 'completed', 'cancelled'], true)) {
            $filters['status'] = '';
        }
        $filters['follow_up_type_id'] = max(0, (int) $this->request->getGet('follow_up_type_id'));
        $filters['date_from'] = trim((string) $this->request->getGet('date_from'));
        $filters['date_to'] = trim((string) $this->request->getGet('date_to'));
        $filters['view'] = in_array((string) $this->request->getGet('view'), ['list', 'grid'], true)
            ? (string) $this->request->getGet('view')
            : 'list';
        $filters['date_from_iso'] = $filters['date_from'] === '' ? null : $this->normalizeDisplayDate($filters['date_from']);
        $filters['date_to_iso'] = $filters['date_to'] === '' ? null : $this->normalizeDisplayDate($filters['date_to']);
        $filters['filter_errors'] = [];

        if ($filters['date_from'] !== '' && $filters['date_from_iso'] === null) {
            $filters['filter_errors']['date_from'] = 'Enter a valid date as dd-mm-yyyy.';
        }

        if ($filters['date_to'] !== '' && $filters['date_to_iso'] === null) {
            $filters['filter_errors']['date_to'] = 'Enter a valid date as dd-mm-yyyy.';
        }

        if ($filters['date_from_iso'] !== null && $filters['date_to_iso'] !== null && $filters['date_from_iso'] > $filters['date_to_iso']) {
            $filters['filter_errors']['date_from'] = 'The start date must be on or before the end date.';
        }

        return $filters;
    }

    private function appointmentFilters(): array
    {
        $filters = $this->listFilters();
        $requestedView = strtolower(trim((string) $this->request->getGet('view')));
        $filters['view'] = in_array($requestedView, ['list', 'grid'], true) ? $requestedView : 'list';
        $filters['appointment_type'] = trim((string) $this->request->getGet('appointment_type'));

        if (!in_array($filters['appointment_type'], ['', 'in_person', 'video', 'phone'], true)) {
            $filters['appointment_type'] = '';
        }
        $filters['status'] = trim((string) $this->request->getGet('status'));
        $allowedStatuses = ['scheduled', 'confirmed', 'checked_in', 'in_queue', 'completed', 'cancelled', 'no_show'];

        if (!in_array($filters['status'], array_merge([''], $allowedStatuses), true)) {
            $filters['status'] = '';
        }
        $filters['service_id'] = max(0, (int) $this->request->getGet('service_id'));
        $queueNumber = trim((string) $this->request->getGet('queue_number'));
        $filters['queue_number'] = ctype_digit($queueNumber) ? (int) $queueNumber : 0;
        $filters['date_from'] = trim((string) $this->request->getGet('date_from'));
        $filters['date_to'] = trim((string) $this->request->getGet('date_to'));
        $filters['date_from_iso'] = $filters['date_from'] === '' ? null : $this->normalizeDisplayDate($filters['date_from']);
        $filters['date_to_iso'] = $filters['date_to'] === '' ? null : $this->normalizeDisplayDate($filters['date_to']);
        $filters['filter_errors'] = [];

        if ($filters['date_from'] !== '' && $filters['date_from_iso'] === null) {
            $filters['filter_errors']['date_from'] = 'Enter a valid date as dd-mm-yyyy.';
        }

        if ($filters['date_to'] !== '' && $filters['date_to_iso'] === null) {
            $filters['filter_errors']['date_to'] = 'Enter a valid date as dd-mm-yyyy.';
        }

        if ($filters['date_from_iso'] !== null && $filters['date_to_iso'] !== null && $filters['date_from_iso'] > $filters['date_to_iso']) {
            $filters['filter_errors']['date_from'] = 'The start date must be on or before the end date.';
        }

        return $filters;
    }

    private function resolveServices(array $context): array
    {
        $postedIds = $this->request->getPost('service_ids');
        $serviceIds = is_array($postedIds) ? array_values(array_unique(array_filter(array_map('intval', $postedIds), static fn (int $id): bool => $id > 0))) : [];
        $legacy = trim((string) $this->request->getPost('service_name'));
        $serviceModel = new ServiceModel();
        $query = $serviceModel->forScope($context['clinic_id'])->where('status', 1);

        if ($serviceIds !== []) {
            $rows = $query->whereIn('id', $serviceIds)->findAll();
            $byId = [];

            foreach ($rows as $row) {
                $byId[(int) $row['id']] = (string) $row['name'];
            }

            if (count($byId) !== count($serviceIds)) {
                return [[], [], 'Select one or more valid services.'];
            }
            $names = array_map(static fn (int $id): string => $byId[$id], $serviceIds);
        } elseif ($legacy !== '') {
            $legacyNames = array_values(array_unique(array_filter(array_map('trim', explode(',', $legacy)))));
            $rows = $query->whereIn('name', $legacyNames)->findAll();
            $byName = [];

            foreach ($rows as $row) {
                $byName[(string) $row['name']] = (int) $row['id'];
            }

            if (count($byName) !== count($legacyNames)) {
                return [[], [], 'Select one or more valid services.'];
            }
            $serviceIds = array_map(static fn (string $name): int => $byName[$name], $legacyNames);
            $names = $legacyNames;
        } else {
            return [[], [], 'Select one or more services.'];
        }
        $serviceName = implode(', ', $names);

        if (mb_strlen($serviceName) > 150) {
            return [[], [], 'The selected services exceed the allowed length.'];
        }

        return [$serviceIds, $names, null];
    }

    private function applySearch($query, string $search, array $columns): void
    {
        if ($search === '') {
            return;
        }
        $query->groupStart();

        foreach ($columns as $index => $column) {
            $index === 0 ? $query->like($column, $search) : $query->orLike($column, $search);
        }
        $query->groupEnd();
    }

    private function window($query, int $page, int $pageSize): array
    {
        $total = $query->countAllResults(false);
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);

        return [$query->limit($pageSize, ($page - 1) * $pageSize)->get()->getResultArray(), ['page' => $page, 'pages' => $pages, 'page_size' => $pageSize, 'total' => $total]];
    }

    private function normalizeAppointmentRow(array $appointment, AppointmentConsultationEligibility $eligibility, array $permissions): array
    {
        $appointment['id'] = max(0, (int) ($appointment['id'] ?? 0));
        $appointment['patient_id'] = max(0, (int) ($appointment['patient_id'] ?? 0));

        foreach (['appointment_number', 'patient_first_name', 'patient_last_name', 'registration_number', 'patient_mobile_number', 'patient_mobile_normalized', 'patient_telephone_country_code', 'patient_legacy_country_code', 'appointment_date', 'appointment_time', 'service_name', 'status', 'appointment_type', 'created_at', 'updated_at'] as $key) {
            $appointment[$key] = trim((string) ($appointment[$key] ?? ''));
        }
        $appointment['queue_position'] = ($appointment['queue_position'] ?? null) === null || ($appointment['queue_position'] ?? '') === '' ? null : (int) $appointment['queue_position'];
        $appointment['consultation_id'] = max(0, (int) ($appointment['consultation_id'] ?? 0));
        $appointment['prescription_id'] = max(0, (int) ($appointment['prescription_id'] ?? 0));
        $appointment['consultation_date_passed'] = $eligibility->isPast($appointment);
        $appointment['consultation_date_today'] = $eligibility->isToday($appointment);
        $appointment['consultation_unavailable_reason'] = $eligibility->unavailableReason($appointment);
        $appointment['consultation_eligible'] = $eligibility->isConsultable($appointment)
            && in_array($appointment['status'], ['scheduled', 'confirmed', 'checked_in', 'in_queue'], true);
        $appointment['action_permissions'] = $permissions;

        return $appointment;
    }

    private function updateAppointment(int $appointmentId, bool $cancel): ResponseInterface
    {
        if (!$this->isAjaxPost()) {
            return $this->json(405, false, 'Invalid request.');
        }
        $context = $this->context();

        if ($context === null) {
            return $this->json(403, false, 'Doctor scope is invalid.');
        }
        $db = db_connect();
        $appointment = $db->table('appointments')->where('id', $appointmentId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->get()->getRowArray();

        if ($appointment === null) {
            return $this->json(404, false, 'Appointment not found in your clinic scope.');
        }

        if ($cancel) {
            if (in_array($appointment['status'], ['completed', 'cancelled', 'no_show'], true)) {
                return $this->json(409, false, 'This appointment cannot be cancelled.');
            }
            $db->transBegin();

            try {
                $ok = $db->table('appointments')->where('id', $appointmentId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->update(['status' => 'cancelled', 'cancelled_at' => date('Y-m-d H:i:s'), 'cancelled_by' => (int) (session('user')['id'] ?? 0), 'cancellation_reason' => trim((string) $this->request->getPost('cancellation_reason')) ?: 'Cancelled by doctor', 'updated_at' => date('Y-m-d H:i:s')]);

                if (!$ok || !$db->transCommit()) {
                    throw new \RuntimeException('Appointment cancellation failed.');
                }
            } catch (Throwable $e) {
                $db->transRollback();
                log_message('error', 'Doctor appointment cancellation failed: ' . $e->getMessage());

                return $this->json(500, false, 'Appointment could not be updated safely.');
            }
            $message = 'Appointment cancelled.';
            $action = 'doctor.appointment_cancelled';
        } else {
            $date = $this->normalizeDisplayDate(trim((string) $this->request->getPost('appointment_date')));
            $time = rootins_parse_time($this->request->getPost('appointment_time')) ?? '';

            if ($date === null || $time === '') {
                return $this->validation(['appointment_date' => 'Valid date and time are required.']);
            }
            $databaseTime = $time;

            if (($availability = $this->availabilityError($context['doctor_id'], $date, $time)) !== null) {
                return $this->validation(['appointment_date' => $availability]);
            }
            $db->transBegin();

            try {
                $conflict = $db->table('appointments')->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->where('appointment_date', $date)->where('appointment_time', $databaseTime)->where('id !=', $appointmentId)->whereIn('status', ['scheduled', 'confirmed', 'checked_in', 'in_queue'])->countAllResults();

                if ($conflict > 0) {
                    $db->transRollback();

                    return $this->validation(['appointment_time' => 'This time is already booked for you.']);
                }
                $ok = $db->table('appointments')->where('id', $appointmentId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->update(['appointment_date' => $date, 'appointment_time' => $databaseTime, 'updated_at' => date('Y-m-d H:i:s')]);

                if (!$ok || !$db->transCommit()) {
                    throw new \RuntimeException('Appointment reschedule failed.');
                }
            } catch (Throwable $e) {
                $db->transRollback();
                log_message('error', 'Doctor appointment reschedule failed: ' . $e->getMessage());

                return $this->json(500, false, 'Appointment could not be updated safely.');
            }
            $message = 'Appointment rescheduled.';
            $action = 'doctor.appointment_rescheduled';
        }
        service('audit')->record($action, ['appointment_id' => $appointmentId, 'doctor_id' => $context['doctor_id'], 'clinic_id' => $context['clinic_id']]);

        return $this->json(200, true, $message);
    }

    private function context(): ?array
    {
        if (!in_array('Doctor', session('roles') ?? [], true)) {
            return null;
        }
        $userId = (int) (session('user')['id'] ?? 0);
        $user = (new User())->select('doctor_id')->where('id', $userId)->where('status', 1)->first();
        $doctor = (new DoctorModel())->select('id, clinic_id')->where('id', (int) ($user['doctor_id'] ?? 0))->where('status', 1)->first();

        if ($doctor === null || (int) $doctor['clinic_id'] <= 0) {
            return null;
        }

        return ['user_id' => $userId, 'doctor_id' => (int) $doctor['id'], 'clinic_id' => (int) $doctor['clinic_id']];
    }

    private function applyDoctorPatientScope($query, array $context): void
    {
        $db = db_connect();
        if ($db->fieldExists('status', 'patients')) $query->where('patients.status', 1);
        if ($db->fieldExists('deleted_at', 'patients')) $query->where('patients.deleted_at', null);
    }

    private function shopifyVisiblePatientIds(array $context)
    {
        $db = db_connect();
        $shopifyClinic = (new ShopifyConfiguration())->clinicStatus();
        if ($db->tableExists('shopify_patient_identity_links')) {
            if ($shopifyClinic['status'] === 'active' && (int) $shopifyClinic['id'] === (int) $context['clinic_id']) {
                return $db->table('shopify_patient_identity_links')->select('patient_id')->where('link_status', 'linked')->where('patient_id IS NOT NULL', null, false);
            }

            return $db->table('patients')->select('id')->where('id', 0);
        }
        if ($db->tableExists('shopify_order_imports')) return $db->table('shopify_order_imports')->select('patient_id')->where('clinic_id', $context['clinic_id'])->where('patient_id IS NOT NULL', null, false);
        return $db->table('patients')->select('id')->where('id', 0);
    }

    private function isAjaxPost(): bool
    {
        return $this->request->isAJAX() && $this->request->getMethod(true) === 'POST';
    }

    private function availabilityError(int $doctorId, string $date, string $time): ?string
    {
        if ($date < date('Y-m-d') || ($date === date('Y-m-d') && $time <= date('H:i:s'))) {
            return 'Appointment time cannot be in the past.';
        }

        if ((int) date('i', strtotime($time)) % 15 !== 0) {
            return 'Appointment time must use 15-minute increments.';
        }

        $day = (int) date('N', strtotime($date));
        $slotStart = new \DateTimeImmutable($date . ' ' . $time);
        $slotEnd = $slotStart->modify('+15 minutes')->format('H:i:s');
        $schedules = db_connect()->table('doctor_schedules')
            ->where('doctor_id', $doctorId)
            ->where('day_of_week', $day)
            ->where('status', 1)
            ->get()->getResultArray();
        $scheduleValid = false;

        foreach ($schedules as $schedule) {
            $start = substr((string) $schedule['start_time'], 0, 8);
            $end = substr((string) $schedule['end_time'], 0, 8);
            $breakStart = substr((string) ($schedule['break_start_time'] ?? ''), 0, 8);
            $breakEnd = substr((string) ($schedule['break_end_time'] ?? ''), 0, 8);
            $breaks = $breakStart !== '' && $breakEnd !== '' && $time < $breakEnd && $slotEnd > $breakStart;

            if ($start <= $time && $slotEnd <= $end && ! $breaks) {
                $scheduleValid = true;
                break;
            }
        }

        if (! $scheduleValid) {
            return 'The selected time is outside the doctor availability.';
        }
        $leaveQuery = db_connect()->table('doctor_leaves')
            ->where('doctor_id', $doctorId)
            ->where('status', 1)
            ->where('start_date <=', $date)
            ->where('end_date >=', $date)
            ->groupStart()
            ->where('start_time', null)
            ->orWhere('end_time', null)
            ->orGroupStart()
            ->where('start_time <=', $time)
            ->where('end_time >=', $time)
            ->groupEnd()
            ->groupEnd();

        return $leaveQuery->countAllResults() > 0 ? 'The doctor is unavailable on the selected date.' : null;
    }

    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = array_pad(array_map('intval', explode(':', $time)), 2, 0);

        return max(0, min(1439, $hours * 60 + $minutes));
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

    private function normalizeFollowUpTime(string $value): ?string
    {
        $value = trim($value);

        if ($value === '' || ! preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $value)) {
            return null;
        }
        $time = \DateTimeImmutable::createFromFormat('!H:i', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if ($time === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $time->format('H:i') !== $value) {
            return null;
        }

        return $time->format('H:i:s');
    }

    private function validation(array $errors): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => 'Please correct the highlighted fields.', 'errors' => $errors]);
    }

    private function audit(string $action, array $context, array $data): void
    {
        service('audit')->record($action, array_merge(['doctor_id' => $context['doctor_id'], 'clinic_id' => $context['clinic_id']], $data));
    }

    private function json(int $code, bool $status, string $message, array $data = []): ResponseInterface
    {
        $payload = ['status' => $status, 'msg' => $message];

        if ($data !== []) {
            $payload['data'] = $data;
        }

        return $this->response->setStatusCode($code)->setJSON($payload);
    }
}
