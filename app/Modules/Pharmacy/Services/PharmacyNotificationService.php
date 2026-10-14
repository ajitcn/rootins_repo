<?php

namespace Pharmacy\Services;

use Pharmacy\Models\PharmacyNotificationModel;

final class PharmacyNotificationService
{
    public function __construct(private readonly PharmacyScope $scope, private readonly PharmacyNotificationModel $model = new PharmacyNotificationModel())
    {
    }

    public function create(string $type, string $title, string $message, ?string $relatedType = null, ?int $relatedId = null, ?string $eventKey = null): ?int
    {
        $context = $this->context();

        if ($context === null) {
            return null;
        }
        $eventKey = $eventKey ? substr(trim($eventKey), 0, 190) : null;

        if ($eventKey !== null && $this->firstScoped(['event_key' => $eventKey]) !== null) {
            return null;
        }

        try {
            $id = $this->model->insert([
                'clinic_id' => $context['clinic_id'], 'recipient_user_id' => $context['user_id'], 'recipient_role' => $context['role'],
                'type' => substr(trim($type), 0, 80), 'title' => substr(trim($title), 0, 160), 'message' => trim($message),
                'related_entity_type' => $relatedType ? substr(trim($relatedType), 0, 60) : null,
                'related_entity_id' => $relatedId ?: null, 'event_key' => $eventKey,
            ], true);

            if ($id) {
                $this->scope->audit('pharmacy.notification_created', ['notification_id' => (int) $id, 'type' => $type, 'related_entity_type' => $relatedType, 'related_entity_id' => $relatedId, 'event_key' => $eventKey]);
            }

            return $id ? (int) $id : null;
        } catch (\Throwable $e) {
            log_message('error', 'Pharmacy notification create failed: ' . $e->getMessage());

            return null;
        }
    }

    public function list(array $input): array
    {
        $context = $this->context();
        $requestedPageSize = (int) ($input['page_size'] ?? 15);
        $pageSize = in_array($requestedPageSize, [10, 15, 25, 50], true) ? $requestedPageSize : 15;
        $page = max(1, (int) ($input['page'] ?? 1));
        $search = trim((string) ($input['search'] ?? ''));
        $status = (string) ($input['status'] ?? '');
        $type = trim((string) ($input['type'] ?? ''));

        if ($context === null) {
            return ['rows' => [], 'filters' => compact('search', 'status', 'type', 'pageSize'), 'pagination' => ['page' => 1, 'pages' => 1, 'page_size' => $pageSize, 'total' => 0], 'unread' => 0, 'types' => []];
        }
        $query = $this->scoped();

        if ($search !== '') {
            $query->groupStart()->like('title', $search)->orLike('message', $search)->orLike('type', $search)->groupEnd();
        }

        if ($status === 'unread') {
            $query->where('read_at IS NULL', null, false);
        } elseif ($status === 'read') {
            $query->where('read_at IS NOT NULL', null, false);
        }

        if ($type !== '') {
            $query->where('type', $type);
        }
        $total = (clone $query)->countAllResults();
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $rows = $query->orderBy('created_at', 'DESC')->get($pageSize, ($page - 1) * $pageSize)->getResultArray();
        $rows = array_map(fn (array $row): array => $this->normalize($row), $rows);
        $types = array_column($this->scoped()->select('type')->distinct()->orderBy('type', 'ASC')->get()->getResultArray(), 'type');

        return ['rows' => $rows, 'filters' => compact('search', 'status', 'type', 'pageSize'), 'pagination' => ['page' => $page, 'pages' => $pages, 'page_size' => $pageSize, 'total' => $total], 'unread' => $this->unreadCount(), 'types' => $types];
    }

    public function unreadCount(): int
    {
        return $this->context() === null ? 0 : (int) $this->scoped()->where('read_at IS NULL', null, false)->countAllResults();
    }

    public function markRead(int $id): array
    {
        $row = $this->firstScoped(['id' => $id]);

        if (!$row) {
            return ['http' => 404, 'body' => ['status' => false, 'msg' => 'Notification not found.']];
        }

        if ($row['read_at'] === null) {
            $this->model->update($id, ['read_at' => date('Y-m-d H:i:s')]);
            $this->scope->audit('pharmacy.notification_read', ['notification_id' => $id]);
        }

        return ['http' => 200, 'body' => ['status' => true, 'msg' => 'Notification marked as read.']];
    }

    public function markAllRead(): array
    {
        if ($this->context() === null) {
            return ['http' => 403, 'body' => ['status' => false, 'msg' => 'Pharmacy clinic scope is not assigned.']];
        }
        $count = $this->scoped()->where('read_at IS NULL', null, false)->countAllResults();

        if ($count > 0) {
            $this->scoped()->set(['read_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')])->where('read_at IS NULL', null, false)->update();
            $this->scope->audit('pharmacy.notifications_read_all', ['count' => $count]);
        }

        return ['http' => 200, 'body' => ['status' => true, 'msg' => 'Notifications marked as read.', 'data' => ['count' => $count]]];
    }

    private function normalize(array $row): array
    {
        $row['status'] = $row['read_at'] ? 'read' : 'unread';
        $row['created_display'] = $row['created_at'] ? date('d-m-Y H:i', strtotime((string) $row['created_at'])) : 'Not recorded';
        $row['related_url'] = $this->relatedUrl($row);

        return $row;
    }

    private function relatedUrl(array $row): ?string
    {
        $type = (string) ($row['related_entity_type'] ?? '');
        $id = (int) ($row['related_entity_id'] ?? 0);

        if ($id <= 0) {
            return null;
        }
        $rules = ['prescription' => ['pharmacy.prescription.view', 'pharmacy/prescriptions'], 'order' => ['pharmacy.order.view', 'pharmacy/orders'], 'billing' => ['pharmacy.billing.view', 'pharmacy/billing'], 'delivery' => ['pharmacy.delivery.view', 'pharmacy/delivery'], 'inventory' => ['pharmacy.inventory.view', 'pharmacy/inventory']];

        if (!isset($rules[$type]) || !$this->scope->can($rules[$type][0])) {
            return null;
        }
        $record = $type === 'prescription' ? $this->scope->prescription($id) : ($type === 'order' || $type === 'billing' || $type === 'delivery' ? $this->scope->order($id) : $this->scope->inventoryBatch($id));

        return $record ? base_url($rules[$type][1]) : null;
    }

    private function scoped()
    {
        $context = $this->context();

        return $this->model->builder()->where('clinic_id', $context['clinic_id'] ?? 0)->where('recipient_user_id', $context['user_id'] ?? 0)->where('recipient_role', $context['role'] ?? 'Pharmacy Assistant');
    }

    /**
     * Query-builder instances do not expose the model's first() helper in CI4.
     * Keep notification reads clinic/user scoped while using the builder API.
     */
    private function firstScoped(array $where): ?array
    {
        $row = $this->scoped()->where($where)->get(1)->getRowArray();

        return $row === [] ? null : $row;
    }

    private function context(): ?array
    {
        $clinic = $this->scope->clinicId();
        $user = $this->scope->user();

        if ($clinic === null || !$user || (int) ($user['id'] ?? 0) <= 0) {
            return null;
        }
        $roles = session('roles') ?? [];

        return ['clinic_id' => $clinic, 'user_id' => (int) $user['id'], 'role' => 'Pharmacy Assistant'];
    }
}
