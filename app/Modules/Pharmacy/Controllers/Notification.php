<?php

namespace Pharmacy\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use Pharmacy\Services\PharmacyNotificationService;
use Pharmacy\Services\PharmacyScope;

final class Notification extends BaseController
{
    private PharmacyScope $scope;

    private PharmacyNotificationService $notifications;

    public function __construct()
    {
        $this->scope = new PharmacyScope();
        $this->notifications = new PharmacyNotificationService($this->scope);
    }

    public function index(): string|ResponseInterface
    {
        if ($this->scope->clinicId() === null || !$this->scope->can('pharmacy.notification.view')) {
            return redirect()->to(base_url('permission-denied'));
        }

        try {
            $input = $this->request->getGet();

            return view(PHARMACY_VIEW . 'pages/notifications/index', $this->notifications->list(is_array($input) ? $input : []));
        } catch (\Throwable $e) {
            log_message('error', 'Pharmacy notification inbox failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setBody('Notifications could not be loaded safely.');
        }
    }

    public function unreadCount(): ResponseInterface
    {
        if ($this->scope->clinicId() === null || !$this->scope->can('pharmacy.notification.view')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'Permission denied.']);
        }

        return $this->response->setJSON(['status' => true, 'data' => ['unread_count' => $this->notifications->unreadCount()]]);
    }

    public function read(int $id): ResponseInterface
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
        }
        $result = $this->notifications->markRead($id);

        return $this->response->setStatusCode($result['http'])->setJSON($result['body']);
    }

    public function readAll(): ResponseInterface
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
        }
        $result = $this->notifications->markAllRead();

        return $this->response->setStatusCode($result['http'])->setJSON($result['body']);
    }
}
