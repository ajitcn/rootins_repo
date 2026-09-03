<?= $this->extend('templates/theme1') ?>

<?= $this->section('side-menu') ?>
<?php
$path = trim(uri_string(), '/');
$permissions = session('permissions') ?? [];
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
    </div>

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
                <li class="nav-item">
                    <a
                        class="nav-link <?= $active('doctor/video') ? 'active' : '' ?>"
                        href="<?= base_url('doctor/appointments') ?>"
                    >
                        <i class="bi bi-camera-video menu-icon" aria-hidden="true"></i>
                        <span>Video consultations</span>
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
            <a
                class="btn-tabler-icon"
                href="<?= base_url('doctor/notifications') ?>"
                title="Notifications"
                aria-label="Notifications"
            >
                <i class="bi bi-bell" aria-hidden="true"></i>
            </a>
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
