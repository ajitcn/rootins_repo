<?= $this->extend('templates/theme1') ?>

<?= $this->section('side-menu') ?>
<aside id="sidebar" class="p-3 app-sidebar" aria-label="Patient panel navigation">
    <?php $path = trim(uri_string(), '/'); ?>
    <div class="sidebar-brand"><span class="brand-img"><img src="<?= base_url('assets/img/logo.png') ?>" alt="ROOTINS"></span><span class="system-tag">Patient</span></div>
    <nav class="sidebar-nav" aria-label="Patient navigation"><a class="nav-link <?= $path === 'patient' ? 'active' : '' ?>" href="<?= base_url('patient') ?>"><i class="bi bi-grid menu-icon" aria-hidden="true"></i><span>My dashboard</span></a><a class="nav-link <?= $path === 'patient/notifications' ? 'active' : '' ?>" href="<?= base_url('patient/notifications') ?>"><i class="bi bi-bell menu-icon" aria-hidden="true"></i><span>Notifications</span></a></nav>
    <a class="d-block mt-4 text-danger" href="<?= base_url('system/logout') ?>">Log out</a>
</aside>
<?= $this->endSection() ?>

<?= $this->section('top-menu') ?>
<header class="sticky-top-area top-header"><div class="d-flex align-items-center"><button class="btn btn-sm btn-light d-lg-none mr-2" id="sidebarToggle" type="button" aria-label="Toggle navigation" aria-controls="sidebar"><i class="bi bi-list" aria-hidden="true"></i></button><div><span class="topbar-kicker">ROOTINS</span><span class="font-weight-bold d-block">Patient panel</span></div></div><div class="topbar-actions"><span class="topbar-avatar" aria-hidden="true"><i class="bi bi-person"></i></span><a class="btn btn-sm btn-light" href="<?= base_url('system/logout') ?>"><i class="bi bi-box-arrow-right mr-1" aria-hidden="true"></i>Log out</a></div>
</header>
<?= $this->endSection() ?>
