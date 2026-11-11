<?= $this->extend(ADMIN_VIEW . 'layout') ?>
<?= $this->section('styles') ?>
<style>
    .admin-audit-page { padding-bottom: 2rem; }
    .admin-audit-page .audit-page-header { margin-bottom: 1.25rem; }
    .admin-audit-page .audit-scope-badge { padding: .45rem .7rem; border: 1px solid #dbe4ef; border-radius: 999px; background: #f8fafc; color: #475569; font-size: .7rem; font-weight: 800; }
    .admin-audit-page .audit-summary-row { margin-right: -.5rem; margin-left: -.5rem; }
    .admin-audit-page .audit-summary-row > [class*="col-"] { padding-right: .5rem; padding-left: .5rem; }
    .admin-audit-page .audit-summary-card { min-height: 6.5rem; padding: 1rem 1.15rem; border: 1px solid #e8edf2; border-radius: .75rem; background: #fff; box-shadow: 0 5px 18px rgba(15, 23, 42, .05); }
    .admin-audit-page .audit-summary-card { display: flex; align-items: center; gap: .8rem; }
    .admin-audit-page .audit-summary-icon { display: inline-flex; width: 2.25rem; height: 2.25rem; flex: 0 0 2.25rem; align-items: center; justify-content: center; border-radius: .65rem; color: #334155; background: #e9eef5; font-size: 1rem; }
    .admin-audit-page .audit-summary-icon.is-blue { color: #1d4ed8; background: #e5edff; }
    .admin-audit-page .audit-summary-icon.is-red { color: #b91c1c; background: #fee8e8; }
    .admin-audit-page .audit-summary-card small,
    .admin-audit-page .audit-summary-card span:not(.audit-summary-icon) { display: block; color: #64748b; font-size: .68rem; }
    .admin-audit-page .audit-summary-card strong { display: block; margin: .15rem 0; color: #172033; font-size: 1.35rem; line-height: 1.1; }
    .admin-audit-page .audit-register-card { overflow: hidden; border: 1px solid #e8edf2; border-radius: .75rem; background: #fff; box-shadow: 0 5px 18px rgba(15, 23, 42, .04); }
    .admin-audit-page .audit-register-header { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: 1rem 1.25rem; border-bottom: 1px solid #edf1f5; background: #f8fafc; }
    .admin-audit-page .audit-filter-bar { display: none; gap: .75rem; padding: 1rem 1.25rem; border-bottom: 1px solid #edf1f5; background: #fff; }
    .admin-audit-page .audit-filter-bar.is-visible { display: flex; flex-wrap: wrap; }
    .admin-audit-page .audit-filter-field { min-width: 10rem; flex: 1 1 10rem; }
    .admin-audit-page .audit-filter-field.audit-filter-search { flex-basis: 18rem; }
    .admin-audit-page .audit-filter-field label { display: block; margin-bottom: .3rem; color: #64748b; font-size: .68rem; font-weight: 800; }
    .admin-audit-page .audit-table th { padding: .85rem 1rem; background: #f8fafc; color: #475569; font-size: .7rem; letter-spacing: .04em; text-transform: uppercase; white-space: nowrap; }
    .admin-audit-page .audit-table td { padding: 1rem; border-color: #edf1f5; vertical-align: middle; }
    .admin-audit-page .audit-table tbody tr:hover { background: #fbfdff; }
    .admin-audit-page .audit-table code { display: inline-block; max-width: 13rem; overflow-wrap: anywhere; font-size: .68rem; }
    .admin-audit-page .audit-action-badge { display: inline-block; max-width: 18rem; padding: .35rem .55rem; border: 1px solid #e2e8f0; border-radius: .4rem; background: #f8fafc; color: #334155; font-size: .7rem; font-weight: 700; overflow-wrap: anywhere; }
    .admin-audit-page .audit-empty { padding: 3rem 1rem; color: #64748b; text-align: center; }
    .admin-audit-page .audit-empty strong,
    .admin-audit-page .audit-empty span { display: block; }
    .admin-audit-page .audit-detail { border-radius: .45rem; font-size: .72rem; font-weight: 700; }
    .admin-audit-page #audit-detail-modal .modal-content { overflow: hidden; border: 0; border-radius: .75rem; box-shadow: 0 18px 55px rgba(15, 23, 42, .2); }
    .admin-audit-page #audit-detail-modal .modal-header { padding: 1rem 1.25rem; background: #172033; color: #fff; border: 0; }
    .admin-audit-page #audit-detail-modal .modal-title { color: #fff; }
    .admin-audit-page #audit-detail-modal .modal-header .close { color: #fff; opacity: .85; text-shadow: none; }
    .admin-audit-page #audit-detail-modal .modal-body { padding: 1.25rem; }
    .admin-audit-page #audit-detail-modal dt { padding-top: .5rem; color: #64748b; font-size: .68rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
    .admin-audit-page #audit-detail-modal dd { padding: .55rem .7rem; border: 1px solid #e5eaf0; border-radius: .45rem; background: #f8fafc; overflow-wrap: anywhere; }
    .admin-audit-page #audit-detail-context { max-height: 48vh; overflow: auto; padding: .9rem; border: 1px solid #e2e8f0; border-radius: .55rem; background: #0f172a !important; color: #e2e8f0 !important; white-space: pre-wrap; word-break: break-word; font-size: .76rem; line-height: 1.55; }
    @media (max-width: 767.98px) {
        .admin-audit-page .audit-register-header { align-items: flex-start; flex-direction: column; padding: .9rem; }
        .admin-audit-page .audit-filter-bar { padding: .9rem; }
        .admin-audit-page .audit-filter-field { flex-basis: 100%; }
    }
</style>
<?= $this->endSection() ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container doctor-management-standard crm-page access-shell admin-audit-page" aria-labelledby="audit-title">
    <?= view('templates/_breadcrumb', ['module' => 'admin', 'menu_title' => 'Administration', 'page' => 'Audit logs']) ?>
    <header class="crm-header access-hero audit-page-header"><div><span class="crm-eyebrow">ADMIN / SECURITY & ACCOUNTABILITY</span><h1 id="audit-title" class="h3 mb-1">Audit logs</h1><p class="text-muted mb-0">Review security, clinical, financial, and configuration events in your permitted scope.</p></div><span class="audit-scope-badge"><i class="bi bi-shield-check mr-1" aria-hidden="true"></i>Scoped view</span></header>
    <div class="row audit-summary-row mb-4">
        <div class="col-sm-6 col-xl-4 mb-3 mb-xl-0"><div class="audit-summary-card"><span class="audit-summary-icon"><i class="bi bi-journal-text"></i></span><div><small>Events in scope</small><strong><?= (int) ($summary['count'] ?? 0) ?></strong><span>Latest 100 records</span></div></div></div>
        <div class="col-sm-6 col-xl-4 mb-3 mb-xl-0"><div class="audit-summary-card"><span class="audit-summary-icon is-blue"><i class="bi bi-calendar-event"></i></span><div><small>Events today</small><strong><?= (int) ($summary['today'] ?? 0) ?></strong><span>Current clinic scope</span></div></div></div>
        <div class="col-sm-6 col-xl-4"><div class="audit-summary-card"><span class="audit-summary-icon is-red"><i class="bi bi-shield-exclamation"></i></span><div><small>Security / auth events</small><strong class="text-danger"><?= (int) ($summary['security'] ?? 0) ?></strong><span>Review for exceptions</span></div></div></div>
    </div>
    <section class="access-card audit-register-card">
        <div class="audit-register-header"><div><h2 class="h5 mb-1">Event register</h2><p class="text-muted small mb-0">Sanitized context is available through the detail action.</p></div><div class="d-flex flex-wrap" style="gap:.5rem"><button type="button" class="btn btn-sm btn-outline-secondary" id="audit-filter-toggle" aria-expanded="false"><i class="bi bi-filter mr-1"></i>Filters</button><button type="button" class="btn btn-sm btn-outline-secondary" id="audit-clear-filters"><i class="bi bi-arrow-counterclockwise mr-1"></i>Clear filters</button></div></div>
        <div class="audit-filter-bar"><div class="audit-filter-field audit-filter-search"><label for="audit-search">Search</label><input id="audit-search" class="form-control form-control-sm" type="search" placeholder="Action, user, route, or ID"></div><div class="audit-filter-field"><label for="audit-date">From date</label><input id="audit-date" class="form-control form-control-sm datepicker" type="text" placeholder="yyyy-mm-dd" autocomplete="off"></div><div class="audit-filter-field"><label for="audit-action">Action group</label><select id="audit-action" class="custom-select custom-select-sm"><option value="">All groups</option><option value="security">Security / auth</option><option value="billing">Billing</option><option value="prescription">Prescription</option><option value="user">Identity / access</option></select></div><div class="audit-filter-field"><label for="audit-clinic">Clinic ID</label><input id="audit-clinic" class="form-control form-control-sm" type="number" min="1" placeholder="Any clinic"></div><div class="audit-filter-field"><label for="audit-user">Actor ID</label><input id="audit-user" class="form-control form-control-sm" type="number" min="1" placeholder="Any user"></div></div>
        <div class="table-responsive"><table class="table audit-table mb-0" id="audit-table"><thead><tr><th>Time</th><th>Action</th><th>Actor</th><th>Clinic</th><th>Correlation ID</th><th class="text-right">Details</th></tr></thead><tbody><?php foreach ($events as $event): ?><tr><td data-order="<?= esc((string) ($event['occurred_at'] ?? ''), 'attr') ?>"><span class="audit-time"><?= esc((string) ($event['occurred_at'] ?? '—')) ?></span></td><td><span class="audit-action-badge"><?= esc((string) ($event['action'] ?? 'Unknown event')) ?></span></td><td><strong><?= esc((string) ($event['actor_username'] ?: $event['actor_email'] ?: 'Unknown user')) ?></strong><small class="d-block text-muted">ID <?= (int) ($event['actor_user_id'] ?? 0) ?></small></td><td><strong><?= esc((string) ($event['clinic_name'] ?: 'Unknown clinic')) ?></strong><small class="d-block text-muted">ID <?= (int) ($event['clinic_id'] ?? 0) ?></small></td><td><code title="<?= esc((string) ($event['correlation_id'] ?? ''), 'attr') ?>"><?= esc((string) ($event['correlation_id'] ?? '—')) ?></code></td><td class="text-right"><button class="btn btn-sm btn-outline-secondary audit-detail" type="button" data-toggle="modal" data-target="#audit-detail-modal" data-action="<?= esc((string) ($event['action'] ?? ''), 'attr') ?>" data-time="<?= esc((string) ($event['occurred_at'] ?? ''), 'attr') ?>" data-correlation="<?= esc((string) ($event['correlation_id'] ?? ''), 'attr') ?>" data-context="<?= esc((string) ($event['context'] ?? ''), 'attr') ?>">View details</button></td></tr><?php endforeach; ?></tbody></table></div>
        <?php if ($events === []): ?><div class="audit-empty"><i class="bi bi-journal-check d-block h3 mb-2" aria-hidden="true"></i><strong>No audit events found</strong><span>Events will appear here when scoped actions are recorded.</span></div><?php endif; ?>
    </section>
</main>
<!-- Audit detail modal is part of the page output. -->
<div class="modal fade" id="audit-detail-modal" tabindex="-1" role="dialog" aria-labelledby="audit-detail-title" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable" role="document"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5" id="audit-detail-title">Audit event details</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><dl class="row mb-0"><dt class="col-sm-3">Action</dt><dd class="col-sm-9" id="audit-detail-action"></dd><dt class="col-sm-3">Time</dt><dd class="col-sm-9" id="audit-detail-time"></dd><dt class="col-sm-3">Correlation ID</dt><dd class="col-sm-9"><code id="audit-detail-correlation"></code></dd><dt class="col-sm-3">Sanitized context</dt><dd class="col-sm-9"><pre id="audit-detail-context" tabindex="0"></pre></dd></dl></div></div></div></div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?><script>
$(function () {
    const rows = $('#audit-table tbody tr');
    const filterCount = $('<span id="audit-filter-count" class="audit-filter-count" aria-live="polite"></span>');
    $('.audit-filter-bar').append(filterCount);
    $('#audit-filter-toggle').on('click', function () {
        const visible = $('.audit-filter-bar').toggleClass('is-visible').hasClass('is-visible');
        $(this).attr('aria-expanded', String(visible));
    });
    const actionGroups = {
        security: /security|auth|csrf|login|logout|hmac|access.?denied|permission/i,
        billing: /billing|invoice|payment|refund/i,
        prescription: /prescription|consultation/i,
        user: /user|access|role|permission|identity/i
    };
    function matchesActionGroup(action, selected) {
        return !selected || (actionGroups[selected] ? actionGroups[selected].test(action) : action.toLowerCase().indexOf(selected) >= 0);
    }
    function filterRows() {
        const search = $.trim(String($('#audit-search').val() || '')).toLowerCase();
        const date = $.trim(String($('#audit-date').val() || ''));
        const action = $('#audit-action').val() || '';
        const clinic = $.trim(String($('#audit-clinic').val() || ''));
        const user = $.trim(String($('#audit-user').val() || ''));
        let visible = 0;
        rows.each(function () {
            const row = $(this);
            const time = String(row.children().eq(0).text()).trim();
            const actionText = String(row.children().eq(1).text()).trim();
            const actorCell = String(row.children().eq(2).text());
            const clinicCell = String(row.children().eq(3).text());
            const rowDate = time.match(/\d{4}-\d{2}-\d{2}/);
            const matches = (!search || row.text().toLowerCase().indexOf(search) >= 0)
                && (!date || (rowDate && rowDate[0] >= date))
                && matchesActionGroup(actionText, action)
                && (!clinic || clinicCell.indexOf('ID ' + clinic) >= 0)
                && (!user || actorCell.indexOf('ID ' + user) >= 0);
            row.toggle(matches);
            if (matches) visible++;
        });
        filterCount.text(visible + ' event' + (visible === 1 ? '' : 's') + ' shown');
    }
    $('#audit-search,#audit-date,#audit-action,#audit-clinic,#audit-user').on('input change', filterRows);
    if ($.fn.datepicker) {
        $('#audit-date').datepicker({ format: 'yyyy-mm-dd', autoclose: true, todayHighlight: true }).on('changeDate', filterRows);
    }
    $('#audit-clear-filters').on('click', function () { $('#audit-search,#audit-date,#audit-clinic,#audit-user').val(''); $('#audit-action').val(''); filterRows(); });
    $('.audit-detail').on('click', function () { const button = $(this); $('#audit-detail-action').text(button.data('action')); $('#audit-detail-time').text(button.data('time')); $('#audit-detail-correlation').text(button.data('correlation')); let context = button.attr('data-context') || ''; try { context = JSON.stringify(JSON.parse(context), null, 2); } catch (error) {} $('#audit-detail-context').text(context || 'No additional context recorded.'); });
    filterRows();
});
</script><?= $this->endSection() ?>
