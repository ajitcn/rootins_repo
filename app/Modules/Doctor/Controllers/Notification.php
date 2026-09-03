<?php

namespace Doctor\Controllers;

use Admin\Models\Doctor\DoctorModel;
use Admin\Models\User;
use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Doctor\Models\DoctorNotification;
final class Notification extends BaseController
{
    protected DoctorNotification $notifications;
    public function __construct()
    {
        $this->notifications = new DoctorNotification();
    }
    public function index(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $context = $this->context();
        if ($context === null) {
            return redirect()->to(base_url('permission-denied'));
        }
        $search = trim((string) $this->request->getGet('search'));
        $status = (string) $this->request->getGet('status');
        if (!in_array($status, ['', 'read', 'unread'], true)) {
            $status = '';
        }
        $size = (int) $this->request->getGet('page_size');
        $size = in_array($size, [10, 15, 25, 50], true) ? $size : 15;
        $page = max(1, (int) $this->request->getGet('page'));
        $query = $this->notifications->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id']);
        if ($search !== '') {
            $query->groupStart()->like('title', $search)->orLike('message', $search)->groupEnd();
        }
        if ($status !== '') {
            $query->where('status', $status);
        }
        $total = $query->countAllResults(false);
        $pages = max(1, (int) ceil($total / $size));
        $page = min($page, $pages);
        $notifications = $query->orderBy('created_at', 'DESC')->findAll($size, ($page - 1) * $size);
        $unread = $this->notifications->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->where('status', 'unread')->countAllResults();
        return view(DOCTOR_VIEW . 'pages/notifications', ['notifications' => $notifications, 'unread' => $unread, 'filters' => ['search' => $search, 'status' => $status], 'pagination' => ['page' => $page, 'pages' => $pages, 'page_size' => $size, 'total' => $total]]);
    }
    public function read(int $notificationId): ResponseInterface
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
        }
        $context = $this->context();
        if ($context === null) {
            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'Doctor scope is invalid.']);
        }
        $notification = $this->notifications->where('id', $notificationId)->where('doctor_id', $context['doctor_id'])->where('clinic_id', $context['clinic_id'])->first();
        if ($notification === null) {
            return $this->response->setStatusCode(404)->setJSON(['status' => false, 'msg' => 'Notification not found.']);
        }
        if ($notification['status'] === 'read') {
            return $this->response->setJSON(['status' => true, 'msg' => 'Notification was already read.', 'data' => ['duplicate' => true]]);
        }
        if (!$this->notifications->update($notificationId, ['status' => 'read', 'read_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')])) {
            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Notification could not be updated safely.']);
        }
        service('audit')->record('doctor.notification_read', ['doctor_id' => $context['doctor_id'], 'clinic_id' => $context['clinic_id'], 'notification_id' => $notificationId]);
        return $this->response->setJSON(['status' => true, 'msg' => 'Notification marked as read.']);
    }
    private function context(): ?array
    {
        if (!in_array('Doctor', session('roles') ?? [], true)) {
            return null;
        }
        $user = (new User())->select('doctor_id')->where('id', (int) (session('user')['id'] ?? 0))->where('status', 1)->first();
        $doctor = (new DoctorModel())->select('id, clinic_id')->where('id', (int) ($user['doctor_id'] ?? 0))->where('status', 1)->first();
        return $doctor && (int) $doctor['clinic_id'] > 0 ? ['doctor_id' => (int) $doctor['id'], 'clinic_id' => (int) $doctor['clinic_id']] : null;
    }
}
