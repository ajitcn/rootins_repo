<?= $this->extend(PHARMACY_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container pharmacy-page pharmacy-review-page" aria-labelledby="pharmacy-review-title">
    <?= $this->include(PHARMACY_VIEW . 'pages/prescriptions/_toolbar') ?>
    <section class="pharmacy-review-grid" aria-label="Prescription review cards">
    <?php if (($prescriptions ?? []) === []): ?>
        <div class="card pharmacy-review-empty" role="status"><i class="bi bi-inbox d-block" aria-hidden="true"></i><strong>No prescriptions found</strong><span>Try changing the search or filters.</span></div>
    <?php else: foreach ($prescriptions as $prescription): ?>
        <?php $statusClass = in_array($prescription['status'] ?? '', ['approved'], true) ? 'success' : (in_array($prescription['status'] ?? '', ['rejected', 'cancelled'], true) ? 'danger' : 'info'); ?>
        <article class="card prescription-review-card">
            <header class="prescription-card-header"><div class="min-width-0"><span class="review-card-label">Prescription</span><h2 class="h6 mb-0 text-truncate" title="<?= esc($prescription['prescription_number'], 'attr') ?>"><?= esc($prescription['prescription_number']) ?></h2><small class="text-muted"><?= esc($prescription['prescription_date']) ?></small><small class="d-block text-muted"><?= esc($prescription['review_date_label'] ?? 'Last updated') ?>: <?= esc($prescription['review_date'] ?? 'Not recorded') ?></small></div><?= view(PHARMACY_VIEW . 'pages/prescriptions/_actions', ['prescription' => $prescription]) ?></header>
            <div class="prescription-card-patient"><span class="review-card-label">Patient</span><strong class="text-truncate" title="<?= esc($prescription['patient_name'], 'attr') ?>"><?= esc($prescription['patient_name']) ?></strong><span class="text-muted"><?= esc($prescription['patient_number']) ?></span><span class="text-muted text-truncate" title="<?= esc($prescription['patient_mobile'], 'attr') ?>"><?= esc($prescription['patient_mobile']) ?></span></div>
            <div class="prescription-card-details"><span class="min-width-0"><small>Doctor</small><strong class="text-truncate" title="<?= esc($prescription['doctor_name'], 'attr') ?>"><?= esc($prescription['doctor_name']) ?></strong><em><?= esc($prescription['doctor_code']) ?></em></span><span><small>Medicines</small><strong><?= (int) $prescription['medicine_count'] ?></strong><em><?= (int) $prescription['medicine_count'] === 1 ? 'item' : 'items' ?></em></span></div>
            <footer class="prescription-card-footer"><span class="prescription-card-status-group"><small>Prescription status</small><span class="badge badge-<?= $statusClass ?>"><?= esc($prescription['status_label']) ?></span></span><span class="prescription-card-status-group"><small>Review status</small><span class="badge badge-<?= ($prescription['review_state'] ?? '') === 'Final' ? 'light' : (($prescription['review_state'] ?? '') === 'Clarification requested' ? 'warning' : 'info') ?>"><?= esc($prescription['review_state']) ?></span></span></footer>
        </article>
    <?php endforeach; endif; ?>
    </section>
    <?= $this->include(PHARMACY_VIEW . 'parts/prescription_pagination') ?>
</main>
<?= $this->include(PHARMACY_VIEW . 'pages/prescriptions/_modal') ?>
<?= $this->section('scripts') ?><?= $this->include(PHARMACY_VIEW . 'pages/prescriptions/_scripts') ?><?= $this->endSection() ?>
<?= $this->endSection() ?>
