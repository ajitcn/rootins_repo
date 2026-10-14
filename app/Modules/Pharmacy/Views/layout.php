<?= $this->extend('templates/theme1') ?>

<?= $this->section('styles') ?>
<?php $pharmacyAssetVersion = '20261012-dashboard-ui1'; ?>
<link rel="stylesheet" href="<?= base_url('assets/css/pharmacy/shared.css?v=' . $pharmacyAssetVersion) ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/pharmacy/prescriptions.css?v=' . $pharmacyAssetVersion) ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/pharmacy/prescriptions-refinement.css?v=' . $pharmacyAssetVersion) ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/pharmacy/inventory.css?v=' . $pharmacyAssetVersion) ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/pharmacy/orders.css?v=' . $pharmacyAssetVersion) ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/pharmacy/orders-refinement.css?v=' . $pharmacyAssetVersion) ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/pharmacy/order_labels.css?v=' . $pharmacyAssetVersion) ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/pharmacy/billing.css?v=' . $pharmacyAssetVersion) ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/pharmacy/billing-refinement.css?v=20261012-payment-modal6') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/pharmacy/delivery.css?v=20261010-delivery2') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/pharmacy/delivery-refinement.css?v=20261010-delivery2') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/pharmacy/audit.css?v=' . $pharmacyAssetVersion) ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/pharmacy/notifications.css?v=' . $pharmacyAssetVersion) ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/pharmacy/more_actions.css?v=' . $pharmacyAssetVersion) ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/pharmacy/theme.css?v=' . $pharmacyAssetVersion) ?>">
<?= $this->endSection() ?>

<?= $this->section('side-menu') ?>
<aside id="sidebar" class="p-3 app-sidebar" aria-label="Pharmacy panel navigation" aria-hidden="false">
    <?php $path = trim(uri_string(), '/');
$permissions = session('permissions') ?? [];
$pharmacyUser = session('user') ?? [];
$displayName = trim((string) ($pharmacyUser['username'] ?? $pharmacyUser['email'] ?? 'Pharmacy Assistant')) ?: 'Pharmacy Assistant'; ?>
    <div class="sidebar-brand"><span class="brand-img"><img src="<?= base_url('assets/img/logo.png') ?>" alt="ROOTINS"></span><span class="system-tag">Pharmacy</span><button type="button" class="sidebar-close d-lg-none" data-sidebar-close aria-label="Close navigation"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
    <div class="sidebar-user"><span class="sidebar-avatar" aria-hidden="true"><?= esc(strtoupper(substr($displayName, 0, 1))) ?></span><span class="sidebar-user-copy"><strong><?= esc($displayName) ?></strong><small>Pharmacy workspace</small></span></div>
    <nav class="sidebar-nav" aria-label="Pharmacy navigation">
        <?php if (in_array('Pharmacy Assistant', session('roles') ?? [], true) && in_array('pharmacy.dashboard.view', $permissions, true)): ?><a class="nav-link <?= $path === 'pharmacy' ? 'active' : '' ?>" href="<?= base_url('pharmacy') ?>"><i class="bi bi-grid menu-icon" aria-hidden="true"></i><span>Dashboard</span></a><?php endif; ?>
        <?php if (in_array('Pharmacy Assistant', session('roles') ?? [], true) && in_array('pharmacy.prescription.review.view', $permissions, true)): ?><a class="nav-link <?= $path === 'pharmacy/prescriptions' ? 'active' : '' ?>" href="<?= base_url('pharmacy/prescriptions') ?>"><i class="bi bi-prescription2 menu-icon" aria-hidden="true"></i><span>Prescription review</span></a><?php endif; ?>
        <?php if (in_array('Pharmacy Assistant', session('roles') ?? [], true) && in_array('pharmacy.inventory.view', $permissions, true)): ?><a class="nav-link <?= $path === 'pharmacy/inventory' ? 'active' : '' ?>" href="<?= base_url('pharmacy/inventory') ?>"><i class="bi bi-box-seam menu-icon" aria-hidden="true"></i><span>Central medicine stock</span></a><?php endif; ?>
        <?php if (in_array('Pharmacy Assistant', session('roles') ?? [], true) && in_array('pharmacy.order.view', $permissions, true)): ?><a class="nav-link <?= $path === 'pharmacy/orders' ? 'active' : '' ?>" href="<?= base_url('pharmacy/orders') ?>"><i class="bi bi-bag-check menu-icon" aria-hidden="true"></i><span>Medicine orders</span></a><?php endif; ?>
        <?php if (in_array('Pharmacy Assistant', session('roles') ?? [], true) && in_array('pharmacy.billing.view', $permissions, true)): ?><a class="nav-link <?= $path === 'pharmacy/billing' ? 'active' : '' ?>" href="<?= base_url('pharmacy/billing') ?>"><i class="bi bi-receipt menu-icon" aria-hidden="true"></i><span>Billing &amp; payments</span></a><?php endif; ?>
        <?php if (in_array('Pharmacy Assistant', session('roles') ?? [], true) && in_array('pharmacy.notification.view', $permissions, true)): ?><a class="nav-link <?= $path === 'pharmacy/notifications' ? 'active' : '' ?>" href="<?= base_url('pharmacy/notifications') ?>"><i class="bi bi-bell menu-icon" aria-hidden="true"></i><span>Notifications</span></a><?php endif; ?>
        <?php if (in_array('Pharmacy Assistant', session('roles') ?? [], true) && in_array('pharmacy.audit.view', $permissions, true)): ?><a class="nav-link <?= $path === 'pharmacy/audit' ? 'active' : '' ?>" href="<?= base_url('pharmacy/audit') ?>"><i class="bi bi-shield-check menu-icon" aria-hidden="true"></i><span>Audit trail</span></a><?php endif; ?>
    </nav>
    <div class="small text-muted mt-3">Orders, payment, and delivery are managed from the pharmacy dashboard.</div>
    <a class="d-block mt-4 text-danger" href="<?= base_url('system/logout') ?>">Log out</a>
</aside>
<?= $this->endSection() ?>

<?= $this->section('top-menu') ?>
<header class="sticky-top-area top-header"><div class="d-flex align-items-center"><button class="btn btn-sm btn-light d-lg-none mr-2" id="sidebarToggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="sidebar"><i class="bi bi-list" aria-hidden="true"></i></button><div><span class="topbar-kicker">ROOTINS</span><span class="font-weight-bold d-block">Pharmacy panel</span></div></div><div class="topbar-actions"><span class="topbar-avatar" aria-hidden="true"><i class="bi bi-person"></i></span><a class="btn btn-sm btn-light" href="<?= base_url('system/logout') ?>"><i class="bi bi-box-arrow-right mr-1" aria-hidden="true"></i>Log out</a></div>
</header>
<?= $this->endSection() ?>
