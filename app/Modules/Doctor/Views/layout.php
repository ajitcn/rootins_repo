<?= $this->extend('templates/theme1') ?>

<?php $this->section('styles'); ?>
<link rel="stylesheet" href="<?= base_url('assets/css/doctor/dashboard.css?v=20261106-doctor-dashboard1') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/doctor/dashboard-refinements.css?v=20261106-doctor-dashboard2') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/doctor/follow-ups.css?v=20261106-doctor-followups1') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/doctor/follow-ups-picker.css?v=20261106-doctor-followups3') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/doctor/follow-ups-standard.css?v=20261106-doctor-followups4') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/doctor/follow-ups-pagination.css?v=20261106-doctor-followups5') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/doctor/follow-ups-heading.css?v=20261106-doctor-followups6') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/doctor/follow-ups-summary.css?v=20261106-doctor-followups7') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/doctor/standard-pages.css?v=20261106-doctor-standard1') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/doctor/patients-standard.css?v=20261106-doctor-patients2') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/doctor/patients-toolbar.css?v=20261106-doctor-patients3') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/doctor/appointments-standard.css?v=20261106-doctor-appointments1') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/doctor/appointments-summary.css?v=20261106-doctor-appointments2') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/doctor/appointments-layout.css?v=20261106-doctor-appointments3') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/doctor/availability-standard.css?v=20261106-doctor-availability2') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/doctor/prescriptions-standard.css?v=20261106-doctor-prescriptions4') ?>">
<?= $this->endSection(); ?>

<?= $this->section('side-menu') ?>
<?php
$path = trim(uri_string(), '/');
$permissions = session('permissions') ?? [];
$sessionUser = session('user') ?? [];
$displayName = trim((string) ($sessionUser['username'] ?? $sessionUser['email'] ?? 'Doctor')) ?: 'Doctor';
$can = static fn (string $permission): bool => in_array($permission, $permissions, true);
$active = static function (string $prefix) use ($path): bool {
    $prefix = trim($prefix, '/');

    return $path === $prefix || str_starts_with($path, $prefix . '/');
};
?>
<nav id="sidebar" aria-label="Doctor panel navigation">
    <div class="sidebar-brand">
        <span class="brand-img">
            <img src="<?= base_url('assets/img/logo.png') ?>" alt="ROOTINS">
        </span>
        <span class="system-tag">Doctor</span>
        <button type="button" class="sidebar-close d-lg-none" data-sidebar-close aria-label="Close navigation"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </div>
    <div class="sidebar-user"><span class="sidebar-avatar" aria-hidden="true"><?= esc(strtoupper(substr($displayName, 0, 1))) ?></span><span class="sidebar-user-copy"><strong><?= esc($displayName) ?></strong><small>Doctor workspace</small></span></div>

    <ul class="sidebar-nav" id="sidebarMenu" aria-label="Doctor navigation">
            <li class="sidebar-section-title" aria-hidden="true">Workspace</li>

            <?php if ($can('doctor.view')): ?>
                <li class="nav-item">
                    <a
                        class="nav-link <?= $path === 'doctor' ? 'active' : '' ?>"
                        href="<?= base_url('doctor') ?>"
                    >
                        <i class="bi bi-speedometer2 menu-icon" aria-hidden="true"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($can('consultation.view')): ?>
                <li class="nav-item">
                    <a
                        class="nav-link <?= $active('doctor/clinical') ? 'active' : '' ?>"
                        href="<?= base_url('doctor/clinical') ?>"
                    >
                        <i class="bi bi-clipboard2-pulse menu-icon" aria-hidden="true"></i>
                        <span>Clinical workspace</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a
                        class="nav-link <?= $active('doctor/follow-ups') ? 'active' : '' ?>"
                        href="<?= base_url('doctor/follow-ups') ?>"
                    >
                        <i class="bi bi-calendar2-check menu-icon" aria-hidden="true"></i>
                        <span>Follow-ups</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($can('patient.view')): ?>
                <li class="nav-item">
                    <a
                        class="nav-link <?= $active('doctor/patients') ? 'active' : '' ?>"
                        href="<?= base_url('doctor/patients') ?>"
                    >
                        <i class="bi bi-person-vcard menu-icon" aria-hidden="true"></i>
                        <span>My patients</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($can('appointment.view')): ?>
                <li class="nav-item">
                    <a
                        class="nav-link <?= $active('doctor/appointments') ? 'active' : '' ?>"
                        href="<?= base_url('doctor/appointments') ?>"
                    >
                        <i class="bi bi-calendar3 menu-icon" aria-hidden="true"></i>
                        <span>Appointments</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($can('doctor.view')): ?>
                <li class="nav-item">
                    <a
                        class="nav-link <?= $active('doctor/availability') ? 'active' : '' ?>"
                        href="<?= base_url('doctor/availability') ?>"
                    >
                        <i class="bi bi-calendar-week menu-icon" aria-hidden="true"></i>
                        <span>Availability</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a
                        class="nav-link <?= $active('doctor/leaves') ? 'active' : '' ?>"
                        href="<?= base_url('doctor/leaves') ?>"
                    >
                        <i class="bi bi-calendar-x menu-icon" aria-hidden="true"></i>
                        <span>Leaves</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($can('prescription.view')): ?>
                <li class="nav-item">
                    <a
                        class="nav-link <?= $active('doctor/prescriptions') ? 'active' : '' ?>"
                        href="<?= base_url('doctor/prescriptions') ?>"
                    >
                        <i class="bi bi-prescription2 menu-icon" aria-hidden="true"></i>
                        <span>Prescriptions</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($can('billing.view')): ?>
                <li class="nav-item">
                    <a
                        class="nav-link <?= $active('doctor/billing') ? 'active' : '' ?>"
                        href="<?= base_url('doctor/billing') ?>"
                    >
                        <i class="bi bi-receipt menu-icon" aria-hidden="true"></i>
                        <span>Billing</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($can('doctor.view')): ?>
                <li class="nav-item">
                    <a
                        class="nav-link <?= $active('doctor/notifications') ? 'active' : '' ?>"
                        href="<?= base_url('doctor/notifications') ?>"
                    >
                        <i class="bi bi-bell menu-icon" aria-hidden="true"></i>
                        <span>Notifications</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a
                        class="nav-link <?= $active('doctor/profile') ? 'active' : '' ?>"
                        href="<?= base_url('doctor/profile') ?>"
                    >
                        <i class="bi bi-person-gear menu-icon" aria-hidden="true"></i>
                        <span>Profile &amp; settings</span>
                    </a>
                </li>
            <?php endif; ?>
    </ul>
</nav>
<?= $this->endSection() ?>

<?= $this->section('top-menu') ?>
<div class="sticky-top-area">
    <header class="top-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <button
                class="btn btn-sm btn-light d-lg-none"
                id="sidebarToggle"
                type="button"
                aria-label="Toggle navigation"
                aria-controls="sidebar"
                aria-expanded="false"
            >
                <i class="bi bi-list fs-5" aria-hidden="true"></i>
            </button>
            <div>
                <span class="topbar-kicker">ROOTINS</span>
                <span class="font-weight-bold d-block">Doctor Panel</span>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <form class="doctor-top-patient-search d-none d-md-flex align-items-center" method="get" action="<?= base_url('doctor/patients') ?>">
                <label class="sr-only" for="doctor-top-patient-search">Search patients</label>
                <div class="input-group input-group-sm">
                    <div class="input-group-prepend"><span class="input-group-text bg-transparent border-right-0"><i class="bi bi-search" aria-hidden="true"></i></span></div>
                    <input id="doctor-top-patient-search" class="form-control border-left-0" type="search" name="search" placeholder="Search patients" autocomplete="off">
                </div>
            </form>
            <a
                class="btn-tabler-icon"
                href="<?= base_url('doctor/notifications') ?>"
                title="Notifications"
                aria-label="Notifications"
            >
                <i class="bi bi-bell" aria-hidden="true"></i>
            </a>
            <?php if ($can('doctor.view')): ?>
                <a
                    class="btn-tabler-icon"
                    href="<?= base_url('doctor/profile') ?>"
                    title="Doctor profile"
                    aria-label="Doctor profile"
                >
                    <i class="bi bi-person-circle" aria-hidden="true"></i>
                </a>
            <?php endif; ?>
            <div class="vr mx-1 opacity-25"></div>
            <a
                class="btn btn-sm btn-light"
                href="<?= base_url('system/logout') ?>"
            >
                <i class="bi bi-box-arrow-right mr-1" aria-hidden="true"></i>
                Log out
            </a>
        </div>
    </header>
</div>
<?= $this->endSection() ?>
