<?= $this->extend(ADMIN_VIEW . 'layout') ?>
<?= $this->section('styles') ?>
<style>
    .admin-access-permissions-page { padding-bottom: 2rem; }
    .admin-access-permissions-page .access-hero { margin-bottom: 1.25rem; }
    .admin-access-permissions-page .access-nav { margin-bottom: 1.25rem; }
    .admin-access-permissions-page > .access-card { overflow: hidden; border-radius: .75rem; }
    .admin-access-permissions-page > .access-card > .card-header { display: none; }
    .admin-access-permissions-page .permission-catalog-toolbar { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .9rem 1.25rem; border-bottom: 1px solid #edf1f5; background: #f8fafc; }
    .admin-access-permissions-page .permission-catalog-toolbar strong { color: #1f2937; font-size: .9rem; }
    .admin-access-permissions-page .permission-catalog-toolbar small { color: #64748b; }
    .admin-access-permissions-page .permission-catalog-body { padding: 1.25rem; }
    .admin-access-permissions-page #permission-catalog { display: flex; flex-direction: column; gap: 1rem; margin: 0; }
    .admin-access-permissions-page .permission-catalog-group { width: 100%; max-width: none; flex: 0 0 auto; margin: 0; padding: 0; border: 1px solid #e8edf2; border-radius: .7rem; background: #fff; }
    .admin-access-permissions-page .permission-group-title { position: sticky; z-index: 1; top: 0; margin: 0; padding: .75rem .9rem; border: 0 !important; color: #334155; background: #f8fafc; font-size: .76rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
    .admin-access-permissions-page .permission-catalog-item { min-height: 5.2rem; padding: .8rem .9rem !important; border-color: #edf1f5 !important; }
    .admin-access-permissions-page .permission-catalog-item:last-child { border-bottom: 0 !important; }
    .admin-access-permissions-page .permission-catalog-item strong { color: #1f2937; font-size: .8rem; }
    .admin-access-permissions-page .permission-catalog-item code { display: block; margin-top: .4rem; color: #64748b; font-size: .68rem; overflow-wrap: anywhere; }
    .admin-access-permissions-page .permission-catalog-item small { color: #64748b; font-size: .7rem; line-height: 1.4; }
    @media (max-width: 767.98px) {
        .admin-access-permissions-page .permission-catalog-toolbar,
        .admin-access-permissions-page .permission-catalog-body { padding: .9rem; }
        .admin-access-permissions-page .permission-catalog-toolbar { align-items: flex-start; flex-direction: column; }
        .admin-access-permissions-page #permission-catalog { gap: .75rem; }
    }
</style>
<?= $this->endSection() ?>
<?= $this->section('main-content') ?>
<?= view(ADMIN_VIEW . 'parts/access_styles') ?>
<?php foreach ($permissions as &$permissionItem) { $permissionItem['module'] = display_permission_label($permissionItem['module'] ?? ''); $permissionItem['permission_name'] = display_permission_label($permissionItem['permission_name'] ?? ''); $permissionItem['description'] = display_permission_label($permissionItem['description'] ?? ''); } unset($permissionItem); ?>
<main class="dashboard-container doctor-management-standard crm-page access-shell admin-access-permissions-page">
    <?= view('templates/_breadcrumb', ['module' => 'admin', 'menu_title' => 'Administration', 'page' => 'Permissions']) ?>
    <header class="crm-header access-hero"><div><span class="crm-eyebrow">ADMIN / ACCESS CONTROL</span><h1 class="h3 mb-1">Permission catalog</h1><p class="text-muted mb-0">Read-oriented catalog of stable permission keys used by route and controller enforcement.</p></div><div class="d-flex flex-wrap align-items-center" style="gap:.5rem"><button class="btn btn-outline-secondary" type="button" id="permissionFilterToggle" aria-expanded="false" onclick="var p=document.querySelector('.admin-access-permissions-page > .access-card > .card-header');if(p){var o=p.style.display!=='block';p.style.display=o?'block':'none';this.setAttribute('aria-expanded',String(o));}"><i class="fa fa-filter mr-1" aria-hidden="true"></i>Filters</button><span class="access-badge active"><?= count($permissions) ?> registered</span></div></header>
    <nav class="access-nav" aria-label="Identity and access navigation"><a href="<?= base_url('admin/access/users') ?>">Users</a><a href="<?= base_url('admin/access/roles') ?>">Roles</a><a class="active" href="<?= base_url('admin/access/permissions') ?>">Permissions</a></nav>
    <div class="access-card"><div class="card-header"><div class="access-toolbar"><input class="form-control" id="permission-catalog-search" type="search" placeholder="Search module, name, or key" aria-label="Search permission catalog"><span class="access-count ml-auto">Permission keys are stable after creation.</span></div></div><div class="permission-catalog-toolbar"><div><strong>Permission keys</strong><small class="d-block">Search by module, name, or technical key.</small></div><i class="fa fa-key text-muted" aria-hidden="true"></i></div><div class="permission-catalog-body"><div class="row" id="permission-catalog"><?php $group = '';

foreach ($permissions as $permission): ?><?php if ($group !== $permission['module']): ?><?php $group = $permission['module']; ?><div class="col-lg-4 mb-4 permission-catalog-group" data-module="<?= esc(strtolower($group), 'attr') ?>"><h6 class="permission-group-title border-bottom pb-2"><?= esc($group) ?></h6><?php endif; ?><div class="permission-catalog-item p-2 border-bottom" data-search="<?= esc(strtolower($permission['module'] . ' ' . $permission['permission_name'] . ' ' . $permission['permission_key']), 'attr') ?>"><div class="d-flex justify-content-between"><strong><?= esc($permission['permission_name']) ?></strong><span class="access-badge <?= (int) $permission['status'] === 1 ? 'active' : 'disabled' ?>"><?= (int) $permission['status'] === 1 ? 'Active' : 'Disabled' ?></span></div><code><?= esc($permission['permission_key']) ?></code><?php if (! empty($permission['description'])): ?><small class="d-block access-muted mt-1"><?= esc($permission['description']) ?></small><?php endif; ?></div><?php $next = $permissions[array_search($permission, $permissions, true) + 1]['module'] ?? null;

    if ($next !== $group): ?></div><?php endif; ?><?php endforeach; ?><?php if ($permissions === []): ?><div class="col-12 access-empty">No permissions registered.</div><?php endif; ?></div></div></div>
</main>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?><script>$(function(){$('#permission-catalog-search').on('input',function(){const q=$(this).val().toLowerCase();$('.permission-catalog-group').each(function(){const group=$(this);let visible=0;group.find('.permission-catalog-item').each(function(){const show=!q||$(this).data('search').indexOf(q)!==-1;$(this).toggle(show);if(show)visible++;});group.toggle(visible>0);});});});</script><?= $this->endSection() ?>
