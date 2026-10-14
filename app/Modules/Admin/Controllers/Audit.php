<?php

namespace Admin\Controllers;

use App\Controllers\BaseAdminController;

class Audit extends BaseAdminController
{
    public function index(): string
    {
        $query = db_connect()->table('audit_logs')->select('audit_logs.*')->orderBy('id', 'DESC');
        $clinicId = (int) ($this->user['clinic_id'] ?? session('clinic_id') ?? 0);

        if ($clinicId > 0) {
            $query->where('clinic_id', $clinicId);
        }
        $events = $query->limit(100)->get()->getResultArray();
        $summary = ['count' => count($events), 'security' => count(array_filter($events, static fn (array $event): bool => str_contains(strtolower((string) ($event['action'] ?? '')), 'security') || str_contains(strtolower((string) ($event['action'] ?? '')), 'auth'))), 'today' => count(array_filter($events, static fn (array $event): bool => str_starts_with((string) ($event['occurred_at'] ?? ''), date('Y-m-d'))))];

        return view(ADMIN_VIEW . 'pages/audit/index', ['events' => $events, 'summary' => $summary]);
    }
}
