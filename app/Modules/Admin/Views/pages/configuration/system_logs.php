<?= $this->extend(ADMIN_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container" aria-labelledby="system-logs-title">
    <h1 id="system-logs-title" class="h4 mb-3">System Logs</h1>
    <form method="get" class="card card-body mb-3" aria-label="Filter system logs">
        <div class="form-row align-items-end">
            <div class="col-md-5"><label for="logSearch">Search</label><input id="logSearch" name="search" class="form-control" value="<?= esc($filters['search']) ?>" placeholder="Action, correlation ID, or context"></div>
            <div class="col-md-3"><label for="logAction">Action group</label><select id="logAction" name="action" class="form-control btSelect"><option value="">All actions</option><?php foreach (['security', 'auth', 'configuration', 'billing', 'prescription', 'appointment', 'patient'] as $option): ?><option value="<?= $option ?>" <?= $filters['action'] === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label for="logPageSize">Page size</label><select id="logPageSize" name="page_size" class="form-control btSelect"><?php foreach ([10, 15, 25, 50] as $option): ?><option value="<?= $option ?>" <?= $filters['page_size'] === $option ? 'selected' : '' ?>><?= $option ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><button class="btn btn-primary mr-1">Apply</button><a class="btn btn-outline-secondary" href="<?= current_url() ?>">Clear</a></div>
        </div>
    </form>
    <div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Time</th><th>Action</th><th>Actor</th><th>Clinic</th><th>Correlation ID</th></tr></thead><tbody>
    <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">No system logs found.</td></tr><?php endif; ?>
    <?php foreach ($rows as $row): ?><tr><td><?= esc($row['occurred_at'] ?? '—') ?></td><td><span class="badge badge-light"><?= esc($row['action'] ?? '—') ?></span></td><td><?= (int) ($row['actor_user_id'] ?? 0) ?></td><td><?= (int) ($row['clinic_id'] ?? 0) ?></td><td><code><?= esc($row['correlation_id'] ?? '—') ?></code></td></tr><?php endforeach; ?>
    </tbody></table></div><?= view(ADMIN_VIEW . 'parts/doctor_pagination', ['pager' => $pager, 'group' => 'config_system_logs', 'filters' => $filters]) ?></div>
</main>
<?= $this->endSection() ?>
