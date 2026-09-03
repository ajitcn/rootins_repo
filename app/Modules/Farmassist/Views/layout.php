<?= $this->extend('templates/theme1') ?>

<?= $this->section('side-menu') ?>
<aside id="sidebar" class="p-3 app-sidebar" aria-label="Farmassist panel navigation">
    <?php $path = trim(uri_string(), '/'); $permissions = session('permissions') ?? []; ?>
    <div class="sidebar-brand"><span class="brand-img"><img src="<?= base_url('assets/img/logo.png') ?>" alt="ROOTINS"></span><span class="system-tag">Farmassist</span></div>
    <nav class="sidebar-nav" aria-label="Farmassist navigation">
        <?php if (in_array('FarmAssist', session('roles') ?? [], true) && in_array('prescription.view', $permissions, true)): ?><a class="nav-link <?= $path === 'farmassist' ? 'active' : '' ?>" href="<?= base_url('farmassist') ?>"><i class="bi bi-grid menu-icon" aria-hidden="true"></i><span>Dashboard</span></a><a class="nav-link <?= $path === 'farmassist/prescriptions' ? 'active' : '' ?>" href="<?= base_url('farmassist/prescriptions') ?>"><i class="bi bi-prescription2 menu-icon" aria-hidden="true"></i><span>Prescription review</span></a><?php endif; ?>
        <?php if (in_array('FarmAssist', session('roles') ?? [], true) && in_array('audit.view', $permissions, true)): ?><a class="nav-link <?= $path === 'farmassist/audit' ? 'active' : '' ?>" href="<?= base_url('farmassist/audit') ?>"><i class="bi bi-shield-check menu-icon" aria-hidden="true"></i><span>Audit trail</span></a><?php endif; ?>
    </nav>
    <div class="small text-muted mt-3">Orders, payment and delivery are managed from the dashboard.</div>
    <a class="d-block mt-4 text-danger" href="<?= base_url('system/logout') ?>">Log out</a>
</aside>
<?= $this->endSection() ?>

<?= $this->section('top-menu') ?>
<header class="sticky-top-area top-header"><div class="d-flex align-items-center"><button class="btn btn-sm btn-light d-lg-none mr-2" id="sidebarToggle" type="button" aria-label="Toggle navigation" aria-controls="sidebar"><i class="bi bi-list" aria-hidden="true"></i></button><div><span class="topbar-kicker">ROOTINS</span><span class="font-weight-bold d-block">Farmassist panel</span></div></div><div class="topbar-actions"><span class="topbar-avatar" aria-hidden="true"><i class="bi bi-person"></i></span><a class="btn btn-sm btn-light" href="<?= base_url('system/logout') ?>"><i class="bi bi-box-arrow-right mr-1" aria-hidden="true"></i>Log out</a></div>
</header>
<?= $this->endSection() ?>
