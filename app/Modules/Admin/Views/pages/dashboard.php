<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php $dashboardPermissions = session('permissions') ?? []; $canDashboard = static fn (string $permission): bool => in_array($permission, $dashboardPermissions, true); ?>
<main class="dashboard-container" aria-labelledby="admin-dashboard-title">
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Administration / operations</div>
            <h1 id="admin-dashboard-title" class="h3 mb-1">Admin dashboard</h1>
            <p class="text-muted mb-0">Live operational summary for the permitted system scope.</p>
        </div>
        <a class="btn btn-primary" href="<?= base_url('admin/appointments') ?>">
            <i class="bi bi-calendar-plus mr-1" aria-hidden="true"></i>Open appointments
        </a>
    </div>

    <section class="row" aria-label="Operational KPIs">
        <?php foreach ([
            ['clinics', 'Active clinics', 'bi-hospital', 'admin/clinics'],
            ['doctors', 'Active doctors', 'bi-person-badge', 'admin/doctors'],
            ['patients', 'Active patients', 'bi-people', 'admin/patients'],
            ['appointments', 'Appointments today', 'bi-calendar-check', 'admin/appointments'],
            ['payments', 'Paid invoices', 'bi-credit-card', 'admin/billing'],
            ['orders', 'Medicine orders', 'bi-box-seam', 'pharmacy'],
            ['audit_alerts', 'Audit alerts today', 'bi-shield-exclamation', 'admin/audit'],
        ] as [$key, $label, $icon, $url]): ?>
            <div class="col-12 col-sm-6 col-xl-3 mb-3">
                <a class="card ri-stat-card h-100 text-decoration-none" href="<?= base_url($url) ?>" aria-label="<?= esc($label) ?>">
                    <div class="card-body">
                        <div class="text-muted small"><?= esc($label) ?></div>
                        <div class="ri-stat-value mt-2 <?= $key === 'audit_alerts' && ($metrics[$key] ?? 0) > 0 ? 'text-danger' : '' ?>"><?= (int) ($metrics[$key] ?? 0) ?></div>
                        <span class="ri-stat-icon" aria-hidden="true"><i class="bi <?= esc($icon) ?>"></i></span>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
        <div class="col-12 col-sm-6 col-xl-3 mb-3">
            <div class="card ri-stat-card h-100">
                <div class="card-body">
                    <div class="text-muted small">Finalized revenue</div>
                    <div class="ri-stat-value mt-2">₹<?= esc(number_format((float) ($metrics['revenue'] ?? 0), 2)) ?></div>
                    <span class="ri-stat-icon" aria-hidden="true"><i class="bi bi-graph-up-arrow"></i></span>
                </div>
            </div>
        </div>
    </section>

    <div class="row">
        <div class="col-lg-7 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h2 class="h5 mb-0">Priority actions</h2>
                    <span class="badge badge-light">Scoped</span>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <?php if ($canDashboard('doctor.view')): ?><a class="btn btn-outline-primary" href="<?= base_url('admin/doctors') ?>"><i class="fa-solid fa-user-doctor mr-1" aria-hidden="true"></i>Doctor Management</a><?php endif; ?>
                        <?php if ($canDashboard('doctor_commission.view')): ?><a class="btn btn-outline-primary" href="<?= base_url('admin/doctor-commissions') ?>"><i class="fa-solid fa-percent mr-1" aria-hidden="true"></i>Doctor Commission</a><?php endif; ?>
                        <a class="btn btn-outline-primary" href="<?= base_url('admin/audit') ?>"><i class="bi bi-shield-check mr-1" aria-hidden="true"></i>Review audit logs</a>
                        <a class="btn btn-outline-primary" href="<?= base_url('admin/billing') ?>"><i class="bi bi-receipt mr-1" aria-hidden="true"></i>Review billing</a>
                        <a class="btn btn-outline-primary" href="<?= base_url('admin/access/users') ?>"><i class="bi bi-person-gear mr-1" aria-hidden="true"></i>Manage users</a>
                    </div>
                    <?php if (($metrics['audit_alerts'] ?? 0) > 0): ?>
                        <div class="alert alert-warning mt-3 mb-0" role="status">There are audit events requiring review today.</div>
                    <?php else: ?>
                        <div class="empty-state mt-3"><i class="bi bi-check-circle d-block h3 mb-2" aria-hidden="true"></i><div>No priority alerts</div><small>Operational indicators are clear for the current scope.</small></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5 mb-4">
            <div class="card h-100">
                <div class="card-header"><h2 class="h5 mb-0">System health</h2></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between border-bottom py-2"><span>Runtime data</span><span class="badge badge-success">Loaded</span></div>
                    <div class="d-flex justify-content-between border-bottom py-2"><span>Audit records</span><span class="badge badge-success">Available</span></div>
                    <div class="d-flex justify-content-between py-2"><span>External providers</span><span class="badge badge-light">Deployment setup required</span></div>
                </div>
            </div>
        </div>
    </div>

    <?php
    $moduleAccess = [
        ['label' => 'Doctor', 'description' => 'Consultations, availability, and doctor operations.', 'icon' => 'fa-solid fa-user-doctor', 'permission' => 'doctor.view', 'url' => 'doctor', 'status' => 'Authorized'],
        ['label' => 'Pharmacy', 'description' => 'Prescriptions, inventory, orders, and delivery.', 'icon' => 'fa-solid fa-pills', 'permission' => 'pharmacy.dashboard.view', 'url' => 'pharmacy', 'status' => 'Authorized'],
        ['label' => 'Patient', 'description' => 'Manage patient records and care information.', 'icon' => 'fa-solid fa-user-injured', 'permission' => 'patient.view', 'url' => 'admin/patients', 'status' => 'Authorized'],
        ['label' => 'CRM / Lead Management', 'description' => 'Review leads and manage the conversion pipeline.', 'icon' => 'fa-solid fa-address-book', 'permission' => 'crm.lead.view', 'url' => 'admin/crm/leads', 'status' => 'Authorized'],
        ['label' => 'Core API', 'description' => 'Manage signed Core API credentials and access.', 'icon' => 'fa-solid fa-plug-circle-check', 'permission' => 'core_api.view', 'url' => 'admin/integrations/core-api', 'status' => 'Authorized'],
        ['label' => 'Shopify Integration', 'description' => 'Monitor the Shopify connection and operations.', 'icon' => 'fa-brands fa-shopify', 'permission' => 'shopify.integration.view', 'url' => 'admin/integrations/shopify', 'status' => 'Authorized'],
        ['label' => 'Admin Settings', 'description' => 'Configure system settings for the permitted scope.', 'icon' => 'fa-solid fa-sliders', 'permission' => 'settings.manage', 'url' => 'admin/configuration/settings/general-settings', 'status' => 'Authorized'],
    ];
    $availableModules = array_values(array_filter($moduleAccess, static fn (array $module): bool => $canDashboard($module['permission'])));
    ?>
    <section class="module-access-section" aria-labelledby="module-access-title">
        <div class="module-access-heading">
            <div>
                <h2 id="module-access-title" class="h5 mb-1">Module Access</h2>
                <p class="text-muted mb-0">Open an authorized module directly from the Admin panel.</p>
            </div>
            <span class="badge badge-light module-access-count" aria-label="<?= count($availableModules) ?> modules available"><?= count($availableModules) ?> available</span>
        </div>

        <?php if ($availableModules): ?>
            <div class="module-access-grid">
                <?php foreach ($availableModules as $module): ?>
                    <a class="module-access-card" href="<?= base_url($module['url']) ?>">
                        <span class="module-access-icon" aria-hidden="true"><i class="<?= esc($module['icon']) ?>"></i></span>
                        <span class="module-access-copy">
                            <strong><?= esc($module['label']) ?></strong>
                            <span><?= esc($module['description']) ?></span>
                        </span>
                        <span class="module-access-meta">
                            <span class="badge badge-success"><i class="bi bi-check2 mr-1" aria-hidden="true"></i><?= esc($module['status']) ?></span>
                            <span class="module-access-open">Open <i class="bi bi-arrow-up-right ml-1" aria-hidden="true"></i></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state module-access-empty" role="status">
                <i class="bi bi-lock d-block h3 mb-2" aria-hidden="true"></i>
                <div>No additional modules are available</div>
                <small>Your account currently has access to the Admin dashboard only.</small>
            </div>
        <?php endif; ?>
    </section>
</main>
<?= $this->endSection() ?>
