<?= $this->extend(ADMIN_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container crm-page">
    <header class="crm-header">
        <div><span class="crm-eyebrow">CRM / LEAD</span><h1><?= esc($lead['full_name']) ?></h1><p><?= esc($lead['lead_number']) ?> · <?= esc($lead['mobile_display'] ?? $lead['mobile_number']) ?> · <?= esc($lead['email'] ?? 'Email not recorded') ?></p></div>
        <a class="btn btn-outline-dark" href="<?= base_url('admin/crm/leads') ?>">Back to leads</a>
    </header>
    <section class="card crm-card">
        <div class="crm-form-grid">
            <div><strong>Status</strong><p><?= esc($lead['status_name'] ?? $lead['lead_status_id']) ?></p></div>
            <div><strong>Priority</strong><p><?= esc(ucfirst($lead['priority'])) ?></p></div>
            <div><strong>Phone</strong><p><?= esc($lead['mobile_display'] ?? $lead['mobile_number'] ?? 'Phone not recorded') ?></p></div>
            <div><strong>Email</strong><p><?= esc($lead['email'] ?? 'Email not recorded') ?></p></div>
            <div><strong>Interested service</strong><p><?= esc($lead['service_name'] ?? $lead['interested_service'] ?? '—') ?></p></div>
            <div class="crm-wide"><strong>Remarks</strong><p><?= nl2br(esc($lead['remarks'] ?? '—')) ?></p></div>
        </div>
    </section>
    <?php if (!empty($lead['ai_analyses'])): ?>
    <section class="card crm-card mt-3">
        <div class="crm-card-head"><div><h2>AI analysis history</h2><p>Saved Shopify analysis records linked to this CRM lead.</p></div></div>
        <div class="table-responsive"><table class="table crm-table"><thead><tr><th>Type / reference</th><th>Status</th><th>Customer details</th><th>Images</th><th>Created</th><th>Result</th></tr></thead><tbody>
        <?php foreach ($lead['ai_analyses'] as $analysis): ?>
            <tr><td><strong><?= esc(ucfirst($analysis['type'])) ?> analysis</strong><small><?= esc($analysis['reference']) ?></small></td><td><?= esc(ucwords(str_replace('_', ' ', $analysis['status'] ?: 'Unknown'))) ?></td><td><?= esc(trim(($analysis['first_name'] ?? '') . ' ' . ($analysis['last_name'] ?? ''))) ?><small><?= esc($analysis['email'] ?: 'Email not recorded') ?></small></td><td><?= (int) $analysis['image_count'] ?></td><td><?= esc($analysis['created_at'] ?? '—') ?></td><td><a class="btn btn-sm btn-outline-primary" href="<?= esc($analysis['detail_url']) ?>">View analysis</a></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </section>
    <?php endif; ?>
</main>
<?= $this->endSection() ?>
