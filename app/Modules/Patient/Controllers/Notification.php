<?php

namespace Patient\Controllers;

use Admin\Models\PatientModel;
use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Patient\Models\PatientNotificationModel;
final class Notification extends BaseController
{
    protected PatientNotificationModel $notifications;
    protected PatientModel $patients;
    public function __construct()
    {
        $this->notifications = new PatientNotificationModel();
        $this->patients = new PatientModel();
    }
    public function index(): string|RedirectResponse
    {
        $context = $this->context();
        if ($context === null) {
            return redirect()->to(base_url('permission-denied'));
        }
        $pageSize = (int) ($this->request->getGet('page_size') ?? 15);
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $search = trim((string) $this->request->getGet('search'));
        $status = trim((string) $this->request->getGet('status'));
        $query = $this->notifications->where('patient_id', $context['id'])->where('clinic_id', $context['clinic_id']);
        if ($search !== '') {
            $query->groupStart()->like('title', $search)->orLike('message', $search)->groupEnd();
        }
        if (in_array($status, ['unread', 'read'], true)) {
            $query->where('status', $status);
        }
        $total = (clone $query)->countAllResults();
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $notifications = $query->orderBy('created_at', 'DESC')->findAll($pageSize, ($page - 1) * $pageSize);
        $unread = $this->notifications->where('patient_id', $context['id'])->where('clinic_id', $context['clinic_id'])->where('status', 'unread')->countAllResults();
        return view(PATIENT_VIEW . 'pages/notifications', ['notifications' => $notifications, 'unread' => $unread, 'filters' => ['search' => $search, 'status' => $status], 'pagination' => ['page' => $page, 'pages' => $pages, 'page_size' => $pageSize, 'total' => $total]]);
    }
    public function read(int $notificationId): ResponseInterface
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
        }
        $context = $this->context();
        if ($context === null) {
            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'Patient scope is unavailable.']);
        }
        $notification = $this->notifications->where('id', $notificationId)->where('patient_id', $context['id'])->where('clinic_id', $context['clinic_id'])->first();
        if ($notification === null) {
            return $this->response->setStatusCode(404)->setJSON(['status' => false, 'msg' => 'Notification not found.']);
        }
        if ($notification['status'] !== 'read' && !$this->notifications->update($notificationId, ['status' => 'read', 'read_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')])) {
            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Notification could not be updated safely.']);
        }
        service('audit')->record('patient.notification_read', ['patient_id' => $context['id'], 'clinic_id' => $context['clinic_id'], 'notification_id' => $notificationId]);
        return $this->response->setJSON(['status' => true, 'msg' => 'Notification marked as read.']);
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
        $patient = $this->patients->select('id, clinic_id')->where('user_id', $userId)->where('status', 1)->first();
        return $patient !== null && (int) $patient['clinic_id'] > 0 ? ['id' => (int) $patient['id'], 'clinic_id' => (int) $patient['clinic_id']] : null;
    }
}
