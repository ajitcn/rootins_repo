<?php

namespace Farmassist\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use Farmassist\Models\AuditLogModel;
final class Audit extends BaseController
{
    protected AuditLogModel $logs;
    public function __construct()
    {
        $this->logs = new AuditLogModel();
    }
    public function index(): string|RedirectResponse
    {
        $clinicId = $this->clinicId();
        if ($clinicId === null) {
            return redirect()->to(base_url('permission-denied'));
        }
        $pageSize = (int) ($this->request->getGet('page_size') ?? 15);
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $search = trim((string) $this->request->getGet('search'));
        $action = trim((string) $this->request->getGet('action'));
        $status = trim((string) $this->request->getGet('status'));
        $from = trim((string) $this->request->getGet('from'));
        $to = trim((string) $this->request->getGet('to'));
        $query = $this->logs->where('clinic_id', $clinicId);
        if ($search !== '') {
            $query->groupStart()->like('action', $search)->orLike('correlation_id', $search)->orLike('route', $search)->orLike('actor_user_id', $search)->groupEnd();
        }
        if ($action !== '' && in_array($action, ['security', 'billing', 'prescription', 'medicine', 'order', 'notification', 'patient', 'appointment'], true)) {
            $query->like('action', $action);
        }
        if ($status !== '' && in_array($status, ['created', 'updated', 'deleted', 'failed', 'reviewed', 'read'], true)) {
            $query->like('action', $status);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $query->where('occurred_at >=', $from . ' 00:00:00');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $query->where('occurred_at <=', $to . ' 23:59:59');
        }
        $total = (clone $query)->countAllResults();
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $events = $query->orderBy('occurred_at', 'DESC')->findAll($pageSize, ($page - 1) * $pageSize);
        return view(FARMASSIST_VIEW . 'pages/audit/index', ['events' => $events, 'clinicId' => $clinicId, 'filters' => ['search' => $search, 'action' => $action, 'status' => $status, 'from' => $from, 'to' => $to], 'pagination' => ['page' => $page, 'pages' => $pages, 'page_size' => $pageSize, 'total' => $total]]);
    }
    private function clinicId(): ?int
    {
        if (!in_array('FarmAssist', session('roles') ?? [], true)) {
            return null;
        }
        $user = session('user') ?? [];
        $clinicId = (int) ($user['clinic_id'] ?? session('clinic_id') ?? 0);
        return $clinicId > 0 ? $clinicId : null;
    }
}
