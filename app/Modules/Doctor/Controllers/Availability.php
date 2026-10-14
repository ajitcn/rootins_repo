<?php

namespace Doctor\Controllers;

use Admin\Models\Doctor\DoctorModel;
use Admin\Models\User;
use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Doctor\Models\DoctorLeave;
use Doctor\Models\DoctorSchedule;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

class Availability extends BaseController
{
    private DoctorSchedule $schedule;

    private DoctorLeave $leave;

    public function __construct()
    {
        $this->schedule = new DoctorSchedule();
        $this->leave = new DoctorLeave();
    }

    public function index()
    {
        $context = $this->context();

        if ($context === null) {
            return redirect()->to('permission-denied');
        }

        $scheduleFilters = [
            'search' => trim((string) $this->request->getGet('schedule_search')),
            'day' => (int) $this->request->getGet('schedule_day'),
            'status' => (string) $this->request->getGet('schedule_status'),
            'page_size' => $this->pageSize($this->request->getGet('schedule_page_size')),
            'page' => max(1, (int) $this->request->getGet('schedule_page')),
        ];
        $leaveFilters = [
            'search' => trim((string) $this->request->getGet('leave_search')),
            'status' => (string) $this->request->getGet('leave_status'),
            'page_size' => $this->pageSize($this->request->getGet('leave_page_size')),
            'page' => max(1, (int) $this->request->getGet('leave_page')),
        ];

        $scheduleQuery = $this->schedule->where('doctor_id', $context['doctor_id'])->where('deleted_at', null);

        if ($scheduleFilters['search'] !== '') {
            $scheduleQuery->groupStart()->like('shift_name', $scheduleFilters['search'])->orLike('start_time', $scheduleFilters['search'])->orLike('end_time', $scheduleFilters['search'])->groupEnd();
        }

        if (in_array($scheduleFilters['day'], range(1, 7), true)) {
            $scheduleQuery->where('day_of_week', $scheduleFilters['day']);
        }

        if ($scheduleFilters['status'] === '0' || $scheduleFilters['status'] === '1') {
            $scheduleQuery->where('status', (int) $scheduleFilters['status']);
        }
        $scheduleTotal = $scheduleQuery->countAllResults(false);
        $schedules = $scheduleQuery->orderBy('day_of_week', 'ASC')->orderBy('start_time', 'ASC')->findAll($scheduleFilters['page_size'], ($scheduleFilters['page'] - 1) * $scheduleFilters['page_size']);

        $leaveQuery = $this->leave->where('doctor_id', $context['doctor_id']);

        if ($leaveFilters['search'] !== '') {
            $leaveQuery->groupStart()->like('leave_type', $leaveFilters['search'])->orLike('reason', $leaveFilters['search'])->groupEnd();
        }

        if (in_array($leaveFilters['status'], ['0', '1', '2'], true)) {
            $leaveQuery->where('status', (int) $leaveFilters['status']);
        }
        $leaveTotal = $leaveQuery->countAllResults(false);
        $leaves = $leaveQuery->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->findAll($leaveFilters['page_size'], ($leaveFilters['page'] - 1) * $leaveFilters['page_size']);

        return view(DOCTOR_VIEW . 'pages/availability', [
            'context' => $context,
            'schedules' => $schedules ?: [],
            'leaves' => $leaves ?: [],
            'scheduleFilters' => $scheduleFilters,
            'leaveFilters' => $leaveFilters,
            'schedulePagination' => $this->pagination($scheduleFilters['page'], $scheduleFilters['page_size'], $scheduleTotal),
            'leavePagination' => $this->pagination($leaveFilters['page'], $leaveFilters['page_size'], $leaveTotal),
            'today' => $this->today(),
            'canOverrideConflict' => $this->canOverrideConflict(),
            'days' => [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'],
        ]);
    }

    public function saveSchedule(): ResponseInterface
    {
        if (!$this->isAjaxPost()) {
            return $this->json(405, false, 'Invalid request.');
        }
        $context = $this->context();

        if ($context === null) {
            return $this->json(403, false, 'Doctor scope is invalid.');
        }
        $id = max(0, (int) $this->request->getPost('schedule_id'));
        $day = (int) $this->request->getPost('day_of_week');
        $start = $this->normalizeTime((string) $this->request->getPost('start_time'));
        $end = $this->normalizeTime((string) $this->request->getPost('end_time'));
        $breakStart = trim((string) $this->request->getPost('break_start_time'));
        $breakEnd = trim((string) $this->request->getPost('break_end_time'));
        $breakStartNormalized = $breakStart === '' ? null : $this->normalizeTime($breakStart);
        $breakEndNormalized = $breakEnd === '' ? null : $this->normalizeTime($breakEnd);
        $errors = [];

        if (!in_array($day, range(1, 7), true)) {
            $errors['day_of_week'] = 'Select a working day.';
        }

        if ($start === null) {
            $errors['start_time'] = 'Enter a valid start time in HH:mm format.';
        }

        if ($end === null) {
            $errors['end_time'] = 'Enter a valid end time in HH:mm format.';
        }

        if ($start !== null && $end !== null && $start >= $end) {
            $errors['end_time'] = 'End time must be later than start time.';
        }

        if ($breakStart !== '' && $breakStartNormalized === null) {
            $errors['break_start_time'] = 'Enter a valid break start time in HH:mm format.';
        }

        if ($breakEnd !== '' && $breakEndNormalized === null) {
            $errors['break_end_time'] = 'Enter a valid break end time in HH:mm format.';
        }

        if (($breakStartNormalized === null) !== ($breakEndNormalized === null)) {
            $errors['break_end_time'] = 'Enter both break times or leave both blank.';
        }

        if ($start !== null && $end !== null && $breakStartNormalized !== null && $breakEndNormalized !== null && ($breakStartNormalized <= $start || $breakEndNormalized >= $end || $breakStartNormalized >= $breakEndNormalized)) {
            $errors['break_start_time'] = 'Break must be inside the working hours.';
        }
        $slotDuration = (int) $this->request->getPost('slot_duration');
        $maxPatients = (int) $this->request->getPost('max_patients');

        if ($slotDuration < 1 || $slotDuration > 480) {
            $errors['slot_duration'] = 'Slot duration must be between 1 and 480 minutes.';
        }

        if ($maxPatients < 0) {
            $errors['max_patients'] = 'Maximum patients cannot be negative.';
        }

        if ($errors !== []) {
            return $this->validation($errors);
        }

        if ($this->schedule->hasOverlap($context['doctor_id'], $day, $start, $end, $id > 0 ? $id : null)) {
            return $this->validation(['start_time' => 'This working time overlaps an existing availability slot.']);
        }

        $existing = $id > 0 ? $this->scopedSchedule($context, $id) : null;

        if ($id > 0 && $existing === null) {
            return $this->json(404, false, 'Availability slot not found.');
        }
        $data = ['doctor_id' => $context['doctor_id'], 'day_of_week' => $day, 'shift_name' => trim((string) $this->request->getPost('shift_name')) ?: null, 'start_time' => $start, 'end_time' => $end, 'break_start_time' => $breakStartNormalized, 'break_end_time' => $breakEndNormalized, 'slot_duration' => $slotDuration, 'max_patients' => $maxPatients, 'status' => $this->request->getPost('status') === '0' ? 0 : 1];

        try {
            $db = db_connect();
            $db->transBegin();

            if ($id > 0) {
                $this->schedule->update($id, $data);
            } else {
                $this->schedule->insert($data);
            }

            if (!$db->transStatus()) {
                throw new \RuntimeException('Availability save failed.');
            }
            $db->transCommit();
            service('audit')->record($id > 0 ? 'doctor.availability_updated' : 'doctor.availability_created', ['schedule_id' => $id ?: (int) $this->schedule->getInsertID(), 'doctor_id' => $context['doctor_id'], 'clinic_id' => $context['clinic_id']]);

            return $this->json(200, true, $id > 0 ? 'Availability updated successfully.' : 'Availability saved successfully.');
        } catch (Throwable $e) {
            db_connect()->transRollback();
            log_message('error', 'Doctor availability save failed: ' . $e->getMessage());

            return $this->json(500, false, 'Availability could not be saved safely.');
        }
    }

    public function cancelSchedule(int $id): ResponseInterface
    {
        $context = $this->context();
        $row = $context ? $this->scopedSchedule($context, $id) : null;

        if (!$this->isAjaxPost()) {
            return $this->json(405, false, 'Invalid request.');
        }

        if ($row === null) {
            return $this->json(404, false, 'Availability slot not found.');
        }
        $this->schedule->update($id, ['status' => 0]);
        service('audit')->record('doctor.availability_cancelled', ['schedule_id' => $id, 'doctor_id' => $context['doctor_id'], 'clinic_id' => $context['clinic_id']]);

        return $this->json(200, true, 'Availability slot disabled.');
    }

    public function saveLeave(): ResponseInterface
    {
        if (!$this->isAjaxPost()) {
            return $this->json(405, false, 'Invalid request.');
        }
        $context = $this->context();

        if ($context === null) {
            return $this->json(403, false, 'Doctor scope is invalid.');
        }
        $id = max(0, (int) $this->request->getPost('leave_id'));
        $start = $this->normalizeDate((string) $this->request->getPost('start_date'));
        $end = $this->normalizeDate((string) $this->request->getPost('end_date'));
        $startTime = $this->normalizeTime((string) $this->request->getPost('start_time'));
        $endTime = $this->normalizeTime((string) $this->request->getPost('end_time'));
        $type = trim((string) $this->request->getPost('leave_type'));
        $reason = trim((string) $this->request->getPost('reason'));
        $errors = [];

        if ($type === '' || mb_strlen($type) > 50) {
            $errors['leave_type'] = 'Enter a leave type up to 50 characters.';
        }

        if ($start === null) {
            $errors['start_date'] = 'Enter a valid date in dd-mm-yyyy format.';
        }

        if ($end === null) {
            $errors['end_date'] = 'Enter a valid date in dd-mm-yyyy format.';
        }
        $startTimeInput = trim((string) $this->request->getPost('start_time'));
        $endTimeInput = trim((string) $this->request->getPost('end_time'));

        if ($startTimeInput !== '' && $startTime === null) {
            $errors['start_time'] = 'Enter a valid start time in HH:mm format.';
        }

        if ($endTimeInput !== '' && $endTime === null) {
            $errors['end_time'] = 'Enter a valid end time in HH:mm format.';
        }

        if (($startTimeInput === '') !== ($endTimeInput === '')) {
            $errors['end_time'] = 'Enter both times or leave both blank for a full-day leave.';
        }

        if ($start !== null && $end !== null && $start > $end) {
            $errors['end_date'] = 'End date must be on or after start date.';
        }

        if ($startTime !== null && $endTime !== null && $startTime >= $endTime) {
            $errors['end_time'] = 'End time must be later than start time.';
        }
        $today = $this->today();

        if ($start !== null && $start < $today) {
            $errors['start_date'] = 'Start date cannot be before today.';
        }

        if ($end !== null && $end < $today) {
            $errors['end_date'] = 'End date cannot be before today.';
        }

        if (mb_strlen($reason) > 2000) {
            $errors['reason'] = 'Reason must be 2000 characters or fewer.';
        }

        if ($errors !== []) {
            return $this->validation($errors);
        }

        if ($this->leave->hasOverlap($context['doctor_id'], $start, $end, $id > 0 ? $id : null)) {
            return $this->validation(['start_date' => 'This leave overlaps an existing leave period.']);
        }
        $existing = $id > 0 ? $this->scopedLeave($context, $id) : null;

        if ($id > 0 && $existing === null) {
            return $this->json(404, false, 'Leave record not found.');
        }
        $appointmentQuery = db_connect()->table('appointments')->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->where('appointment_date >=', $start)->where('appointment_date <=', $end)->whereIn('status', ['scheduled', 'confirmed', 'checked_in', 'in_queue']);

        if ($startTime !== null && $endTime !== null) {
            $appointmentQuery->where('appointment_time >=', $startTime)->where('appointment_time <=', $endTime);
        }
        $appointmentConflict = $appointmentQuery->countAllResults() > 0;
        $overrideConfirmation = trim((string) $this->request->getPost('override_confirmation'));
        $override = $this->canOverrideConflict() && $overrideConfirmation === 'I am authorized to override appointment conflicts';

        if ($appointmentConflict && !$this->canOverrideConflict()) {
            return $this->validation(['leave_date' => 'This leave conflicts with an appointment and cannot be overridden with your permissions.']);
        }

        if ($appointmentConflict && $this->canOverrideConflict() && !$override) {
            return $this->validation(['override_confirmation' => 'I am authorized to override appointment conflicts']);
        }
        $status = $existing !== null ? (int) ($existing['status'] ?? 2) : 2;
        $data = ['doctor_id' => $context['doctor_id'], 'leave_type' => $type, 'start_date' => $start, 'end_date' => $end, 'start_time' => $startTime, 'end_time' => $endTime, 'reason' => $reason !== '' ? $reason : null, 'status' => $status];

        try {
            $db = db_connect();
            $db->transBegin();

            if ($id > 0) {
                $this->leave->update($id, $data);
            } else {
                $this->leave->insert($data);
            }

            if (!$db->transStatus()) {
                throw new \RuntimeException('Leave save failed.');
            }
            $db->transCommit();
            $leaveId = $id ?: (int) $this->leave->getInsertID();
            service('audit')->record($id > 0 ? 'doctor.leave_updated' : 'doctor.leave_created', ['leave_id' => $leaveId, 'doctor_id' => $context['doctor_id'], 'clinic_id' => $context['clinic_id'], 'status' => $status, 'conflict_override' => $override]);

            return $this->json(200, true, $id > 0 ? 'Leave updated and submitted for review.' : 'Leave submitted for approval.');
        } catch (Throwable $e) {
            db_connect()->transRollback();
            log_message('error', 'Doctor leave save failed: ' . $e->getMessage());

            return $this->json(500, false, 'Leave could not be saved safely.');
        }
    }

    public function cancelLeave(int $id): ResponseInterface
    {
        if (!$this->isAjaxPost()) {
            return $this->json(405, false, 'Invalid request.');
        }
        $context = $this->context();
        $row = $context ? $this->scopedLeave($context, $id) : null;

        if ($row === null) {
            return $this->json(404, false, 'Leave record not found.');
        }
        $this->leave->update($id, ['status' => 0]);
        service('audit')->record('doctor.leave_cancelled', ['leave_id' => $id, 'doctor_id' => $context['doctor_id'], 'clinic_id' => $context['clinic_id']]);

        return $this->json(200, true, 'Leave cancelled.');
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

    private function scopedSchedule(array $context, int $id): ?array
    {
        return $this->schedule->where('doctor_id', $context['doctor_id'])->where('deleted_at', null)->find($id);
    }

    private function scopedLeave(array $context, int $id): ?array
    {
        return $this->leave->where('doctor_id', $context['doctor_id'])->find($id);
    }

    private function canOverrideConflict(): bool
    {
        return in_array('doctor.edit', session('permissions') ?? [], true);
    }

    private function isAjaxPost(): bool
    {
        return $this->request->isAJAX() && $this->request->getMethod(true) === 'POST';
    }

    private function pageSize($value): int
    {
        $value = (int) $value;

        return in_array($value, [10, 15, 25, 50], true) ? $value : 15;
    }

    private function pagination(int $page, int $size, int $total): array
    {
        return ['page' => $page, 'page_size' => $size, 'total' => $total, 'pages' => max(1, (int) ceil($total / $size))];
    }

    private function today(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone((string) (config('App')->appTimezone ?: date_default_timezone_get()))))->format('Y-m-d');
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat('!d-m-Y', $value);
        $errors = DateTimeImmutable::getLastErrors();

        return $value !== '' && $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) && $date->format('d-m-Y') === $value ? $date->format('Y-m-d') : null;
    }

    private function normalizeTime(string $value): ?string
    {
        $value = trim($value);

        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
            return null;
        }

        return $value . ':00';
    }

    private function validation(array $errors): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => 'Please correct the highlighted fields.', 'errors' => $errors]);
    }

    private function json(int $statusCode, bool $status, string $message): ResponseInterface
    {
        return $this->response->setStatusCode($statusCode)->setJSON(['status' => $status, 'msg' => $message]);
    }
}
