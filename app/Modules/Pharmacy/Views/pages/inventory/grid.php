<?= $this->extend(PHARMACY_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container pharmacy-page pharmacy-inventory-page" aria-labelledby="pharmacy-inventory-title">
    <?= $this->include(PHARMACY_VIEW . 'pages/inventory/_toolbar') ?>
    <section class="pharmacy-inventory-grid" aria-label="Central medicine stock cards">
        <?php if (($rows ?? []) === []): ?><div class="card pharmacy-inventory-empty" role="status"><i class="bi bi-box-seam d-block" aria-hidden="true"></i><strong>No central medicine batches found</strong><span>Try changing the search or filters.</span></div><?php else: ?><?php foreach ($rows as $row): ?><article class="card inventory-card <?= !empty($row['is_near_expiry']) ? 'inventory-card-near-expiry' : '' ?>"><div class="inventory-card-header"><div><div class="inventory-card-label">Medicine</div><h2 class="h6 mb-0 text-truncate" title="<?= esc($row['medicine_name'], 'attr') ?>"><?= esc($row['medicine_name']) ?></h2></div><?= view(PHARMACY_VIEW . 'pages/inventory/_actions', ['row' => $row]) ?></div><div class="inventory-card-meta"><div><span class="inventory-card-label">Batch</span><strong><?= esc($row['batch_number']) ?></strong></div><div><span class="inventory-card-label">Expiry</span><strong><?= esc($row['expiry_display']) ?></strong><span class="badge badge-<?= esc($row['expiry_class']) ?> ml-1"><?= esc($row['expiry_label']) ?></span></div></div><div class="inventory-card-footer"><div><span class="inventory-card-label">Available quantity</span><strong class="inventory-quantity"><?= (int) $row['quantity_available'] ?> <small><?= esc($row['unit']) ?></small></strong></div><span class="badge badge-<?= esc($row['availability_class']) ?> inventory-status"><span class="inventory-status-dot" aria-hidden="true"></span><?= esc($row['availability_label']) ?></span></div></article><?php endforeach; ?><?php endif; ?>
    </section>
    <?= $this->include(PHARMACY_VIEW . 'parts/inventory_pagination') ?>
</main>
<?= $this->include(PHARMACY_VIEW . 'pages/inventory/_detail_modal') ?>
<?= $this->section('scripts') ?><?= $this->include(PHARMACY_VIEW . 'pages/inventory/_scripts') ?><?= $this->endSection() ?>
<?= $this->endSection() ?>
