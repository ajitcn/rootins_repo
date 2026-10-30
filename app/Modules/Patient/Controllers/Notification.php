<?php

namespace Patient\Controllers;

use App\Controllers\BaseController;
use App\Services\PatientScope;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Patient\Models\PatientNotificationModel;

final class Notification extends BaseController
{
    protected PatientNotificationModel $notifications;

    protected PatientScope $scope;

    public function __construct()
    {
        $this->notifications = new PatientNotificationModel();
        $this->scope = new PatientScope();
    }

    public function index(): string|RedirectResponse
    {
        $context = $this->scope->current();

        if ($context === null) {
            return redirect()->to(base_url('permission-denied'));
        }
        $pageSize = (int) ($this->request->getGet('page_size') ?? 15);
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $search = trim((string) $this->request->getGet('search'));
        $status = trim((string) $this->request->getGet('status'));
        $type = trim((string) $this->request->getGet('type'));
        $dateFromInput = trim((string) $this->request->getGet('date_from'));
        $dateToInput = trim((string) $this->request->getGet('date_to'));
        $dateFrom = $this->parseDate($dateFromInput);
        $dateTo = $this->parseDate($dateToInput);
        $isGlobalPatient = (int) ($context['clinic_id'] ?? 0) <= 0;
        $query = $this->notifications->where('patient_id', $context['patient_id']);
        if (!$isGlobalPatient) $query->where('clinic_id', $context['clinic_id']);

        if ($search !== '') {
            $safeSearch = db_connect()->escapeLikeString($search);
            $query->groupStart()
                ->like('title', $search)
                ->orLike('message', $search)
                ->orLike('related_entity_type', $search)
                ->orWhere("EXISTS (SELECT 1 FROM medicine_orders related_orders WHERE related_orders.id = patient_notifications.related_entity_id AND patient_notifications.related_entity_type = 'order' AND related_orders.patient_id = {$context['patient_id']} " . ($isGlobalPatient ? '' : "AND related_orders.clinic_id = {$context['clinic_id']} ") . "AND related_orders.order_number LIKE '%{$safeSearch}%' ESCAPE '!')", null, false)
                ->orWhere("EXISTS (SELECT 1 FROM invoices related_invoices WHERE related_invoices.id = patient_notifications.related_entity_id AND patient_notifications.related_entity_type = 'invoice' AND related_invoices.patient_id = {$context['patient_id']} " . ($isGlobalPatient ? '' : "AND related_invoices.clinic_id = {$context['clinic_id']} ") . "AND related_invoices.invoice_number LIKE '%{$safeSearch}%' ESCAPE '!')", null, false)
                ->orWhere("EXISTS (SELECT 1 FROM prescriptions related_prescriptions WHERE related_prescriptions.id = patient_notifications.related_entity_id AND patient_notifications.related_entity_type = 'prescription' AND related_prescriptions.patient_id = {$context['patient_id']} " . ($isGlobalPatient ? '' : "AND related_prescriptions.clinic_id = {$context['clinic_id']} ") . "AND related_prescriptions.prescription_number LIKE '%{$safeSearch}%' ESCAPE '!')", null, false)
                ->groupEnd();
        }

        if (in_array($status, ['unread', 'read'], true)) {
            $query->where('status', $status);
        } else {
            $status = '';
        }

        if ($type !== '') {
            $query->where('related_entity_type', $type);
        }

        if ($dateFrom !== null) {
            $query->where('created_at >=', $dateFrom . ' 00:00:00');
        } else {
            $dateFromInput = '';
        }

        if ($dateTo !== null) {
            $query->where('created_at <=', $dateTo . ' 23:59:59');
        } else {
            $dateToInput = '';
        }
        $total = (clone $query)->countAllResults();
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $notifications = $query->orderBy('created_at', 'DESC')->findAll($pageSize, ($page - 1) * $pageSize);

        foreach ($notifications as &$notification) {
            $notification['created_display'] = !empty($notification['created_at']) ? date('d-m-Y H:i', strtotime((string) $notification['created_at'])) : 'Not recorded';
            $notification['related_label'] = ucwords(str_replace('_', ' ', (string) ($notification['related_entity_type'] ?? 'Notification')));
            $notification['related_url'] = $this->relatedUrl($notification, $context);
        }
        unset($notification);
        $unreadQuery = $this->notifications->where('patient_id', $context['patient_id'])->where('status', 'unread');
        $typesQuery = $this->notifications->select('related_entity_type')->distinct()->where('patient_id', $context['patient_id'])->where('related_entity_type IS NOT NULL', null, false);
        if (!$isGlobalPatient) { $unreadQuery->where('clinic_id', $context['clinic_id']); $typesQuery->where('clinic_id', $context['clinic_id']); }
        $unread = $unreadQuery->countAllResults();
        $types = $typesQuery->orderBy('related_entity_type')->findAll();

        return view(PATIENT_VIEW . 'pages/notifications', ['notifications' => $notifications, 'unread' => $unread, 'types' => $types, 'filters' => ['search' => $search, 'status' => $status, 'type' => $type, 'date_from' => $dateFromInput, 'date_to' => $dateToInput, 'page_size' => $pageSize], 'pagination' => ['page' => $page, 'pages' => $pages, 'page_size' => $pageSize, 'total' => $total]]);
    }

    public function read(int $notificationId): ResponseInterface
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
        }
        $context = $this->scope->current();

        if ($context === null) {
            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'Patient scope is unavailable.']);
        }
        $notificationQuery = $this->notifications->where('id', $notificationId)->where('patient_id', $context['patient_id']);
        if ((int) ($context['clinic_id'] ?? 0) > 0) $notificationQuery->where('clinic_id', $context['clinic_id']);
        $notification = $notificationQuery->first();

        if ($notification === null) {
            return $this->response->setStatusCode(404)->setJSON(['status' => false, 'msg' => 'Notification not found.']);
        }

        if ($notification['status'] !== 'read' && !$this->notifications->update($notificationId, ['status' => 'read', 'read_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')])) {
            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Notification could not be updated safely.']);
        }
        service('audit')->record('patient.notification_read', ['patient_id' => $context['patient_id'], 'clinic_id' => $context['clinic_id'], 'notification_id' => $notificationId]);

        return $this->response->setJSON(['status' => true, 'msg' => 'Notification marked as read.']);
    }

    public function unreadCount(): ResponseInterface
    {
        $context = $this->scope->current();

        if ($context === null) {
            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'Patient scope is unavailable.']);
        }

        $countQuery = $this->notifications->where('patient_id', $context['patient_id'])->where('status', 'unread');
        if ((int) ($context['clinic_id'] ?? 0) > 0) $countQuery->where('clinic_id', $context['clinic_id']);
        $count = $countQuery->countAllResults();

        return $this->response->setJSON(['status' => true, 'unread' => $count]);
    }

    public function readAll(): ResponseInterface
    {
        if (! $this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') {
            return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
        }

        $context = $this->scope->current();

        if ($context === null) {
            return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'Patient scope is unavailable.']);
        }

        $updatedQuery = $this->notifications->where('patient_id', $context['patient_id'])->where('status', 'unread');
        if ((int) ($context['clinic_id'] ?? 0) > 0) $updatedQuery->where('clinic_id', $context['clinic_id']);
        $updated = (clone $updatedQuery)->countAllResults();
        $updatedQuery->set(['status' => 'read', 'read_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')])->update();
        service('audit')->record('patient.notifications_read_all', ['patient_id' => $context['patient_id'], 'clinic_id' => $context['clinic_id'], 'count' => $updated]);

        return $this->response->setJSON(['status' => true, 'msg' => 'Notifications marked as read.']);
    }

    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!d-m-Y', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private function relatedUrl(array $notification, array $context): ?string
    {
        $type = (string) ($notification['related_entity_type'] ?? '');
        $id = (int) ($notification['related_entity_id'] ?? 0);

        if ($id <= 0) {
            return null;
        }

        $db = db_connect();

        if ($type === 'order' && $this->ownsEntity($db->table('medicine_orders'), $id, $context, 'clinic_id')) {
            return base_url('patient/orders?order_id=' . $id);
        }

        if ($type === 'invoice' && $this->ownsEntity($db->table('invoices'), $id, $context, 'clinic_id')) {
            return base_url('patient/invoices?invoice_id=' . $id);
        }

        if ($type === 'prescription' && $this->ownsEntity($db->table('prescriptions'), $id, $context, 'clinic_id')) {
            return base_url('patient/prescriptions?prescription_id=' . $id);
        }

        return null;
    }

    private function ownsEntity(object $builder, int $id, array $context, string $clinicColumn): bool
    {
        $builder->where('id', $id)->where('patient_id', $context['patient_id']);
        if ((int) ($context['clinic_id'] ?? 0) > 0) $builder->where($clinicColumn, $context['clinic_id']);
        return $builder->countAllResults() > 0;
    }

}
