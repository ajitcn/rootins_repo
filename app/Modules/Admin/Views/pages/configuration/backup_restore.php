<?= $this->extend(ADMIN_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container" aria-labelledby="backup-title">
    <h1 id="backup-title" class="h4 mb-3">Backup and Restore</h1>
    <div class="alert alert-info">This screen reports local readiness only. It does not create, download, restore, or delete backups.</div>
    <div class="card"><div class="card-body"><h2 class="h5">Readiness checks</h2><dl class="row mb-0"><dt class="col-sm-6">Application writable directory</dt><dd class="col-sm-6"><span class="badge badge-<?= $writable ? 'success' : 'danger' ?>"><?= $writable ? 'Available' : 'Unavailable' ?></span></dd><dt class="col-sm-6">Database connection</dt><dd class="col-sm-6"><span class="badge badge-<?= $backupConfigured ? 'success' : 'warning' ?>"><?= $backupConfigured ? 'Configured' : 'Review configuration' ?></span></dd></dl></div></div>
    <div class="card mt-3"><div class="card-body"><h2 class="h5">Operational requirement</h2><p class="mb-0">Use an approved encrypted backup process and verify restores in a controlled environment before production release. Credentials, encryption keys, retention, RPO/RTO, and restore ownership remain deployment policy.</p></div></div>
</main>
<?= $this->endSection() ?>
