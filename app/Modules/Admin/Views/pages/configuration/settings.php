<?= $this->extend(ADMIN_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container">
    <h1 class="h4 mb-3"><?= esc($title) ?></h1>
    <div class="action-btn-container d-flex justify-content-between">
        <form method="get" class="form-inline" aria-label="Filter settings">
            <label class="mr-2" for="settingSearch">Search</label>
            <input id="settingSearch" name="search" class="form-control mr-2" value="<?= esc($filters['search']) ?>">
            <button class="btn btn-outline-secondary">Filter</button>
        </form>
        <button class="btn btn-primary" data-toggle="modal" data-target="#settingModal">New setting</button>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Key</th><th>Value</th><th>Scope</th><th>Updated</th><th>Action</th></tr></thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">No settings found.</td></tr><?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><code><?= esc($row['setting_key']) ?></code></td>
                        <td><?= esc($row['setting_value'] ?? '') ?></td>
                        <td><?= $row['clinic_id'] ? 'Clinic ' . (int) $row['clinic_id'] : 'Global' ?></td>
                        <td><?= esc($row['updated_at'] ?? '—') ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary edit-setting" data-toggle="modal" data-target="#settingModal" data-id="<?= (int) $row['id'] ?>" data-key="<?= esc($row['setting_key'], 'attr') ?>" data-secret="<?= (int) $row['is_secret'] ?>">Edit</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= view(ADMIN_VIEW . 'parts/doctor_pagination', ['pager' => $pager, 'group' => 'config_settings', 'filters' => $filters]) ?>
    </div>
</main>
<div class="modal fade" id="settingModal" tabindex="-1" aria-labelledby="settingModalTitle" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" id="settingForm">
        <div class="modal-header"><h2 class="h5" id="settingModalTitle"><?= esc($title) ?></h2><button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button></div>
        <div class="modal-body">
            <input type="hidden" name="id" id="settingId">
            <input type="hidden" name="csrf_rtn" value="<?= csrf_hash() ?>">
            <input type="hidden" name="setting_group" value="<?= esc($group) ?>">
            <label for="settingKey">Setting key</label><input id="settingKey" name="setting_key" class="form-control" required>
            <label for="settingValue">Setting value</label><textarea id="settingValue" name="setting_value" class="form-control"></textarea>
            <small id="secretHint" class="form-text text-muted d-none">Leave blank to preserve the stored secret.</small>
            <label><input type="checkbox" name="is_secret" value="1"> Store securely as secret</label>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">Save setting</button></div>
    </form></div>
</div>
<?= $this->section('scripts') ?>
<script>
$(function () {
    $('.edit-setting').on('click', function () {
        $('#settingId').val($(this).data('id')); $('#settingKey').val($(this).data('key'));
        const secret = String($(this).data('secret')) === '1'; $('#settingValue').val('').attr('placeholder', secret ? '[configured securely]' : 'Setting value');
        $('#secretHint').toggleClass('d-none', !secret); $('input[name="is_secret"]').prop('checked', secret);
    });
    $('[data-target="#settingModal"]:not(.edit-setting)').on('click', function () { $('#settingForm')[0].reset(); $('#settingId').val(''); $('#secretHint').addClass('d-none'); });
    $('#settingForm').on('submit', function (e) { e.preventDefault(); ajaxRequest({url: '<?= base_url('admin/configuration/settings/save') ?>', data: $(this).serialize(), success: function (r) { if (r.status) reload_page(500); else showError(r.msg); }}); });
});
</script>
<?= $this->endSection() ?>
<?= $this->endSection() ?>
