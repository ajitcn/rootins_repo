<?php

namespace Doctor\Controllers;

use Admin\Models\ConsultationModel;
use Admin\Models\Doctor\DoctorLeaveModel;
use Admin\Models\Doctor\DoctorScheduleModel;
use Admin\Models\PrescriptionItemModel;
use Admin\Models\PrescriptionModel;
use Admin\Models\User;
use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;
class Clinical extends BaseController
{
    private const PRESCRIPTION_STATES = ['draft', 'submitted'];
    private const QUEUE_TRANSITIONS = ['scheduled' => ['scheduled', 'confirmed', 'checked_in', 'cancelled', 'no_show'], 'confirmed' => ['confirmed', 'checked_in', 'cancelled', 'no_show'], 'checked_in' => ['checked_in', 'in_queue', 'cancelled', 'no_show'], 'in_queue' => ['in_queue', 'completed', 'cancelled', 'no_show'], 'completed' => ['completed'], 'cancelled' => ['cancelled'], 'no_show' => ['no_show']];
    protected ConsultationModel $consultations;
    protected PrescriptionModel $prescriptions;
    protected PrescriptionItemModel $prescriptionItems;
    public function __construct()
    {
        $this->consultations = new ConsultationModel();
        $this->prescriptions = new PrescriptionModel();
        $this->prescriptionItems = new PrescriptionItemModel();
    }
    public function index(): string|RedirectResponse
    {
        $context = $this->context();
        if ($context === null) {
            return redirect()->to(base_url('permission-denied'));
        }
        $db = db_connect();
        $today = date('Y-m-d');
        $pageSize = (int) $this->request->getGet('page_size');
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $page = max(1, (int) $this->request->getGet('page'));
        $appointmentQuery = $db->table('appointments')->select('appointments.*, patients.registration_number, patients.first_name AS patient_first_name, patients.middle_name AS patient_middle_name, patients.last_name AS patient_last_name, consultations.id AS consultation_id, consultations.status AS consultation_status')->join('patients', 'patients.id = appointments.patient_id')->join('consultations', 'consultations.appointment_id = appointments.id', 'left')->where('appointments.doctor_id', $context['doctor_id'])->where('appointments.clinic_id', $context['clinic_id'])->where('patients.clinic_id', $context['clinic_id'])->where('appointments.appointment_date', $today);
        $appointmentTotal = $appointmentQuery->countAllResults(false);
        $appointmentPages = max(1, (int) ceil($appointmentTotal / $pageSize));
        $page = min($page, $appointmentPages);
        $appointments = $appointmentQuery->orderBy('appointments.appointment_date')->orderBy('appointments.queue_position')->orderBy('appointments.appointment_time')->limit($pageSize, ($page - 1) * $pageSize)->get()->getResultArray();
        $historyQuery = $db->table('consultations')->select('consultations.consultation_number, consultations.consultation_date, consultations.status, consultations.follow_up_date, patients.first_name AS patient_first_name, patients.last_name AS patient_last_name')->join('patients', 'patients.id = consultations.patient_id')->where('consultations.doctor_id', $context['doctor_id'])->where('consultations.clinic_id', $context['clinic_id'])->where('patients.clinic_id', $context['clinic_id']);
        $historyTotal = $historyQuery->countAllResults(false);
        $historyPages = max(1, (int) ceil($historyTotal / $pageSize));
        $history = $historyQuery->orderBy('consultations.consultation_date', 'DESC')->orderBy('consultations.id', 'DESC')->limit($pageSize, ($page - 1) * $pageSize)->get()->getResultArray();
        return view(DOCTOR_VIEW . 'pages/clinical', ['doctor' => $context['doctor'], 'appointments' => $appointments, 'history' => $history, 'pagination' => ['page' => $page, 'pages' => $appointmentPages, 'page_size' => $pageSize, 'total' => $appointmentTotal], 'historyPagination' => ['page' => $page, 'pages' => $historyPages, 'page_size' => $pageSize, 'total' => $historyTotal], 'filters' => ['page' => $page, 'page_size' => $pageSize], 'schedules' => (new DoctorScheduleModel())->where('doctor_id', $context['doctor_id'])->where('status', 1)->orderBy('day_of_week')->orderBy('start_time')->findAll(), 'leaves' => (new DoctorLeaveModel())->getUpcomingLeaves($context['doctor_id'])]);
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
        $errors = $this->textErrors(['chief_complaint' => 20000, 'clinical_notes' => 30000, 'diagnosis' => 20000, 'treatment_plan' => 20000]);
        $date = trim((string) $this->request->getPost('consultation_date')) ?: date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $errors['consultation_date'] = 'Consultation date is invalid.';
        }
        $appointment = db_connect()->table('appointments')->where('id', $appointmentId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->get()->getRowArray();
        if ($appointment === null) {
            $errors['appointment_id'] = 'Appointment is not in the assigned doctor scope.';
        }
        if ($errors !== []) {
            return $this->validation($errors);
        }
        $existing = $this->consultations->where('appointment_id', $appointmentId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->first();
        if ($existing !== null && $existing['status'] === 'completed') {
            return $this->json(409, false, 'Completed consultations cannot be modified.');
        }
        $data = ['patient_id' => (int) $appointment['patient_id'], 'doctor_id' => $context['doctor_id'], 'clinic_id' => $context['clinic_id'], 'appointment_id' => $appointmentId, 'consultation_date' => $date, 'consultation_time' => $this->nullable('consultation_time'), 'chief_complaint' => $this->nullable('chief_complaint'), 'clinical_notes' => $this->nullable('clinical_notes'), 'diagnosis' => $this->nullable('diagnosis'), 'treatment_plan' => $this->nullable('treatment_plan'), 'follow_up_date' => $this->nullable('follow_up_date'), 'status' => $status, 'updated_at' => date('Y-m-d H:i:s')];
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
        if ($context === null || $consultationId <= 0 || !in_array($status, self::PRESCRIPTION_STATES, true)) {
            return $this->json(422, false, 'Consultation and prescription status are required.');
        }
        $consultation = $this->consultations->where('id', $consultationId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->first();
        if ($consultation === null) {
            return $this->json(404, false, 'Consultation not found in the assigned clinic.');
        }
        if ($status === 'submitted' && $consultation['status'] !== 'completed') {
            return $this->json(409, false, 'Consultation must be completed before prescription submission.');
        }
        $posted = $this->request->getPost('items');
        $errors = [];
        $items = [];
        if (!is_array($posted) || $posted === []) {
            $errors['items'] = 'At least one prescription item is required.';
        }
        foreach ((array) $posted as $index => $item) {
            if (!is_array($item)) {
                $errors['items'] = 'Prescription items are invalid.';
                continue;
            }
            $name = trim((string) ($item['medicine_name'] ?? ''));
            $quantity = trim((string) ($item['quantity'] ?? ''));
            if ($name === '' || strlen($name) > 255) {
                $errors["items.{$index}.medicine_name"] = 'Medicine name is required and must be valid.';
            }
            if ($quantity !== '' && (!ctype_digit($quantity) || (int) $quantity <= 0)) {
                $errors["items.{$index}.quantity"] = 'Quantity must be a positive whole number.';
            }
            $items[] = ['medicine_name' => $name, 'dosage' => $this->item($item, 'dosage', 100), 'frequency' => $this->item($item, 'frequency', 100), 'duration' => $this->item($item, 'duration', 50), 'duration_unit' => $this->item($item, 'duration_unit', 30), 'instructions' => $this->item($item, 'instructions', 65535), 'quantity' => $quantity === '' ? null : (int) $quantity, 'notes' => $this->item($item, 'notes', 65535)];
        }
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
    private function item(array $item, string $field, int $max): ?string
    {
        $value = trim((string) ($item[$field] ?? ''));
        return $value === '' ? null : substr($value, 0, $max);
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
