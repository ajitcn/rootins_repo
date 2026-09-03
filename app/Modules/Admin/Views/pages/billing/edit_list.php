<?= $this->extend(ADMIN_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container" aria-labelledby="edit-bill-title">
    <?= view('templates/_breadcrumb', ['module' => 'admin', 'menu_title' => 'Billing and Invoices', 'page' => 'Edit Bill']) ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3"><div><h1 id="edit-bill-title" class="h4 mb-1">Edit Bill</h1><p class="text-muted mb-0">Select a draft bill to edit. Finalized invoices are locked.</p></div><a href="<?= base_url('admin/billing') ?>" class="btn btn-outline-secondary">View Bill List</a></div>
    <div class="card"><div class="table-responsive"><table class="table mb-0" id="draft-invoices-table"><caption class="sr-only">Draft bills available for editing</caption><thead><tr><th>Invoice</th><th>Patient</th><th>Clinic</th><th>Total</th><th>Created</th><th class="text-right">Action</th></tr></thead><tbody>
        <?php if ($invoices === []): ?><tr><td colspan="6" class="text-center text-muted py-4">No draft bills are available to edit.</td></tr><?php endif; ?>
        <?php foreach ($invoices as $invoice): ?><tr><td><strong><?= esc($invoice['invoice_number']) ?></strong></td><td><?= esc(trim(($invoice['first_name'] ?? '') . ' ' . ($invoice['last_name'] ?? ''))) ?><br><small class="text-muted"><?= esc($invoice['registration_number'] ?? '') ?></small></td><td><?= esc($invoice['clinic_name'] ?? $invoice['clinic_id']) ?></td><td><?= esc($invoice['currency'] . ' ' . number_format((float) $invoice['total'], 2)) ?></td><td><?= esc($invoice['created_at']) ?></td><td class="text-right"><a href="<?= base_url('admin/billing/edit/' . (int) $invoice['id']) ?>" class="btn btn-sm btn-outline-primary">Edit Bill</a></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
</main>
<?= $this->endSection() ?>
