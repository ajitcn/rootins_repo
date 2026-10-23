<?= $this->extend(ADMIN_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<script>(function(){var importId=String(<?= (int) ($import['id'] ?? 0) ?>);var actionBase=<?= json_encode(base_url('admin/integrations/shopify/order-operations')) ?>;document.querySelectorAll('[data-shopify-operation-form]').forEach(function(form){form.dataset.shopifyImportId=importId;form.action=actionBase+'/'+encodeURIComponent(importId)+'/action';});}());</script>
<main class="container-fluid py-4"><div class="d-flex justify-content-between align-items-center mb-4"><div><p class="text-uppercase text-muted small mb-1">Shopify · Order Operations</p><h1 class="h4 mb-1">Order <?= esc($import['shopify_order_number'] ?: $import['shopify_order_id']) ?></h1><p class="text-muted mb-0">Store: <?= esc($import['store_domain']) ?> · Import status: <strong><?= esc($import['status']) ?></strong></p></div><a class="btn btn-outline-secondary" href="<?= base_url('admin/integrations/shopify/order-operations') ?>">Back to queue</a></div><div class="row"><div class="col-lg-8"><div class="card shadow-sm mb-4"><div class="card-header bg-white"><h2 class="h6 mb-0">Order summary</h2></div><div class="card-body"><div class="row"><div class="col-md-4"><strong>Shopify order ID</strong><br><?= esc($import['shopify_order_id']) ?></div><div class="col-md-4"><strong>Customer</strong><br><?= esc($import['customer_name_snapshot'] ?: $import['customer_email_snapshot'] ?: 'Unavailable') ?><br><small><?= esc($import['shopify_customer_id'] ?: 'No customer ID') ?></small></div><div class="col-md-4"><strong>ROOTINS patient</strong><br><?= esc($import['registration_number'] ?: 'Missing') ?><br><small><?= esc($import['rootins_patient_status'] === null ? 'Not linked' : ((int) $import['rootins_patient_status'] === 1 ? 'Active' : 'Inactive')) ?></small></div></div></div></div><div class="card shadow-sm mb-4"><div class="card-header bg-white"><h2 class="h6 mb-0">Line items and medicine mapping</h2></div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Shopify line</th><th>Product / variant</th><th>Quantity</th><th>ROOTINS medicine</th><th>Status</th></tr></thead><tbody><?php foreach (($import['lines'] ?? []) as $line): ?><tr><td><?= esc($line['shopify_line_item_id']) ?></td><td><?= esc($line['title_snapshot'] ?: ($line['shopify_product_id'] . ' / ' . $line['shopify_variant_id'])) ?><br><small><?= esc($line['shopify_product_id'] . ' / ' . ($line['shopify_variant_id'] ?: '—')) ?></small></td><td><?= (int) $line['quantity'] ?></td><td><?= esc($line['medicine_name'] ?: 'Unmapped') ?><?= ($line['medicine_name'] ?? '') !== '' ? '<br><small>' . esc(trim(($line['strength'] ?? '') . ' ' . ($line['unit'] ?? ''))) . '</small>' : '' ?></td><td><?= esc($line['status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div><div class="card shadow-sm"><div class="card-header bg-white"><h2 class="h6 mb-0">History</h2></div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>When</th><th>Action</th><th>Status</th><th>Reason/error</th></tr></thead><tbody><?php foreach (($import['history'] ?? []) as $item): ?><tr><td><?= esc($item['created_at']) ?></td><td><?= esc($item['action']) ?></td><td><?= esc(($item['from_status'] ?? '') . ' → ' . ($item['to_status'] ?? '')) ?></td><td><?= esc($item['reason'] ?: $item['error_message'] ?: '—') ?></td></tr><?php endforeach; ?></tbody></table></div></div></div><div class="col-lg-4"><div class="card shadow-sm mb-4"><div class="card-header bg-white"><h2 class="h6 mb-0">Reconciliation actions</h2></div><div class="card-body"><form class="mb-2" method="post" action="<?= base_url('admin/integrations/shopify/order-operations/' . (int) $import['id'] . '/action') ?>" data-shopify-operation-form><?= csrf_field() ?><input type="hidden" name="action" value="retry_validation"><button class="btn btn-primary btn-block" type="submit">Retry validation/import</button></form><form class="mb-2" method="post" action="<?= base_url('admin/integrations/shopify/order-operations/' . (int) $import['id'] . '/action') ?>" data-shopify-operation-form><?= csrf_field() ?><input type="hidden" name="action" value="mark_ready"><input class="form-control mb-2" name="reason" placeholder="Reason (optional)"><button class="btn btn-outline-primary btn-block" type="submit">Mark ready for import</button></form><form class="mb-2" method="post" action="<?= base_url('admin/integrations/shopify/order-operations/' . (int) $import['id'] . '/action') ?>" data-shopify-operation-form><?= csrf_field() ?><input type="hidden" name="action" value="quarantine"><input class="form-control mb-2" name="reason" placeholder="Quarantine reason" required><button class="btn btn-outline-warning btn-block" type="submit">Quarantine</button></form><form method="post" action="<?= base_url('admin/integrations/shopify/order-operations/' . (int) $import['id'] . '/action') ?>" data-shopify-operation-form><?= csrf_field() ?><input type="hidden" name="action" value="release_quarantine"><button class="btn btn-outline-success btn-block" type="submit">Release quarantine</button></form></div></div><div class="card shadow-sm"><div class="card-body"><strong>Prescription</strong><p class="mb-1"><?= esc($import['prescription_reference'] ?: 'Missing') ?></p><span class="badge badge-light"><?= esc($import['prescription_status'] ?: 'not supplied') ?></span><?php if (!empty($import['prescription_reference'])): ?><p class="small text-muted mt-2 mb-0">Prescription viewing is authorized through the existing private document boundary.</p><?php endif; ?></div></div></div></div></main>
<form class="mt-3" method="post" action="<?= base_url('admin/integrations/shopify/order-operations/' . (int) $import['id'] . '/action') ?>" data-shopify-operation-form><?= csrf_field() ?><input type="hidden" name="action" value="reprocess_paid_appointment"><button class="btn btn-outline-info" type="submit">Reprocess paid appointment</button></form>
<script>(function(){var importId=String(<?= (int) ($import['id'] ?? 0) ?>);var actionBase=<?= json_encode(base_url('admin/integrations/shopify/order-operations')) ?>;document.querySelectorAll('[data-shopify-operation-form]').forEach(function(form){form.dataset.shopifyImportId=importId;form.action=actionBase+'/'+encodeURIComponent(importId)+'/action';});}());</script>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?><script>
(function () {
    document.addEventListener('submit', function (event) {
        var form = event.target.closest('[data-shopify-operation-form]');
        if (!form) return;
        event.preventDefault();
        var button = form.querySelector('button[type="submit"]');
        var isRetry = ['retry_validation', 'retry_import', 'retry_payment', 'reprocess_webhook'].indexOf((form.querySelector('[name="action"]') || {}).value) !== -1;
        if (button && button.disabled) return;
        if (button) button.disabled = true;
        if (typeof window.show_loader === 'function') window.show_loader();
        fetch(form.action, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, body: new FormData(form) })
            .then(function (response) {
                return response.text().then(function (text) {
                    var payload;
                    try { payload = JSON.parse(text); } catch (error) { throw new Error('The server returned an invalid response.'); }
                    if (!response.ok || !payload.status) {
                        var failure = new Error(payload.message || payload.msg || 'Shopify order import could not be completed.');
                        failure.payload = payload;
                        throw failure;
                    }
                    return payload;
                });
            })
            .then(function (payload) {
                var message = payload.message || payload.msg || 'Shopify order import completed.';
                if (isRetry && payload.before_status === payload.after_status && !payload.already_processed) throw new Error('The order status did not change. ' + message);
                if (typeof window.showSuccess === 'function') window.showSuccess(message);
                window.setTimeout(function () { window.location.reload(); }, 350);
            })
            .catch(function (error) {
                var payload = error && error.payload ? error.payload : {};
                var message = payload.message || payload.msg || (error && error.message) || 'Shopify order import could not be completed.';
                if (typeof window.showError === 'function') window.showError(message); else window.alert(message);
            })
            .finally(function () {
                if (button) button.disabled = false;
                if (typeof window.hide_loader === 'function') window.hide_loader();
                document.querySelectorAll('.loading, .is-loading').forEach(function (node) { node.classList.remove('loading', 'is-loading'); node.removeAttribute('aria-busy'); });
            });
    });
}());
</script><?= $this->endSection() ?>
