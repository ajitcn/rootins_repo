<?php

namespace Patient\Controllers;

use Admin\Models\Doctor\DoctorLeaveModel;
use Admin\Models\PatientDocumentModel;
use Admin\Models\PatientModel;
use App\Controllers\BaseController;
use App\Services\Common\R2StorageLibrary;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;
class Portal extends BaseController
{
    private const APPOINTMENT_TYPES = ['in_person', 'video', 'phone'];
    private const PATIENT_EDITABLE_APPOINTMENT_STATUSES = ['scheduled', 'confirmed'];
    protected PatientModel $patients;
    protected PatientDocumentModel $documents;
    protected R2StorageLibrary $storage;
    public function __construct()
    {
        $this->patients = new PatientModel();
        $this->documents = new PatientDocumentModel();
        $this->storage = new R2StorageLibrary();
    }
    public function index(): string|RedirectResponse
    {
        $context = $this->context();
        if ($context === null) {
            return redirect()->to(base_url('permission-denied'));
        }
        $db = db_connect();
        $patientId = $context['patient']['id'];
        $clinicId = $context['patient']['clinic_id'];
        $pageSize = (int) ($this->request->getGet('page_size') ?? 15);
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $search = trim((string) $this->request->getGet('search'));
        $appointmentStatus = trim((string) $this->request->getGet('appointment_status'));
        $appointmentQuery = $db->table('appointments')->select('appointments.*, doctors.first_name AS doctor_first_name, doctors.last_name AS doctor_last_name')->join('doctors', 'doctors.id = appointments.doctor_id')->where('appointments.patient_id', $patientId)->where('appointments.clinic_id', $clinicId)->where('doctors.clinic_id', $clinicId);
        if ($search !== '') {
            $appointmentQuery->groupStart()->like('appointments.appointment_number', $search)->orLike('doctors.first_name', $search)->orLike('doctors.last_name', $search)->orLike('appointments.appointment_type', $search)->groupEnd();
        }
        if ($appointmentStatus !== '') {
            $appointmentQuery->where('appointments.status', $appointmentStatus);
        }
        $appointmentTotal = (clone $appointmentQuery)->countAllResults();
        $appointmentPages = max(1, (int) ceil($appointmentTotal / $pageSize));
        $appointmentPage = min(max(1, (int) ($this->request->getGet('appointments_page') ?? 1)), $appointmentPages);
        $appointments = $appointmentQuery->orderBy('appointments.appointment_date', 'DESC')->orderBy('appointments.appointment_time', 'DESC')->limit($pageSize, ($appointmentPage - 1) * $pageSize)->get()->getResultArray();
        $doctors = $db->table('doctors')->select('id, first_name, last_name, doctor_code, qualification, online_booking')->where('clinic_id', $clinicId)->where('status', 1)->where('online_booking', 1)->orderBy('display_order')->orderBy('first_name')->get()->getResultArray();
        $visitQuery = $db->table('consultations')->select('consultations.consultation_number, consultations.consultation_date, consultations.consultation_time, consultations.chief_complaint, consultations.clinical_notes, consultations.diagnosis, consultations.treatment_plan, consultations.follow_up_date, consultations.status, doctors.first_name AS doctor_first_name, doctors.last_name AS doctor_last_name')->join('doctors', 'doctors.id = consultations.doctor_id')->where('consultations.patient_id', $patientId)->where('consultations.clinic_id', $clinicId)->where('doctors.clinic_id', $clinicId);
        if ($search !== '') {
            $visitQuery->groupStart()->like('consultations.consultation_number', $search)->orLike('doctors.first_name', $search)->orLike('doctors.last_name', $search)->groupEnd();
        }
        $visitTotal = (clone $visitQuery)->countAllResults();
        $visitPages = max(1, (int) ceil($visitTotal / $pageSize));
        $visitPage = min(max(1, (int) ($this->request->getGet('visits_page') ?? 1)), $visitPages);
        $visits = $visitQuery->orderBy('consultations.consultation_date', 'DESC')->limit($pageSize, ($visitPage - 1) * $pageSize)->get()->getResultArray();
        $prescriptionQuery = $db->table('prescriptions')->select('prescriptions.id, prescriptions.prescription_number, prescriptions.prescription_date, prescriptions.notes, prescriptions.status')->where('patient_id', $patientId)->where('clinic_id', $clinicId);
        if ($search !== '') {
            $prescriptionQuery->groupStart()->like('prescription_number', $search)->orLike('status', $search)->groupEnd();
        }
        $prescriptionTotal = (clone $prescriptionQuery)->countAllResults();
        $prescriptionPages = max(1, (int) ceil($prescriptionTotal / $pageSize));
        $prescriptionPage = min(max(1, (int) ($this->request->getGet('prescriptions_page') ?? 1)), $prescriptionPages);
        $prescriptions = $prescriptionQuery->orderBy('prescription_date', 'DESC')->limit($pageSize, ($prescriptionPage - 1) * $pageSize)->get()->getResultArray();
        $items = [];
        foreach ($prescriptions as $prescription) {
            $items[(int) $prescription['id']] = $db->table('prescription_items')->where('prescription_id', $prescription['id'])->orderBy('id')->get()->getResultArray();
        }
        $invoiceQuery = $db->table('invoices')->select('invoice_number, currency, total, status, payment_status, created_at')->where('patient_id', $patientId)->where('clinic_id', $clinicId);
        if ($search !== '') {
            $invoiceQuery->groupStart()->like('invoice_number', $search)->orLike('status', $search)->orLike('payment_status', $search)->groupEnd();
        }
        $invoiceTotal = (clone $invoiceQuery)->countAllResults();
        $invoicePages = max(1, (int) ceil($invoiceTotal / $pageSize));
        $invoicePage = min(max(1, (int) ($this->request->getGet('invoices_page') ?? 1)), $invoicePages);
        $invoices = $invoiceQuery->orderBy('id', 'DESC')->limit($pageSize, ($invoicePage - 1) * $pageSize)->get()->getResultArray();
        $documentQuery = $this->documents->where('patient_id', $patientId);
        if ($search !== '') {
            $documentQuery->groupStart()->like('title', $search)->orLike('document_type', $search)->groupEnd();
        }
        $documentTotal = (clone $documentQuery)->countAllResults();
        $documentPages = max(1, (int) ceil($documentTotal / $pageSize));
        $documentPage = min(max(1, (int) ($this->request->getGet('documents_page') ?? 1)), $documentPages);
        $documents = $documentQuery->orderBy('created_at', 'DESC')->findAll($pageSize, ($documentPage - 1) * $pageSize);
        $unreadNotifications = $db->table('patient_notifications')->where('patient_id', $patientId)->where('clinic_id', $clinicId)->where('status', 'unread')->countAllResults();
        return view(PATIENT_VIEW . 'pages/portal', ['patient' => $context['patient'], 'appointments' => $appointments, 'doctors' => $doctors, 'visits' => $visits, 'prescriptions' => $prescriptions, 'prescriptionItems' => $items, 'documents' => $documents, 'invoices' => $invoices, 'unreadNotifications' => $unreadNotifications, 'filters' => ['search' => $search, 'appointment_status' => $appointmentStatus], 'pagination' => ['appointments' => ['page' => $appointmentPage, 'pages' => $appointmentPages, 'total' => $appointmentTotal], 'visits' => ['page' => $visitPage, 'pages' => $visitPages, 'total' => $visitTotal], 'prescriptions' => ['page' => $prescriptionPage, 'pages' => $prescriptionPages, 'total' => $prescriptionTotal], 'invoices' => ['page' => $invoicePage, 'pages' => $invoicePages, 'total' => $invoiceTotal], 'documents' => ['page' => $documentPage, 'pages' => $documentPages, 'total' => $documentTotal]], 'pageSize' => $pageSize]);
    }
    public function updateProfile(): ResponseInterface
    {
        if (!$this->isAjaxPost()) {
            return $this->json(405, false, 'Invalid request.');
        }
        $context = $this->context();
        if ($context === null) {
            return $this->json(403, false, 'Patient scope is unavailable.');
        }
        $email = trim((string) $this->request->getPost('email'));
        $mobile = trim((string) $this->request->getPost('mobile_number'));
        $address = trim((string) $this->request->getPost('address'));
        $errors = [];
        if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255)) {
            $errors['email'] = 'Email is invalid.';
        }
        if ($mobile !== '' && !preg_match('/^[0-9+() -]{7,20}$/', $mobile)) {
            $errors['mobile_number'] = 'Mobile number is invalid.';
        }
        if (strlen($address) > 2000) {
            $errors['address'] = 'Address is too long.';
        }
        if ($errors !== []) {
            return $this->validation($errors);
        }
        if (!$this->patients->update((int) $context['patient']['id'], ['email' => $email === '' ? null : $email, 'mobile_number' => $mobile === '' ? null : $mobile, 'address' => $address === '' ? null : $address])) {
            return $this->json(500, false, 'Profile could not be updated safely.');
        }
        $this->audit('patient.profile_updated', $context, []);
        return $this->json(200, true, 'Profile updated.');
    }
    public function bookAppointment(): ResponseInterface
    {
        if (!$this->isAjaxPost()) {
            return $this->json(405, false, 'Invalid request.');
        }
        $context = $this->context();
        $doctorId = (int) $this->request->getPost('doctor_id');
        $date = trim((string) $this->request->getPost('appointment_date'));
        $time = trim((string) $this->request->getPost('appointment_time'));
        $type = strtolower(trim((string) $this->request->getPost('appointment_type')));
        $errors = [];
        if ($context === null) {
            return $this->json(403, false, 'Patient scope is unavailable.');
        }
        if ($doctorId <= 0) {
            $errors['doctor_id'] = 'Doctor is required.';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < date('Y-m-d')) {
            $errors['appointment_date'] = 'Appointment date is invalid.';
        }
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
            $errors['appointment_time'] = 'Appointment time is invalid.';
        }
        if (!in_array($type, self::APPOINTMENT_TYPES, true)) {
            $errors['appointment_type'] = 'Consultation type is invalid.';
        }
        if ($errors !== []) {
            return $this->validation($errors);
        }
        $db = db_connect();
        $doctor = $db->table('doctors')->where('id', $doctorId)->where('clinic_id', $context['patient']['clinic_id'])->where('status', 1)->where('online_booking', 1)->get()->getRowArray();
        if ($doctor === null) {
            return $this->json(422, false, 'Doctor is not available for booking.');
        }
        $day = (int) date('N', strtotime($date));
        $schedule = $db->table('doctor_schedules')->where('doctor_id', $doctorId)->where('day_of_week', $day)->where('status', 1)->where('start_time <=', $time)->where('end_time >', $time)->get()->getRowArray();
        if ($schedule === null || (new DoctorLeaveModel())->isOnLeave($doctorId, $date)) {
            return $this->json(409, false, 'Doctor is not available at the selected time.');
        }
        $conflict = $db->table('appointments')->groupStart()->where('doctor_id', $doctorId)->orWhere('patient_id', $context['patient']['id'])->groupEnd()->where('clinic_id', $context['patient']['clinic_id'])->where('appointment_date', $date)->where('appointment_time', $time)->whereNotIn('status', ['cancelled', 'no_show'])->countAllResults();
        if ($conflict > 0) {
            return $this->json(409, false, 'The selected time is already booked.');
        }
        $now = date('Y-m-d H:i:s');
        $db->transBegin();
        try {
            $id = $db->table('appointments')->insert(['appointment_number' => 'TMP-' . bin2hex(random_bytes(8)), 'patient_id' => $context['patient']['id'], 'doctor_id' => $doctorId, 'clinic_id' => $context['patient']['clinic_id'], 'appointment_date' => $date, 'appointment_time' => $time, 'duration_minutes' => (int) ($schedule['slot_duration'] ?? 15), 'appointment_type' => $type, 'status' => 'scheduled', 'created_by_type' => 'patient', 'created_by_id' => $context['user_id'], 'booking_source' => 'patient', 'reason_for_visit' => $this->nullable('reason_for_visit'), 'created_at' => $now, 'updated_at' => $now]);
            if (!$id || !$db->table('appointments')->where('id', $id)->update(['appointment_number' => sprintf('APT-%06d', $id), 'updated_at' => $now]) || !$db->transCommit()) {
                throw new \RuntimeException('Booking transaction failed.');
            }
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Patient booking failed: ' . $e->getMessage());
            return $this->json(500, false, 'Appointment could not be booked safely.');
        }
        $this->audit('patient.appointment_booked', $context, ['appointment_id' => (int) $id, 'doctor_id' => $doctorId]);
        if (!empty($context['patient']['email'])) {
            service('notification')->dispatch(['channel' => 'email', 'recipient' => $context['patient']['email'], 'template' => 'appointment_booked', 'idempotency_key' => 'appointment:' . $id]);
        }
        return $this->json(200, true, 'Appointment booked.', ['appointment_id' => (int) $id]);
    }
    public function rescheduleAppointment(int $appointmentId): ResponseInterface
    {
        if (!$this->isAjaxPost()) {
            return $this->json(405, false, 'Invalid request.');
        }
        $context = $this->context();
        if ($context === null) {
            return $this->json(403, false, 'Patient scope is unavailable.');
        }
        $date = trim((string) $this->request->getPost('appointment_date'));
        $time = trim((string) $this->request->getPost('appointment_time'));
        $errors = [];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < date('Y-m-d')) {
            $errors['appointment_date'] = 'Appointment date is invalid.';
        }
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
            $errors['appointment_time'] = 'Appointment time is invalid.';
        }
        if ($errors !== []) {
            return $this->validation($errors);
        }
        $db = db_connect();
        $appointment = $db->table('appointments')->where('id', $appointmentId)->where('patient_id', $context['patient']['id'])->where('clinic_id', $context['patient']['clinic_id'])->get()->getRowArray();
        if ($appointment === null) {
            return $this->json(404, false, 'Appointment not found.');
        }
        if (!in_array($appointment['status'], self::PATIENT_EDITABLE_APPOINTMENT_STATUSES, true)) {
            return $this->json(409, false, 'This appointment cannot be rescheduled.');
        }
        $doctorId = (int) $appointment['doctor_id'];
        $day = (int) date('N', strtotime($date));
        $schedule = $db->table('doctor_schedules')->where('doctor_id', $doctorId)->where('day_of_week', $day)->where('status', 1)->where('start_time <=', $time)->where('end_time >', $time)->get()->getRowArray();
        if ($schedule === null || (new DoctorLeaveModel())->isOnLeave($doctorId, $date)) {
            return $this->json(409, false, 'Doctor is not available at the selected time.');
        }
        $conflict = $db->table('appointments')->groupStart()->where('doctor_id', $doctorId)->orWhere('patient_id', $context['patient']['id'])->groupEnd()->where('clinic_id', $context['patient']['clinic_id'])->where('appointment_date', $date)->where('appointment_time', $time)->where('id !=', $appointmentId)->whereNotIn('status', ['cancelled', 'no_show'])->countAllResults();
        if ($conflict > 0) {
            return $this->json(409, false, 'The selected time is already booked.');
        }
        if (!$db->table('appointments')->where('id', $appointmentId)->where('patient_id', $context['patient']['id'])->whereIn('status', self::PATIENT_EDITABLE_APPOINTMENT_STATUSES)->update(['appointment_date' => $date, 'appointment_time' => $time, 'duration_minutes' => (int) ($schedule['slot_duration'] ?? 15), 'updated_at' => date('Y-m-d H:i:s')])) {
            return $this->json(500, false, 'Appointment could not be rescheduled safely.');
        }
        $this->audit('patient.appointment_rescheduled', $context, ['appointment_id' => $appointmentId, 'doctor_id' => $doctorId]);
        return $this->json(200, true, 'Appointment rescheduled.');
    }
    public function cancelAppointment(int $appointmentId): ResponseInterface
    {
        if (!$this->isAjaxPost()) {
            return $this->json(405, false, 'Invalid request.');
        }
        $context = $this->context();
        if ($context === null) {
            return $this->json(403, false, 'Patient scope is unavailable.');
        }
        $db = db_connect();
        $appointment = $db->table('appointments')->where('id', $appointmentId)->where('patient_id', $context['patient']['id'])->where('clinic_id', $context['patient']['clinic_id'])->get()->getRowArray();
        if ($appointment === null) {
            return $this->json(404, false, 'Appointment not found.');
        }
        if (!in_array($appointment['status'], self::PATIENT_EDITABLE_APPOINTMENT_STATUSES, true)) {
            return $this->json(409, false, 'This appointment cannot be cancelled.');
        }
        if (!$db->table('appointments')->where('id', $appointmentId)->where('patient_id', $context['patient']['id'])->whereIn('status', self::PATIENT_EDITABLE_APPOINTMENT_STATUSES)->update(['status' => 'cancelled', 'cancelled_at' => date('Y-m-d H:i:s'), 'cancelled_by' => $context['user_id'], 'cancellation_reason' => $this->nullable('cancellation_reason'), 'updated_at' => date('Y-m-d H:i:s')])) {
            return $this->json(500, false, 'Appointment could not be cancelled safely.');
        }
        $this->audit('patient.appointment_cancelled', $context, ['appointment_id' => $appointmentId]);
        return $this->json(200, true, 'Appointment cancelled.');
    }
    public function uploadDocument(): ResponseInterface
    {
        if (!$this->isAjaxPost()) {
            return $this->json(405, false, 'Invalid request.');
        }
        $context = $this->context();
        if ($context === null) {
            return $this->json(403, false, 'Patient scope is unavailable.');
        }
        $validation = $this->validate(['title' => 'required|max_length[255]', 'document_type' => 'required|max_length[100]', 'document' => ['uploaded[document]', 'max_size[document,10240]', 'ext_in[document,pdf,jpg,jpeg,png,doc,docx]']]);
        if (!$validation) {
            return $this->validation($this->validator->getErrors());
        }
        $file = $this->request->getFile('document');
        $key = 'patients/' . $context['patient']['id'] . '/documents/' . $file->getRandomName();
        try {
            $this->storage->uploadFile($key, $file);
            $id = $this->documents->insert(['patient_id' => $context['patient']['id'], 'document_type' => trim((string) $this->request->getPost('document_type')), 'title' => trim((string) $this->request->getPost('title')), 'file_name' => basename($key), 'file_path' => $key, 'file_size' => $file->getSize(), 'mime_type' => $file->getMimeType()]);
            if (!$id) {
                throw new \RuntimeException('Document insert failed.');
            }
        } catch (Throwable $e) {
            try {
                $this->storage->delete($key);
            } catch (Throwable $cleanup) {
                log_message('error', 'Patient document cleanup failed: ' . $cleanup->getMessage());
            }
            log_message('error', 'Patient document upload failed: ' . $e->getMessage());
            return $this->json(500, false, 'Document could not be uploaded safely.');
        }
        $this->audit('patient.document_uploaded', $context, ['document_id' => (int) $id]);
        return $this->json(200, true, 'Document uploaded.');
    }
    public function downloadDocument(int $documentId): RedirectResponse
    {
        $context = $this->context();
        if ($context === null) {
            return redirect()->to(base_url('permission-denied'));
        }
        $document = $this->documents->where('id', $documentId)->where('patient_id', $context['patient']['id'])->first();
        if ($document === null || empty($document['file_path'])) {
            return redirect()->back()->with('error', 'Document not found.');
        }
        $this->audit('patient.document_downloaded', $context, ['document_id' => $documentId]);
        return redirect()->to($this->storage->getTemporaryUrl($document['file_path']));
    }
    private function context(): ?array
    {
        if (!in_array('Patient', session('roles') ?? [], true)) {
            return null;
        }
        $userId = (int) ((session('user') ?? [])['id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }
        $patient = $this->patients->select('patients.*, clinics.clinic_name')->join('clinics', 'clinics.id = patients.clinic_id')->where('patients.user_id', $userId)->where('patients.status', 1)->where('clinics.status', 1)->first();
        return $patient !== null && (int) $patient['clinic_id'] > 0 ? ['user_id' => $userId, 'patient' => $patient] : null;
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
    private function json(int $code, bool $status, string $message, array $data = []): ResponseInterface
    {
        $payload = ['status' => $status, 'msg' => $message];
        if ($data !== []) {
            $payload['data'] = $data;
        }
        return $this->response->setStatusCode($code)->setJSON($payload);
    }
    private function validation(array $errors): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => (string) reset($errors), 'errors' => $errors]);
    }
    private function audit(string $action, array $context, array $data): void
    {
        service('audit')->record($action, array_merge(['patient_id' => $context['patient']['id'], 'clinic_id' => $context['patient']['clinic_id']], $data));
    }
}
