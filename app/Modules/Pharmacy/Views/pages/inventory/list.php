<?= $this->extend(PHARMACY_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container pharmacy-page pharmacy-inventory-page" aria-labelledby="pharmacy-inventory-title">
    <?= $this->include(PHARMACY_VIEW . 'pages/inventory/_toolbar') ?>
    <section class="card pharmacy-inventory-card" aria-label="Central medicine stock list">
        <div class="table-responsive"><table class="table pharmacy-inventory-table mb-0"><caption class="sr-only">Central medicine stock</caption><thead><tr><th scope="col">Medicine</th><th scope="col">Batch</th><th scope="col">Expiry date</th><th scope="col">Quantity</th><th scope="col">Stock status</th><th scope="col" class="text-right">Actions</th></tr></thead><tbody>
        <?php if (($rows ?? []) === []): ?><tr><td colspan="6"><div class="pharmacy-inventory-empty" role="status"><i class="bi bi-box-seam d-block" aria-hidden="true"></i><strong>No central medicine batches found</strong><span>Try changing the search or filters.</span></div></td></tr><?php else: ?><?php foreach ($rows as $row): ?><tr class="<?= !empty($row['is_near_expiry']) ? 'inventory-row-near-expiry' : '' ?>"><td><span class="font-weight-bold text-truncate d-block" title="<?= esc($row['medicine_name'], 'attr') ?>"><?= esc($row['medicine_name']) ?></span><small class="text-muted">Active medicine master</small></td><td><code><?= esc($row['batch_number']) ?></code></td><td><span class="d-block"><?= esc($row['expiry_display']) ?></span><small class="badge badge-<?= esc($row['expiry_class']) ?>"><?= esc($row['expiry_label']) ?></small></td><td><strong><?= (int) $row['quantity_available'] ?></strong></td><td><span class="badge badge-<?= esc($row['availability_class']) ?> inventory-status"><span class="inventory-status-dot" aria-hidden="true"></span><?= esc($row['availability_label']) ?></span><small class="d-block text-muted mt-1"><?= esc($row['unit']) ?></small></td><td class="text-right"><?= view(PHARMACY_VIEW . 'pages/inventory/_actions', ['row' => $row]) ?></td></tr><?php endforeach; ?><?php endif; ?></tbody></table></div>
        <?= $this->include(PHARMACY_VIEW . 'parts/inventory_pagination') ?>
    </section>
</main>
<?= $this->include(PHARMACY_VIEW . 'pages/inventory/_detail_modal') ?>
<?= $this->section('scripts') ?><?= $this->include(PHARMACY_VIEW . 'pages/inventory/_scripts') ?><?= $this->endSection() ?>
<?= $this->endSection() ?>
