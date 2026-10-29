<?= $this->extend(PHARMACY_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container pharmacy-page pharmacy-review-page" aria-labelledby="pharmacy-review-title">
    <?= $this->include(PHARMACY_VIEW . 'pages/prescriptions/_toolbar') ?>
    <section class="card pharmacy-review-card" aria-label="Prescription review list">
        <div class="table-responsive">
            <table class="table pharmacy-review-table mb-0">
                <caption class="sr-only">Prescription review queue</caption>
                <thead><tr><th scope="col">Patient</th><th scope="col">Doctor</th><th scope="col">Prescription</th><th scope="col">Medicines</th><th scope="col">Status / validity</th><th scope="col">Cart / order</th><th scope="col" class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php if (($prescriptions ?? []) === []): ?>
                    <tr><td colspan="7"><div class="pharmacy-review-empty" role="status"><i class="bi bi-inbox d-block" aria-hidden="true"></i><strong>No prescriptions found</strong><span>There are no eligible prescriptions in the current clinic scope for these filters.</span></div></td></tr>
                <?php else: foreach ($prescriptions as $prescription): ?>
                    <?php $statusClass = in_array($prescription['status'] ?? '', ['approved'], true) ? 'success' : (in_array($prescription['status'] ?? '', ['rejected', 'cancelled'], true) ? 'danger' : 'info'); ?>
                    <tr>
                        <td><div class="pharmacy-review-stack"><strong title="<?= esc($prescription['patient_name'], 'attr') ?>"><?= esc($prescription['patient_name']) ?></strong><span><?= esc($prescription['patient_number']) ?></span><span title="<?= esc($prescription['patient_mobile'], 'attr') ?>"><?= esc($prescription['patient_mobile']) ?></span></div></td>
                        <td><div class="pharmacy-review-stack"><strong title="<?= esc($prescription['doctor_name'], 'attr') ?>"><?= esc($prescription['doctor_name']) ?></strong><span><?= esc($prescription['doctor_code']) ?></span></div></td>
                        <td><div class="pharmacy-review-stack"><strong title="<?= esc($prescription['prescription_number'], 'attr') ?>"><?= esc($prescription['prescription_number']) ?></strong><span><?= esc($prescription['prescription_date']) ?></span></div></td>
                        <td><span class="pharmacy-review-medicine-count"><i class="bi bi-capsule mr-1" aria-hidden="true"></i><?= (int) $prescription['medicine_count'] ?> <?= (int) $prescription['medicine_count'] === 1 ? 'medicine' : 'medicines' ?></span></td>
                        <td><div class="pharmacy-review-status-stack"><span class="badge badge-<?= $statusClass ?>" title="<?= esc($prescription['status_label'], 'attr') ?>"><?= esc($prescription['status_label']) ?></span><span class="badge badge-<?= ($prescription['review_state'] ?? '') === 'Final' ? 'light' : (($prescription['review_state'] ?? '') === 'Clarification requested' ? 'warning' : 'info') ?>" title="<?= esc($prescription['review_state'], 'attr') ?>"><?= esc($prescription['review_state']) ?></span><small><?= esc($prescription['review_date_label'] ?? 'Updated') ?>: <?= esc($prescription['review_date'] ?? 'Not recorded') ?></small><small>Valid until: <?= esc($prescription['valid_until'] ?? 'Not recorded') ?></small></div></td>
                        <td><div class="pharmacy-review-status-stack"><span class="badge badge-light"><?= esc(ucwords(str_replace('_', ' ', $prescription['cart_sync_status'] ?? 'not_synced'))) ?></span><small><?= esc($prescription['shopify_order_reference'] ?? 'Not linked') ?></small></div></td>
                        <td class="text-right"><?= view(PHARMACY_VIEW . 'pages/prescriptions/_actions', ['prescription' => $prescription]) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?= $this->include(PHARMACY_VIEW . 'parts/prescription_pagination') ?>
    </section>
</main>
<?= $this->include(PHARMACY_VIEW . 'pages/prescriptions/_modal') ?>
<?= $this->section('scripts') ?><?= $this->include(PHARMACY_VIEW . 'pages/prescriptions/_scripts') ?><?= $this->endSection() ?>
<?= $this->endSection() ?>
