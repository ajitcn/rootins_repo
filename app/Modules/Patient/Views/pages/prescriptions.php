<?= $this->extend(PATIENT_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<main class="patient-page patient-prescriptions-page" aria-labelledby="patient-prescriptions-title">
    <header class="patient-page-heading">
        <div>
            <span class="patient-eyebrow">Patient portal / treatment</span>
            <h1 id="patient-prescriptions-title">My prescriptions</h1>
            <p class="text-muted mb-0">Review prescriptions issued for your care.</p>
            <span class="small text-muted">Patient: <?= esc(trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''))) ?> · <?= esc($patient['registration_number'] ?? '—') ?></span>
        </div>
        <div class="patient-page-heading-actions"><span class="badge badge-light"><i class="bi bi-prescription2 mr-1" aria-hidden="true"></i><?= (int) ($pagination['total'] ?? 0) ?> total</span></div>
    </header>

    <section class="card patient-list-toolbar" aria-label="Prescription filters">
        <form method="get" action="<?= current_url() ?>" class="patient-filter-form">
            <input type="hidden" name="view" value="<?= esc($filters['view']) ?>">
            <div class="patient-filter-search"><label class="sr-only" for="patient-prescription-search">Search prescriptions</label><i class="bi bi-search" aria-hidden="true"></i><input id="patient-prescription-search" class="form-control" type="search" name="search" value="<?= esc($filters['search']) ?>" placeholder="Prescription, doctor, medicine or status"></div>
            <select class="form-control form-control-sm" name="status" aria-label="Prescription status"><option value="">All statuses</option><?php foreach (['draft', 'completed', 'cancelled'] as $option): ?><option value="<?= esc($option) ?>" <?= $filters['status'] === $option ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $option))) ?></option><?php endforeach; ?></select>
            <select class="form-control form-control-sm" name="doctor_id" aria-label="Doctor"><option value="">All doctors</option><?php foreach ($doctors as $doctor): ?><option value="<?= (int) $doctor['id'] ?>" <?= (string) $filters['doctor_id'] === (string) $doctor['id'] ? 'selected' : '' ?>>Dr. <?= esc(trim(($doctor['first_name'] ?? '') . ' ' . ($doctor['last_name'] ?? ''))) ?></option><?php endforeach; ?></select>
            <input class="form-control form-control-sm patient-date-filter" type="text" name="date_from" value="<?= esc($filters['date_from']) ?>" placeholder="From dd-mm-yyyy" aria-label="Prescription date from">
            <input class="form-control form-control-sm patient-date-filter" type="text" name="date_to" value="<?= esc($filters['date_to']) ?>" placeholder="To dd-mm-yyyy" aria-label="Prescription date to">
            <select class="form-control form-control-sm" name="page_size" aria-label="Page size"><?php foreach ([10, 15, 25, 50] as $size): ?><option value="<?= $size ?>" <?= (int) $filters['page_size'] === $size ? 'selected' : '' ?>><?= $size ?> / page</option><?php endforeach; ?></select>
            <div class="patient-filter-actions"><button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-funnel mr-1" aria-hidden="true"></i>Apply</button><a class="btn btn-light btn-sm" href="<?= current_url() ?>">Clear</a></div>
            <div class="patient-view-toggle" role="group" aria-label="Prescription view"><a class="btn btn-sm <?= $filters['view'] === 'list' ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= current_url() . '?' . http_build_query(array_merge($filters, ['view' => 'list', 'page' => 1])) ?>" aria-pressed="<?= $filters['view'] === 'list' ? 'true' : 'false' ?>"><i class="bi bi-list-ul mr-1" aria-hidden="true"></i>List</a><a class="btn btn-sm <?= $filters['view'] === 'grid' ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= current_url() . '?' . http_build_query(array_merge($filters, ['view' => 'grid', 'page' => 1])) ?>" aria-pressed="<?= $filters['view'] === 'grid' ? 'true' : 'false' ?>"><i class="bi bi-grid mr-1" aria-hidden="true"></i>Grid</a></div>
        </form>
    </section>

    <section class="card patient-prescriptions-content">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between"><div><h2 class="h5 mb-1">Prescription history</h2><span class="small text-muted"><?= esc($clinic['clinic_name'] ?? 'Your clinic') ?></span></div><span class="small text-muted"><i class="fa-solid fa-circle-exclamation mr-1" aria-hidden="true"></i>Created and updated metadata</span></div>
        <?php if ($filters['view'] === 'grid'): ?><?= view(PATIENT_VIEW . 'pages/prescriptions_grid', compact('prescriptions')) ?><?php else: ?><?= view(PATIENT_VIEW . 'pages/prescriptions_list', compact('prescriptions')) ?><?php endif; ?>
    </section>

    <?php $values = ['search' => $filters['search'], 'status' => $filters['status'], 'doctor_id' => $filters['doctor_id'], 'date_from' => $filters['date_from'], 'date_to' => $filters['date_to'], 'view' => $filters['view'], 'page_size' => $filters['page_size']]; ?>
    <div class="card pagination-card mb-4"><div class="card-body py-3 d-flex flex-wrap align-items-center justify-content-between"><span class="small text-muted">Showing page <?= (int) $pagination['page'] ?> of <?= (int) $pagination['pages'] ?> · <?= (int) $pagination['total'] ?> prescriptions</span><nav aria-label="Prescription pagination"><?php $values['page'] = max(1, (int) $pagination['page'] - 1); ?><a class="btn btn-sm btn-outline-secondary <?= (int) $pagination['page'] <= 1 ? 'disabled' : '' ?>" href="<?= (int) $pagination['page'] > 1 ? esc(current_url() . '?' . http_build_query($values)) : '#' ?>">Previous</a><?php $values['page'] = min((int) $pagination['pages'], (int) $pagination['page'] + 1); ?><a class="btn btn-sm btn-outline-secondary <?= (int) $pagination['page'] >= (int) $pagination['pages'] ? 'disabled' : '' ?>" href="<?= (int) $pagination['page'] < (int) $pagination['pages'] ? esc(current_url() . '?' . http_build_query($values)) : '#' ?>">Next</a></nav></div></div>

    <div class="modal fade patient-prescription-modal" id="patient-prescription-detail-modal" tabindex="-1" role="dialog" aria-labelledby="patient-prescription-detail-title" aria-hidden="true"><div class="modal-dialog modal-dialog-scrollable modal-xl" role="document"><div class="modal-content"><div class="modal-header"><div><span class="patient-eyebrow">Patient-safe prescription</span><h2 class="modal-title h5" id="patient-prescription-detail-title">Prescription Details</h2></div><button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body" id="patient-prescription-detail-body" aria-live="polite"><div class="patient-modal-loading" role="status"><span class="spinner-border spinner-border-sm mr-2" aria-hidden="true"></span>Loading prescription details…</div></div><div class="modal-footer"><button class="btn btn-outline-primary" id="patient-prescription-print" type="button" disabled><i class="bi bi-printer mr-1" aria-hidden="true"></i>Print</button><button class="btn btn-light" type="button" data-dismiss="modal">Close</button></div></div></div></div>
</main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?><script>
    $(function () {
        var records = <?= json_encode($prescriptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>; window.patientPdfRecords = records;
        var clinic = <?= json_encode($clinic, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        var activePrescription = null;
        var escapeHtml = function (value) { return $('<div>').text(value == null ? '' : value).html(); };

        $('.patient-date-filter').datepicker({ format: 'dd-mm-yyyy', autoclose: true, todayHighlight: true, orientation: 'bottom auto', container: '.patient-prescriptions-page' });

        $(document).on('click.patientPrescriptionDetails', '.patient-prescription-view', function () {
            var prescription = records.find(function (item) { return Number(item.id) === Number($(this).data('id')); }.bind(this));
            var body = $('#patient-prescription-detail-body');
            var printButton = $('#patient-prescription-print');

            body.html('<div class="patient-modal-loading" role="status"><span class="spinner-border spinner-border-sm mr-2" aria-hidden="true"></span>Loading prescription details…</div>');
            printButton.prop('disabled', true);

            if (! prescription) {
                body.html('<div class="patient-modal-state patient-modal-state-error" role="alert"><i class="bi bi-exclamation-triangle mr-2" aria-hidden="true"></i>Prescription details could not be found.</div>');
                return;
            }

            activePrescription = prescription;
            var medicines = (prescription.items || []).map(function (item) {
                var duration = [item.duration || '', item.duration_unit || ''].join(' ').trim() || '—';
                var note = item.notes || item.instructions || '—';

                return '<tr><td data-label="Medicine">' + escapeHtml(item.medicine_name || 'Medicine unavailable') + '</td><td data-label="Duration">' + escapeHtml(duration) + '</td><td data-label="Frequency">' + escapeHtml(item.frequency || '—') + '</td><td data-label="Dose">' + escapeHtml(item.dosage || '—') + '</td><td data-label="Intake">' + escapeHtml(item.instructions || '—') + '</td><td data-label="Note">' + escapeHtml(note) + '</td></tr>';
            }).join('');

            $('#patient-prescription-detail-title').text('Prescription Details · ' + (prescription.prescription_number || '—'));
            body.html('<section class="patient-modal-section"><h3><i class="bi bi-building mr-2" aria-hidden="true"></i>Clinic and patient</h3><div class="patient-detail-grid"><div><span>Clinic</span><strong>' + escapeHtml(clinic.clinic_name || prescription.clinic_name || '—') + '<small class="d-block text-muted">' + escapeHtml(clinic.mobile || clinic.email || 'Contact not recorded') + '</small></strong></div><div><span>Patient</span><strong>' + escapeHtml((prescription.patient_first_name || '') + ' ' + (prescription.patient_last_name || '')) + '<small class="d-block text-muted">' + escapeHtml(prescription.registration_number || '—') + ' · ' + escapeHtml(prescription.mobile_number || '—') + '</small></strong></div></div></section><section class="patient-modal-section"><h3><i class="bi bi-person-badge mr-2" aria-hidden="true"></i>Prescription and doctor</h3><div class="patient-detail-grid"><div><span>Prescription number</span><strong>' + escapeHtml(prescription.prescription_number || '—') + '</strong></div><div><span>Date / status</span><strong>' + escapeHtml(prescription.prescription_date_display || '—') + ' · ' + escapeHtml(prescription.status_label || '—') + '</strong></div><div><span>Doctor</span><strong>Dr. ' + escapeHtml((prescription.doctor_first_name || '') + ' ' + (prescription.doctor_last_name || '')) + '</strong></div><div><span>Specialization</span><strong>' + escapeHtml(prescription.specialization || '—') + '</strong></div></div></section><section class="patient-modal-section"><h3><i class="bi bi-capsule mr-2" aria-hidden="true"></i>Medicines</h3><div class="table-responsive patient-prescription-medicine-table-wrap"><table class="table table-sm patient-prescription-medicine-table"><thead><tr><th>Medicine</th><th>Duration</th><th>Frequency</th><th>Dose</th><th>Intake</th><th>Note</th></tr></thead><tbody>' + (medicines || '<tr><td colspan="6"><div class="patient-empty-state">No medicine details recorded.</div></td></tr>') + '</tbody></table></div></section><p class="patient-modal-metadata"><i class="fa-solid fa-circle-exclamation mr-1" aria-hidden="true"></i>Created: ' + escapeHtml(prescription.created_at || '—') + '<br>Updated: ' + escapeHtml(prescription.updated_at || '—') + '</p>');
            printButton.prop('disabled', false);
        });

        $('#patient-prescription-print').on('click', function () {
            if (! activePrescription) return;

            var canonicalPdfUrl = window.rootinsPatientPdfBase + '/prescriptions/' + encodeURIComponent(activePrescription.prescription_number || '') + '/pdf';
            if (activePrescription.prescription_number && window.open) {
                window.open(canonicalPdfUrl, '_blank', 'noopener');
                return;
            }

            var rows = (activePrescription.items || []).map(function (item) { return '<tr><td>' + escapeHtml(item.medicine_name || 'Medicine unavailable') + '</td><td>' + escapeHtml([item.duration || '', item.duration_unit || ''].join(' ').trim() || '—') + '</td><td>' + escapeHtml(item.frequency || '—') + '</td><td>' + escapeHtml(item.dosage || '—') + '</td><td>' + escapeHtml(item.instructions || '—') + '</td><td>' + escapeHtml(item.notes || item.instructions || '—') + '</td></tr>'; }).join('');
            var printWindow = window.open('', '_blank', 'width=980,height=760');

            if (! printWindow) return;

            var printMarkup = '<!doctype html><html><head><meta charset="utf-8"><title>' + escapeHtml(activePrescription.prescription_number) + '</title><style>body{font:12px Arial,sans-serif;color:#1f2940;padding:24px}h1{font-size:20px}h2{font-size:14px;margin-top:20px}.table-responsive{width:100%;overflow-x:auto}table{width:100%;min-width:40rem;border-collapse:collapse;table-layout:auto}th,td{padding:7px;border:1px solid #ccd3df;text-align:left;vertical-align:top;overflow-wrap:anywhere;word-break:break-word;white-space:normal}th{background:#f0f3f8}.meta{color:#5e6b82;margin:4px 0}@media print{body{padding:0}.table-responsive{overflow:visible}table{min-width:0;page-break-inside:auto}tr{page-break-inside:avoid;page-break-after:auto}}</style></head><body><h1>Prescription ' + escapeHtml(activePrescription.prescription_number) + '</h1><p class="meta">Patient: ' + escapeHtml((activePrescription.patient_first_name || '') + ' ' + (activePrescription.patient_last_name || '')) + ' · ' + escapeHtml(activePrescription.registration_number || '—') + '</p><p class="meta">Doctor: Dr. ' + escapeHtml((activePrescription.doctor_first_name || '') + ' ' + (activePrescription.doctor_last_name || '')) + ' · Date: ' + escapeHtml(activePrescription.prescription_date_display || '—') + ' · Status: ' + escapeHtml(activePrescription.status_label || '—') + '</p><h2>Medicines</h2><div class="table-responsive"><table><thead><tr><th>Medicine</th><th>Duration</th><th>Frequency</th><th>Dose</th><th>Intake</th><th>Note</th></tr></thead><tbody>' + rows + '</tbody></table></div></body></html>';

            printWindow.onload = function () {
                window.setTimeout(function () {
                    printWindow.focus();
                    printWindow.print();
                    printWindow.close();
                }, 250);
            };
            printWindow.document.open();
            printWindow.document.write(printMarkup);
            printWindow.document.close();
        });

        $(document).on('click.patientPrescriptionPrint', '.patient-prescription-print-row', function () {
            activePrescription = records.find(function (item) { return Number(item.id) === Number($(this).data('id')); }.bind(this));
            $('#patient-prescription-print').trigger('click');
        });
    });
</script><?= $this->endSection() ?>
