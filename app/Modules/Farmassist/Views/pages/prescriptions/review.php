<?= $this->extend(FARMASSIST_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<main class="dashboard-container" aria-labelledby="farmassist-review-title">
    <div class="page-heading"><div><div class="page-eyebrow">Operations / clinical fulfilment</div><h1 id="farmassist-review-title" class="h3 mb-1">Prescription review queue</h1><div class="text-muted">Clinic scope: <?= (int) $clinicId ?></div></div><div class="d-flex flex-wrap gap-2"><button class="btn btn-light" type="button" data-toggle="modal" data-target="#farmassist-review-filters"><i class="bi bi-funnel mr-1" aria-hidden="true"></i>Filters</button><a class="btn btn-light" href="<?= base_url('farmassist') ?>"><i class="bi bi-arrow-left mr-1" aria-hidden="true"></i>Dashboard</a></div></div>

    <div class="modal fade" id="farmassist-review-filters" tabindex="-1" role="dialog" aria-labelledby="farmassist-review-filters-title" aria-hidden="true"><div class="modal-dialog" role="document"><div class="modal-content"><form method="get" action="<?= current_url() ?>"><div class="modal-header"><h2 class="modal-title h5" id="farmassist-review-filters-title">Filter review queue</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><div class="form-group"><label for="farmassist-review-search">Search</label><input class="form-control" id="farmassist-review-search" name="search" value="<?= esc($filters['search'] ?? '') ?>" placeholder="Prescription, patient, or doctor"></div><div class="form-group"><label for="farmassist-review-status">Status</label><select class="form-control btSelect" id="farmassist-review-status" name="status"><option value="">All reviewable statuses</option><?php foreach (['draft','submitted','clarification_requested'] as $reviewStatus): ?><option value="<?= esc($reviewStatus) ?>" <?= ($filters['status'] ?? '') === $reviewStatus ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $reviewStatus))) ?></option><?php endforeach; ?></select></div></div><div class="modal-footer"><a class="btn btn-light" href="<?= current_url() ?>">Clear</a><button class="btn btn-primary" type="submit">Apply filters</button></div></form></div></div></div>

    <?php if ($prescriptions === []): ?>
        <div class="empty-state" role="status"><i class="bi bi-inbox d-block h3 mb-2" aria-hidden="true"></i><div>No prescriptions are waiting for review.</div><small>Approved submissions will appear here.</small></div>
    <?php endif; ?>

    <?php foreach ($prescriptions as $prescription): ?>
        <?php
        $patientName = trim(implode(' ', array_filter([
            $prescription['patient_first_name'] ?? '',
            $prescription['patient_middle_name'] ?? '',
            $prescription['patient_last_name'] ?? '',
        ], static fn ($part) => $part !== null && $part !== '')));
        $doctorName = trim(implode(' ', array_filter([
            $prescription['doctor_first_name'] ?? '',
            $prescription['doctor_last_name'] ?? '',
        ], static fn ($part) => $part !== null && $part !== '')));
        ?>
        <section class="card mb-4" data-prescription-card="<?= (int) $prescription['id'] ?>">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <strong><?= esc($prescription['prescription_number']) ?></strong>
                    <span class="text-muted ml-2"><?= esc($prescription['prescription_date']) ?></span>
                </div>
                <span class="badge badge-info"><?= esc(ucwords(str_replace('_', ' ', $prescription['status']))) ?></span>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4"><strong>Patient</strong><br><?= esc($patientName) ?> <small class="text-muted"><?= esc($prescription['registration_number']) ?></small></div>
                    <div class="col-md-4"><strong>Doctor</strong><br><?= esc($doctorName) ?></div>
                    <div class="col-md-4"><strong>Notes</strong><br><?= nl2br(esc($prescription['notes'] ?: '—')) ?></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-3">
                        <thead class="thead-light"><tr><th>Medicine</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Instructions</th></tr></thead>
                        <tbody>
                        <?php foreach ($itemsByPrescription[(int) $prescription['id']] ?? [] as $item): ?>
                            <tr>
                                <td><?= esc($item['medicine_name']) ?></td>
                                <td><?= esc($item['dosage']) ?></td>
                                <td><?= esc($item['frequency']) ?></td>
                                <td><?= esc(trim(($item['duration'] ?? '') . ' ' . ($item['duration_unit'] ?? ''))) ?></td>
                                <td><?= nl2br(esc($item['instructions'] ?: '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex flex-wrap review-actions" data-review-url="<?= base_url('farmassist/prescriptions/review') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="prescription_id" value="<?= (int) $prescription['id'] ?>">
                    <button type="button" class="btn btn-success mr-2 mb-2 review-action" data-decision="approve">Approve</button>
                    <button type="button" class="btn btn-danger mr-2 mb-2 review-action" data-decision="reject">Reject</button>
                    <button type="button" class="btn btn-warning mb-2 review-action" data-decision="clarification">Request clarification</button>
                    <button type="button" class="btn btn-outline-danger ml-2 mb-2 review-action" data-decision="cancel">Cancel</button>
                </div>
            </div>
        </section>
    <?php endforeach; ?>
    <?= view(FARMASSIST_VIEW . 'parts/pagination', ['pagination' => $pagination ?? [], 'filters' => $filters ?? []]) ?>
</main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
$(function () {
    $('.review-action').on('click', function () {
        const button = $(this);
        const actions = button.closest('.review-actions');
        const formData = actions.find('input').serializeArray();
        formData.push({name: 'decision', value: button.data('decision')});

        ajaxRequest({
            url: actions.data('review-url'),
            data: $.param(formData),
            success: function (response) {
                if (response.status) {
                    showSuccess(response.msg);
                    reload_page(700);
                    return;
                }
                showError(response.msg);
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
