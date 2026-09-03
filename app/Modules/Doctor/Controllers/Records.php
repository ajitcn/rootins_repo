<?php

namespace Doctor\Controllers;

use Admin\Models\Doctor\DoctorModel;
use Admin\Models\User;
use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
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
        $date = trim((string) $this->request->getPost('appointment_date'));
        $time = trim((string) $this->request->getPost('appointment_time'));
        $type = strtolower(trim((string) $this->request->getPost('appointment_type')));
        $errors = [];
        if ($context === null) {
            return $this->json(403, false, 'Doctor scope is invalid.');
        }
        $patientQuery = db_connect()->table('patients')->where('id', $patientId);
        $this->applyDoctorPatientScope($patientQuery, $context);
        if ($patientId <= 0 || $patientQuery->get()->getRowArray() === null) {
            $errors['patient_id'] = 'Select a patient in your clinic scope.';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $errors['appointment_date'] = 'A valid appointment date is required.';
        }
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
            $errors['appointment_time'] = 'A valid appointment time is required.';
        }
        if (!in_array($type, ['in_person', 'video', 'phone'], true)) {
            $errors['appointment_type'] = 'Select a supported consultation type.';
        }
        if ($errors !== []) {
            return $this->validation($errors);
        }
        $db = db_connect();
        $conflict = $db->table('appointments')->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->where('appointment_date', $date)->where('appointment_time', $time)->whereIn('status', ['scheduled', 'confirmed', 'checked_in', 'in_queue'])->countAllResults();
        if (($availability = $this->availabilityError($context['doctor_id'], $date, $time)) !== null) {
            return $this->validation(['appointment_date' => $availability]);
        }
        if ($conflict > 0) {
            return $this->validation(['appointment_time' => 'This time is already booked for you.']);
        }
        $now = date('Y-m-d H:i:s');
        $db->transBegin();
        try {
            $db->table('appointments')->insert(['appointment_number' => 'TMP-' . bin2hex(random_bytes(8)), 'patient_id' => $patientId, 'doctor_id' => $context['doctor_id'], 'clinic_id' => $context['clinic_id'], 'appointment_date' => $date, 'appointment_time' => $time, 'duration_minutes' => 15, 'appointment_type' => $type, 'status' => 'scheduled', 'created_by_type' => 'user', 'created_by_id' => (int) (session('user')['id'] ?? 0), 'booking_source' => 'doctor', 'reason_for_visit' => trim((string) $this->request->getPost('reason_for_visit')) ?: null, 'created_at' => $now, 'updated_at' => $now]);
            $id = (int) $db->insertID();
            if ($id < 1) {
                throw new \RuntimeException('Appointment insert failed.');
            }
            $db->table('appointments')->where('id', $id)->update(['appointment_number' => sprintf('APT-%06d', $id), 'updated_at' => date('Y-m-d H:i:s')]);
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
    public function cancel(int $appointmentId): ResponseInterface
    {
        return $this->updateAppointment($appointmentId, true);
    }
    public function appointments(): string|RedirectResponse
    {
        $context = $this->context();
        if ($context === null) {
            return redirect()->to(base_url('permission-denied'));
        }
        $filters = $this->listFilters();
        $query = db_connect()->table('appointments')->select('appointments.*, patients.first_name AS patient_first_name, patients.last_name AS patient_last_name, patients.registration_number')->join('patients', 'patients.id = appointments.patient_id')->where('appointments.doctor_id', $context['doctor_id'])->where('appointments.clinic_id', $context['clinic_id'])->where('patients.clinic_id', $context['clinic_id']);
        $this->applySearch($query, $filters['search'], ['appointments.appointment_number', 'patients.first_name', 'patients.last_name', 'appointments.status']);
        [$appointments, $pagination] = $this->window($query->orderBy('appointment_date', 'DESC')->orderBy('appointment_time', 'DESC'), $filters['page'], $filters['page_size']);
        $patientQuery = db_connect()->table('patients')->select('patients.id, patients.first_name, patients.last_name, patients.registration_number');
        $this->applyDoctorPatientScope($patientQuery, $context);
        $patients = $patientQuery->orderBy('patients.first_name')->orderBy('patients.last_name')->limit(200)->get()->getResultArray();
        return view(DOCTOR_VIEW . 'pages/appointments', ['appointments' => $appointments, 'patients' => $patients, 'filters' => $filters, 'pagination' => $pagination]);
    }
    public function followUps(): string|RedirectResponse
    {
        $context = $this->context();
        if ($context === null) {
            return redirect()->to(base_url('permission-denied'));
        }
        $filters = $this->listFilters();
        $query = db_connect()->table('consultations')->select('consultations.*, patients.first_name AS patient_first_name, patients.last_name AS patient_last_name, patients.registration_number')->join('patients', 'patients.id = consultations.patient_id')->where('consultations.doctor_id', $context['doctor_id'])->where('consultations.clinic_id', $context['clinic_id'])->where('patients.clinic_id', $context['clinic_id'])->where('consultations.follow_up_date IS NOT NULL', null, false);
        $this->applySearch($query, $filters['search'], ['patients.first_name', 'patients.last_name', 'consultations.consultation_number']);
        [$followUps, $pagination] = $this->window($query->orderBy('follow_up_date'), $filters['page'], $filters['page_size']);
        $patientQuery = db_connect()->table('patients')->select('patients.id, patients.first_name, patients.middle_name, patients.last_name, patients.registration_number');
        $this->applyDoctorPatientScope($patientQuery, $context);
        $patients = $patientQuery->orderBy('patients.first_name')->orderBy('patients.last_name')->limit(200)->get()->getResultArray();
        $consultations = db_connect()->table('consultations')->select('consultations.id, consultations.consultation_number, consultations.patient_id, consultations.appointment_id, consultations.consultation_date')->join('patients', 'patients.id = consultations.patient_id')->where('consultations.doctor_id', $context['doctor_id'])->where('consultations.clinic_id', $context['clinic_id'])->where('patients.clinic_id', $context['clinic_id'])->orderBy('consultations.consultation_date', 'DESC')->limit(200)->get()->getResultArray();
        $appointments = db_connect()->table('appointments')->select('appointments.id, appointments.appointment_number, appointments.patient_id, appointments.appointment_date')->join('patients', 'patients.id = appointments.patient_id')->where('appointments.doctor_id', $context['doctor_id'])->where('appointments.clinic_id', $context['clinic_id'])->where('patients.clinic_id', $context['clinic_id'])->orderBy('appointments.appointment_date', 'DESC')->limit(200)->get()->getResultArray();
        return view(DOCTOR_VIEW . 'pages/follow-ups', ['followUps' => $followUps, 'patients' => $patients, 'consultations' => $consultations, 'appointments' => $appointments, 'filters' => $filters, 'pagination' => $pagination]);
    }
    public function createFollowUp(): ResponseInterface
    {
        if (!$this->isAjaxPost()) {
            return $this->json(405, false, 'Invalid request.');
        }
        $context = $this->context();
        $patientId = (int) $this->request->getPost('patient_id');
        $consultationId = (int) $this->request->getPost('consultation_id');
        $appointmentId = (int) $this->request->getPost('appointment_id');
        $date = trim((string) $this->request->getPost('follow_up_date'));
        $reason = trim((string) $this->request->getPost('reason'));
        $notes = trim((string) $this->request->getPost('notes'));
        $status = strtolower(trim((string) $this->request->getPost('status')));
        if ($context === null) {
            return $this->json(403, false, 'Doctor scope is invalid.');
        }
        $errors = [];
        if ($patientId <= 0) {
            $errors['patient_id'] = 'Select a patient in your clinic scope.';
        }
        if ($consultationId <= 0) {
            $errors['consultation_id'] = 'Select a consultation reference.';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            $errors['follow_up_date'] = 'A valid follow-up date is required.';
        }
        if ($reason === '' || strlen($reason) > 1000) {
            $errors['reason'] = 'A follow-up reason is required and must be 1000 characters or fewer.';
        }
        if (strlen($notes) > 5000) {
            $errors['notes'] = 'Notes must be 5000 characters or fewer.';
        }
        if (!in_array($status, ['draft', 'in_progress', 'completed', 'cancelled'], true)) {
            $errors['status'] = 'Select a valid follow-up status.';
        }
        if ($errors !== []) {
            return $this->validation($errors);
        }
        $db = db_connect();
        $patient = $db->table('patients')->where('id', $patientId)->where('clinic_id', $context['clinic_id'])->get()->getRowArray();
        $consultation = $db->table('consultations')->where('id', $consultationId)->where('patient_id', $patientId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->get()->getRowArray();
        if ($patient === null) {
            return $this->json(404, false, 'Patient not found in your clinic scope.');
        }
        if ($consultation === null) {
            return $this->json(404, false, 'Consultation not found in your clinic scope.');
        }
        if ($appointmentId > 0) {
            $appointment = $db->table('appointments')->where('id', $appointmentId)->where('patient_id', $patientId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->get()->getRowArray();
            if ($appointment === null) {
                return $this->json(404, false, 'Appointment not found in your clinic scope.');
            }
        } else {
            $appointmentId = (int) ($consultation['appointment_id'] ?? 0);
        }
        // A follow-up is an addition to the consultation record. Do not
        // downgrade a finalized consultation when the modal defaults to draft.
        // Non-finalized consultations may still use the selected follow-up
        // status, matching the existing clinical consultation workflow.
        $effectiveStatus = (string) ($consultation['status'] ?? '') === 'completed' ? 'completed' : $status;
        $payload = ['follow_up_date' => $date, 'treatment_plan' => $reason, 'clinical_notes' => $notes !== '' ? $notes : null, 'status' => $effectiveStatus, 'updated_at' => date('Y-m-d H:i:s')];
        if ($appointmentId > 0) {
            $payload['appointment_id'] = $appointmentId;
        }
        if ((string) ($consultation['follow_up_date'] ?? '') === $date && (string) ($consultation['treatment_plan'] ?? '') === $reason && (string) ($consultation['clinical_notes'] ?? '') === ($notes !== '' ? $notes : '') && (string) $consultation['status'] === $effectiveStatus && ($appointmentId === (int) ($consultation['appointment_id'] ?? 0))) {
            return $this->json(200, true, 'Follow-up already recorded.', ['consultation_id' => $consultationId]);
        }
        $db->transBegin();
        try {
            if (!$db->table('consultations')->where('id', $consultationId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->update($payload) || $db->transStatus() === false || !$db->transCommit()) {
                throw new \RuntimeException('Follow-up update failed.');
            }
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Doctor follow-up creation failed: ' . $e->getMessage());
            return $this->json(500, false, 'Follow-up could not be created safely.');
        }
        $this->audit('doctor.follow_up_created', $context, ['consultation_id' => $consultationId, 'patient_id' => $patientId, 'appointment_id' => $appointmentId ?: null, 'status' => $status]);
        return $this->json(200, true, 'Follow-up created successfully.', ['consultation_id' => $consultationId]);
    }
    public function billing(): string|RedirectResponse
    {
        $context = $this->context();
        if ($context === null) {
            return redirect()->to(base_url('permission-denied'));
        }
        $filters = $this->listFilters();
        $query = db_connect()->table('invoices')->select('invoices.invoice_number, invoices.currency, invoices.total, invoices.status, invoices.payment_status, invoices.created_at, patients.first_name AS patient_first_name, patients.last_name AS patient_last_name')->join('appointments', 'appointments.id = invoices.appointment_id')->join('patients', 'patients.id = invoices.patient_id')->where('appointments.doctor_id', $context['doctor_id'])->where('invoices.clinic_id', $context['clinic_id'])->where('patients.clinic_id', $context['clinic_id']);
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
        $filters = $this->listFilters();
        $query = db_connect()->table('prescriptions')->select('prescriptions.*, patients.first_name AS patient_first_name, patients.last_name AS patient_last_name, patients.registration_number')->join('patients', 'patients.id = prescriptions.patient_id')->where('prescriptions.doctor_id', $context['doctor_id'])->where('prescriptions.clinic_id', $context['clinic_id'])->where('patients.clinic_id', $context['clinic_id']);
        $this->applySearch($query, $filters['search'], ['prescriptions.prescription_number', 'patients.first_name', 'patients.last_name', 'prescriptions.status']);
        [$prescriptions, $pagination] = $this->window($query->orderBy('prescription_date', 'DESC'), $filters['page'], $filters['page_size']);
        $items = [];
        if ($prescriptions !== []) {
            foreach (db_connect()->table('prescription_items')->whereIn('prescription_id', array_column($prescriptions, 'id'))->orderBy('id')->get()->getResultArray() as $item) {
                $items[(int) $item['prescription_id']][] = $item;
            }
        }
        return view(DOCTOR_VIEW . 'pages/prescriptions', ['prescriptions' => $prescriptions, 'items' => $items, 'filters' => $filters, 'pagination' => $pagination]);
    }
    private function listFilters(): array
    {
        $size = (int) $this->request->getGet('page_size');
        return ['search' => trim((string) $this->request->getGet('search')), 'page' => max(1, (int) $this->request->getGet('page')), 'page_size' => in_array($size, [10, 15, 25, 50], true) ? $size : 15];
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
            $ok = $db->table('appointments')->where('id', $appointmentId)->update(['status' => 'cancelled', 'cancelled_at' => date('Y-m-d H:i:s'), 'cancelled_by' => (int) (session('user')['id'] ?? 0), 'cancellation_reason' => trim((string) $this->request->getPost('cancellation_reason')) ?: 'Cancelled by doctor', 'updated_at' => date('Y-m-d H:i:s')]);
            $message = 'Appointment cancelled.';
            $action = 'doctor.appointment_cancelled';
        } else {
            $date = trim((string) $this->request->getPost('appointment_date'));
            $time = trim((string) $this->request->getPost('appointment_time'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
                return $this->validation(['appointment_date' => 'Valid date and time are required.']);
            }
            if (($availability = $this->availabilityError($context['doctor_id'], $date, $time)) !== null) {
                return $this->validation(['appointment_date' => $availability]);
            }
            $conflict = $db->table('appointments')->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->where('appointment_date', $date)->where('appointment_time', $time)->where('id !=', $appointmentId)->whereIn('status', ['scheduled', 'confirmed', 'checked_in', 'in_queue'])->countAllResults();
            if ($conflict > 0) {
                return $this->validation(['appointment_time' => 'This time is already booked for you.']);
            }
            $ok = $db->table('appointments')->where('id', $appointmentId)->update(['appointment_date' => $date, 'appointment_time' => $time, 'updated_at' => date('Y-m-d H:i:s')]);
            $message = 'Appointment rescheduled.';
            $action = 'doctor.appointment_rescheduled';
        }
        if (!$ok) {
            return $this->json(500, false, 'Appointment could not be updated safely.');
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
        $appointmentPatients = db_connect()->table('appointments')->select('patient_id')->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id']);
        $registeredPatients = db_connect()->table('patient_clinic_transfers')->select('patient_id')->where('to_clinic_id', $context['clinic_id'])->where('transferred_by', $context['user_id']);
        $query->where('patients.clinic_id', $context['clinic_id'])->groupStart()->whereIn('patients.id', $appointmentPatients)->orWhereIn('patients.id', $registeredPatients)->groupEnd();
    }
    private function isAjaxPost(): bool
    {
        return $this->request->isAJAX() && $this->request->getMethod(true) === 'POST';
    }
    private function availabilityError(int $doctorId, string $date, string $time): ?string
    {
        $day = (int) date('N', strtotime($date));
        if (db_connect()->table('doctor_schedules')->where('doctor_id', $doctorId)->where('day_of_week', $day)->where('status', 1)->where('start_time <=', $time)->where('end_time >=', $time)->countAllResults() < 1) {
            return 'The selected time is outside the doctor availability.';
        }
        return db_connect()->table('doctor_leaves')->where('doctor_id', $doctorId)->where('status', 1)->where('start_date <=', $date)->where('end_date >=', $date)->countAllResults() > 0 ? 'The doctor is unavailable on the selected date.' : null;
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
}
