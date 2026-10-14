<?php
$prefix = $prefix ?? 'clinical';
$title = $title ?? 'Filter records';
$action = $action ?? current_url();
$statuses = $statuses ?? [];
$filters = $filters ?? [];
?>
<div class="modal fade admin-clinical-filter-modal" id="<?= esc($prefix) ?>FilterModal" tabindex="-1" role="dialog" aria-labelledby="<?= esc($prefix) ?>FilterTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div><p class="ri-eyebrow mb-1">Clinical Records</p><h2 class="modal-title h5 mb-0" id="<?= esc($prefix) ?>FilterTitle"><?= esc($title) ?></h2></div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close filters"><span aria-hidden="true">&times;</span></button>
            </div>
            <form method="get" action="<?= esc($action) ?>">
                <div class="modal-body">
                    <div class="admin-clinical-filter-grid">
                        <div class="form-group admin-clinical-filter-search">
                            <label for="<?= esc($prefix) ?>FilterSearch">Search clinical records</label>
                            <input type="search" class="form-control" id="<?= esc($prefix) ?>FilterSearch" name="search" value="<?= esc($filters['search'] ?? '') ?>" placeholder="Record number, patient, mobile, doctor, or clinic">
                        </div>
                        <div class="form-group"><label for="<?= esc($prefix) ?>FilterStatus">Status</label><select class="form-control btSelect" id="<?= esc($prefix) ?>FilterStatus" name="status" title="All statuses"><option value="">All statuses</option><?php foreach ($statuses as $status): ?><option value="<?= esc($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $status))) ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label for="<?= esc($prefix) ?>FilterClinic">Clinic</label><select class="form-control btSelect" id="<?= esc($prefix) ?>FilterClinic" name="clinic_id" title="All clinics"><option value="">All clinics</option><?php foreach ($clinics as $clinic): ?><option value="<?= (int) $clinic['id'] ?>" <?= (string) ($filters['clinic_id'] ?? '') === (string) $clinic['id'] ? 'selected' : '' ?>><?= esc($clinic['clinic_name']) ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label for="<?= esc($prefix) ?>FilterDoctor">Doctor</label><select class="form-control btSelect" id="<?= esc($prefix) ?>FilterDoctor" name="doctor_id" title="All doctors"><option value="">All doctors</option><?php foreach ($doctors as $doctor): ?><option value="<?= (int) $doctor['id'] ?>" <?= (string) ($filters['doctor_id'] ?? '') === (string) $doctor['id'] ? 'selected' : '' ?>>Dr. <?= esc(trim($doctor['first_name'] . ' ' . $doctor['last_name']) . ' (' . $doctor['doctor_code'] . ')') ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label for="<?= esc($prefix) ?>FilterFrom">Date from</label><input type="text" class="form-control datepicker" id="<?= esc($prefix) ?>FilterFrom" name="date_from" value="<?= esc($filters['date_from'] ?? '') ?>" placeholder="dd-mm-yyyy" autocomplete="off"></div>
                        <div class="form-group"><label for="<?= esc($prefix) ?>FilterTo">Date to</label><input type="text" class="form-control datepicker" id="<?= esc($prefix) ?>FilterTo" name="date_to" value="<?= esc($filters['date_to'] ?? '') ?>" placeholder="dd-mm-yyyy" autocomplete="off"></div>
                    </div>
                    <p class="form-text mb-0">Dates are shown as dd-mm-yyyy. The server validates and normalizes them before querying records.</p>
                </div>
                <div class="modal-footer">
                    <a href="<?= esc($action) ?>" class="btn btn-link mr-auto">Clear filters</a>
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn theme-btn">Apply filters</button>
                </div>
            </form>
        </div>
    </div>
</div>
