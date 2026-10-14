<?= $this->extend(PATIENT_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<main class="dashboard-container" aria-labelledby="patient-section-title">
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Patient portal</div>
            <h1 id="patient-section-title" class="h3 mb-1"><?= esc($title) ?></h1>
            <p class="text-muted mb-0"><?= esc($description) ?></p>
        </div>
        <span class="badge badge-light"><?= count($records) ?> record<?= count($records) === 1 ? '' : 's' ?></span>
    </div>
    <?php if (! in_array($sectionKey, ['profile', 'support'], true)): ?>
        <form class="card card-body mb-3" method="get" action="<?= current_url() ?>">
            <div class="form-row align-items-end">
                <div class="form-group col-md-7 mb-md-0">
                    <label for="patient-section-search">Search</label>
                    <input id="patient-section-search" class="form-control" type="search" name="search" value="<?= esc($pagination['search'] ?? '') ?>" placeholder="Search this section">
                </div>
                <div class="form-group col-md-2 mb-md-0">
                    <label for="patient-section-page-size">Page size</label>
                    <select id="patient-section-page-size" class="form-control" name="page_size">
                        <?php foreach ([10, 15, 25, 50] as $size): ?><option value="<?= $size ?>" <?= (int) ($pagination['page_size'] ?? 15) === $size ? 'selected' : '' ?>><?= $size ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3 mb-md-0"><button class="btn btn-primary mr-2" type="submit">Search</button><a class="btn btn-light" href="<?= current_url() ?>">Clear</a></div>
            </div>
        </form>
    <?php endif; ?>
    <?php if ($sectionKey === 'profile'): ?>
        <div class="card"><div class="card-body"><h2 class="h5">Patient details</h2><dl class="row mb-4"><dt class="col-sm-4">Name</dt><dd class="col-sm-8"><?= esc(trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''))) ?></dd><dt class="col-sm-4">Patient number</dt><dd class="col-sm-8"><?= esc($patient['registration_number'] ?? 'Not recorded') ?></dd><dt class="col-sm-4">Clinic</dt><dd class="col-sm-8"><?= esc($patient['clinic_name'] ?? 'Not recorded') ?></dd></dl><form id="patient-profile-form" method="post" action="<?= base_url('patient/profile') ?>"><?= csrf_field() ?><input type="hidden" name="telephone_country_code_id" value="<?= (int) ($patient['telephone_country_code_id'] ?? 0) ?>"><input type="hidden" name="country_code_manual" value="0"><div class="form-row"><div class="form-group col-md-6"><label for="patient-profile-email">Email</label><input id="patient-profile-email" class="form-control" type="email" name="email" value="<?= esc($patient['email'] ?? '') ?>"><div class="validation-error" id="emailErr"></div></div><div class="form-group col-md-6"><label for="patient-profile-mobile">Mobile</label><input id="patient-profile-mobile" class="form-control" name="mobile_number" value="<?= esc($patient['mobile_number'] ?? '') ?>"><div class="validation-error" id="mobile_numberErr"></div><div class="validation-error" id="country_codeErr"></div></div><div class="form-group col-12"><label for="patient-profile-address">Address</label><textarea id="patient-profile-address" class="form-control" name="address" rows="3"><?= esc($patient['address'] ?? '') ?></textarea><div class="validation-error" id="addressErr"></div></div></div><button class="btn btn-primary" type="button" id="patient-profile-save">Save changes</button></form></div></div>
    <?php elseif ($sectionKey === 'support'): ?>
        <div class="card"><div class="card-body"><h2 class="h5">Clinic support</h2><p>For appointment changes, prescription questions, billing, or delivery support, contact your permitted clinic directly.</p><dl class="row mb-0"><dt class="col-sm-3">Clinic</dt><dd class="col-sm-9"><?= esc($clinic['clinic_name'] ?? 'Not recorded') ?></dd><dt class="col-sm-3">Phone</dt><dd class="col-sm-9"><?= esc($clinic['mobile'] ?? 'Not recorded') ?></dd><dt class="col-sm-3">Email</dt><dd class="col-sm-9"><?= esc($clinic['email'] ?? 'Not recorded') ?></dd><dt class="col-sm-3">Address</dt><dd class="col-sm-9"><?= esc($clinic['address'] ?? 'Not recorded') ?></dd></dl></div></div>
    <?php elseif (in_array($sectionKey, ['appointments', 'prescriptions', 'orders', 'invoices', 'visits', 'documents'], true)): ?>
        <?= view(PATIENT_VIEW . 'pages/records', ['sectionKey' => $sectionKey, 'records' => $records]) ?>
    <?php elseif ($records === []): ?>
        <div class="card"><div class="card-body text-center text-muted py-5">No records found.</div></div>
    <?php else: ?>
        <div class="card"><div class="table-responsive"><table class="table mb-0"><caption class="sr-only"><?= esc($title) ?></caption><thead><tr><th>Reference</th><th>Date</th><th>Status</th><th>Details</th></tr></thead><tbody>
            <?php foreach ($records as $record): ?>
                <?php $reference = $record['appointment_number'] ?? $record['prescription_number'] ?? $record['order_number'] ?? $record['invoice_number'] ?? $record['consultation_number'] ?? $record['title'] ?? ('Record #' . ($record['id'] ?? '')); $date = $record['appointment_date'] ?? $record['prescription_date'] ?? $record['created_at'] ?? $record['consultation_date'] ?? ''; $status = $record['status'] ?? $record['payment_status'] ?? $record['delivery_status'] ?? 'Available'; ?>
                <tr><td><?= esc($reference) ?></td><td><?= $date !== '' ? esc(date('d-m-Y', strtotime((string) $date))) : 'Not recorded' ?></td><td><span class="badge badge-light"><?= esc(ucwords(str_replace('_', ' ', (string) $status))) ?></span></td><td class="text-muted small"><?= esc($record['doctor_first_name'] ?? $record['medicine_count'] ?? $record['total'] ?? $record['document_type'] ?? '') ?></td></tr>
            <?php endforeach; ?>
        </tbody></table></div></div>
    <?php endif; ?>
    <?php if (($pagination['pages'] ?? 1) > 1): ?>
        <nav class="mt-3" aria-label="Patient records pagination"><a class="btn btn-sm btn-light <?= (int) ($pagination['page'] ?? 1) <= 1 ? 'disabled' : '' ?>" href="<?= current_url() . '?page=' . max(1, (int) ($pagination['page'] ?? 1) - 1) . '&page_size=' . (int) ($pagination['page_size'] ?? 15) . '&search=' . rawurlencode((string) ($pagination['search'] ?? '')) ?>">Previous</a><span class="small text-muted mx-2">Page <?= (int) ($pagination['page'] ?? 1) ?> of <?= (int) ($pagination['pages'] ?? 1) ?></span><a class="btn btn-sm btn-light <?= (int) ($pagination['page'] ?? 1) >= (int) ($pagination['pages'] ?? 1) ? 'disabled' : '' ?>" href="<?= current_url() . '?page=' . min((int) ($pagination['pages'] ?? 1), (int) ($pagination['page'] ?? 1) + 1) . '&page_size=' . (int) ($pagination['page_size'] ?? 15) . '&search=' . rawurlencode((string) ($pagination['search'] ?? '')) ?>">Next</a></nav>
    <?php endif; ?>
</main>
<?= $this->endSection() ?>
<?php if ($sectionKey === 'profile'): ?>
    <?= $this->section('scripts') ?><script>$(function(){ $('#patient-profile-save').on('click',function(){ clearFormErrors('#patient-profile-form'); ajaxRequest({url:'<?= base_url('patient/profile') ?>',method:'POST',data:$('#patient-profile-form').serialize(),success:function(response){if(response.status){showSuccess(response.msg);}else{showError(response.msg);bindFormError('#patient-profile-form',response.errors||{});}}}); }); });</script><?= $this->endSection() ?>
<?php endif; ?>
