<?php

namespace Pharmacy\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use Pharmacy\Models\AuditLogModel;
use Pharmacy\Services\PharmacyScope;
use DateTimeImmutable;

final class Audit extends BaseController
{
    protected AuditLogModel $logs;

    private PharmacyScope $scope;

    public function __construct()
    {
        $this->logs = new AuditLogModel();
        $this->scope = new PharmacyScope();
    }

    public function index(): string|RedirectResponse
    {
        $clinicId = $this->scope->clinicId();

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
            $query->groupStart()->like('action', $search)->orLike('correlation_id', $search)->orLike('route', $search)->orLike('actor_user_id', $search)->orLike('context', $search)->groupEnd();
        }

        if ($action !== '' && in_array($action, ['security', 'billing', 'prescription', 'medicine', 'order', 'notification', 'patient', 'appointment'], true)) {
            $query->like('action', $action);
        }

        if ($status !== '' && in_array($status, ['created', 'updated', 'deleted', 'failed', 'reviewed', 'read'], true)) {
            $query->like('action', $status);
        }
        $fromIso = $this->toIsoDate($from);
        $toIso = $this->toIsoDate($to);

        if ($fromIso !== null) {
            $query->where('occurred_at >=', $fromIso . ' 00:00:00');
        }

        if ($toIso !== null) {
            $query->where('occurred_at <=', $toIso . ' 23:59:59');
        }
        $total = (clone $query)->countAllResults();
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $events = $query->orderBy('occurred_at', 'DESC')->findAll($pageSize, ($page - 1) * $pageSize);

        return view(PHARMACY_VIEW . 'pages/audit/index', ['events' => $events, 'clinicId' => $clinicId, 'filters' => ['search' => $search, 'action' => $action, 'status' => $status, 'from' => $fromIso ? $this->displayDate($fromIso) : '', 'to' => $toIso ? $this->displayDate($toIso) : ''], 'pagination' => ['page' => $page, 'pages' => $pages, 'page_size' => $pageSize, 'total' => $total]]);
    }

    private function toIsoDate(string $value): ?string
    {
        $date = DateTimeImmutable::createFromFormat('!d-m-Y', $value);

        return $date !== false && $date->format('d-m-Y') === $value ? $date->format('Y-m-d') : null;
    }

    private function displayDate(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false ? $date->format('d-m-Y') : '';
    }
}
