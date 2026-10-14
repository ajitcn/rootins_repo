<?= $this->extend('templates/theme1') ?>

<?= $this->section('side-menu') ?>
<aside id="sidebar" class="p-3 app-sidebar" aria-label="Patient panel navigation">
    <?php $path = trim(uri_string(), '/'); ?>
    <?php $patientUser = session('user') ?? []; $displayName = trim((string) ($patientUser['username'] ?? 'Patient')) ?: 'Patient'; ?>
    <div class="sidebar-brand"><span class="brand-img"><img src="<?= base_url('assets/img/logo.png') ?>" alt="ROOTINS"></span><span class="system-tag">Patient</span><button type="button" class="sidebar-close d-lg-none" data-sidebar-close aria-label="Close navigation"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
    <div class="sidebar-user"><span class="sidebar-avatar" aria-hidden="true"><?= esc(strtoupper(substr($displayName, 0, 1))) ?></span><span class="sidebar-user-copy"><strong><?= esc($displayName) ?></strong><small>Patient portal</small></span></div>
    <nav class="sidebar-nav" aria-label="Patient navigation">
        <?php $links = [
            'patient' => ['My dashboard', 'bi-grid'],
            'patient/profile' => ['My profile', 'bi-person'],
            'patient/appointments' => ['Appointments', 'bi-calendar-check'],
            'patient/prescriptions' => ['Prescriptions', 'bi-prescription2'],
            'patient/orders' => ['Medicine orders', 'bi-bag-check'],
            'patient/invoices' => ['Invoices and payments', 'bi-receipt'],
            'patient/notifications' => ['Notifications', 'bi-bell'],
            'patient/visits' => ['Visit history', 'bi-clock-history'],
            'patient/documents' => ['Documents', 'bi-file-earmark-text'],
            'patient/support' => ['Support and help', 'bi-question-circle'],
        ]; ?>
        <?php foreach ($links as $url => [$label, $icon]): ?>
            <a class="nav-link <?= $path === $url ? 'active' : '' ?>" href="<?= base_url($url) ?>">
                <i class="bi <?= esc($icon) ?> menu-icon" aria-hidden="true"></i>
                <span><?= esc($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <a class="d-block mt-4 text-danger" href="<?= base_url('patient/logout') ?>">Log out</a>
</aside>
<?= $this->endSection() ?>

<?= $this->section('footer') ?>
<footer class="border-top bg-white px-3 py-3 text-muted small" aria-label="Patient portal footer">
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <span>ROOTINS Patient Portal</span>
        <span><?= esc(date('Y')) ?> · Your clinic information is private.</span>
    </div>
</footer>
<?= $this->endSection() ?>

<?= $this->section('top-menu') ?>
<header class="sticky-top-area top-header"><div class="d-flex align-items-center"><button class="btn btn-sm btn-light d-lg-none mr-2" id="sidebarToggle" type="button" aria-label="Toggle navigation" aria-controls="sidebar"><i class="bi bi-list" aria-hidden="true"></i></button><div><span class="topbar-kicker">ROOTINS</span><span class="font-weight-bold d-block">Patient panel</span></div></div><div class="topbar-actions"><span class="topbar-avatar" aria-hidden="true"><i class="bi bi-person"></i></span><a class="btn btn-sm btn-light" href="<?= base_url('patient/logout') ?>"><i class="bi bi-box-arrow-right mr-1" aria-hidden="true"></i>Log out</a></div>
</header>
<?= $this->endSection() ?>
