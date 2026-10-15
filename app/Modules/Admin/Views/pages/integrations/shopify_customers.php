<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<main class="container-fluid py-4" id="shopify-customers-page">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <p class="text-uppercase text-muted small mb-1">Shopify</p>
            <h1 class="h4 mb-1">Customers</h1>
            <p class="text-muted mb-0">Read-only customer lookup and identity review for the configured store.</p>
        </div>
        <a class="btn btn-outline-secondary" href="<?= base_url('admin/integrations/shopify') ?>">Back to Shopify</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white"><h2 class="h6 mb-1">Get Customer Details</h2><p class="small text-muted mb-0">This lookup reads Shopify customer data only and does not create or update a ROOTINS patient.</p></div>
        <div class="card-body">
            <form id="shopify-customer-lookup-form" class="form-inline">
                <label class="sr-only" for="shopify-customer-lookup-id">Shopify customer ID</label>
                <input class="form-control mr-2 mb-2" id="shopify-customer-lookup-id" name="customer_id" placeholder="Customer ID or gid://shopify/Customer/..." required maxlength="80" autocomplete="off">
                <button class="btn btn-primary mb-2" type="submit" id="shopify-customer-lookup-submit">Get Customer Details</button>
            </form>
        </div>
    </div>

    <div class="modal fade" id="shopify-customer-lookup-modal" tabindex="-1" role="dialog" aria-labelledby="shopify-customer-lookup-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="shopify-customer-lookup-title">Shopify Customer Details</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
                <div class="modal-body" id="shopify-customer-lookup-result" aria-live="polite"><div class="text-muted">Enter a customer ID to begin.</div></div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button><button type="button" class="btn btn-outline-primary" id="shopify-customer-lookup-copy" disabled>Copy Customer ID</button></div>
            </div>
        </div>
    </div>

    <?php if ($canManage ?? false): ?>
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-white"><h2 class="h6 mb-1">Customer identity review</h2><p class="small text-muted mb-0">Unmatched and ambiguous Shopify customers require an explicit, audited action.</p></div>
        <div class="card-body p-0">
            <?php if (($customerLinks ?? []) === []): ?>
                <div class="p-4 text-muted">No Shopify customer identities require review.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0"><caption class="sr-only">Shopify customer identity review queue</caption><thead><tr><th>Customer</th><th>Contact snapshot</th><th>Status</th><th>Actions</th></tr></thead><tbody>
                    <?php foreach ($customerLinks as $link): ?>
                        <tr>
                            <td class="text-break"><?= esc($link['shopify_customer_id']) ?></td>
                            <td class="small text-break"><?= esc($link['email_snapshot'] ?: 'No email') ?><br><?= esc($link['mobile_snapshot'] ?: 'No mobile') ?></td>
                            <td><span class="badge badge-light"><?= esc(ucfirst((string) $link['link_status'])) ?></span></td>
                            <td>
                                <?php if ($link['link_status'] !== 'linked'): ?>
                                <form class="shopify-identity-action form-inline mb-2" method="post" action="<?= base_url('admin/integrations/shopify/customers/link') ?>">
                                    <input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>"><input type="hidden" name="link_id" value="<?= (int) $link['id'] ?>"><input type="hidden" name="confirmation" value="I confirm this identity action">
                                    <select class="form-control form-control-sm mr-2" name="patient_id" required <?= ($patientOptions ?? []) === [] ? 'disabled' : '' ?> aria-label="Patient for Shopify customer <?= esc($link['shopify_customer_id']) ?>"><option value="">Select patient</option><?php foreach (($patientOptions ?? []) as $patient): ?><option value="<?= (int) $patient['id'] ?>"><?= esc($patient['registration_number'] . ' — ' . $patient['first_name'] . ' ' . $patient['last_name']) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-outline-primary" type="submit" <?= ($patientOptions ?? []) === [] ? 'disabled' : '' ?>>Link</button>
                                </form>
                                <?php endif; ?>
                                <?php if ($link['link_status'] === 'unmatched' && ! empty($shopify['automatic_patient_creation'])): ?><form class="shopify-identity-action form-inline mb-2" method="post" action="<?= base_url('admin/integrations/shopify/customers/create') ?>"><input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>"><input type="hidden" name="link_id" value="<?= (int) $link['id'] ?>"><input type="hidden" name="confirmation" value="I confirm this identity action"><input class="form-control form-control-sm mr-2" name="date_of_birth" placeholder="dd-mm-yyyy" required aria-label="Date of birth"><button class="btn btn-sm btn-outline-primary" type="submit">Create</button></form><?php endif; ?>
                                <?php if ($link['link_status'] !== 'rejected'): ?><form class="shopify-identity-action d-inline" method="post" action="<?= base_url('admin/integrations/shopify/customers/reject') ?>"><input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>"><input type="hidden" name="link_id" value="<?= (int) $link['id'] ?>"><input type="hidden" name="confirmation" value="I confirm this identity action"><button class="btn btn-sm btn-outline-danger" type="submit">Reject</button></form><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?></tbody></table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>window.rootinsShopifyCustomerLookupUrl = <?= json_encode(base_url('admin/integrations/shopify/customers')) ?>;</script>
<script src="<?= base_url('assets/js/admin/integrations/shopify-customer-lookup.js') ?>"></script>
<script>
document.querySelectorAll('.shopify-identity-action').forEach(function (form) { form.addEventListener('submit', function (event) { event.preventDefault(); var button = form.querySelector('button[type="submit"]'); button.disabled = true; rootinsFetch(form.action, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: new FormData(form) }).then(function (response) { return response.json().then(function (data) { return { response: response, data: data }; }); }).then(function (payload) { window.alert(payload.data.msg || (payload.response.ok ? 'Saved.' : 'The action failed.')); if (payload.response.ok && payload.data.status) window.location.reload(); }).catch(function () { window.alert('The identity action could not be completed safely.'); }).finally(function () { button.disabled = false; }); }); });
</script>
<?= $this->endSection() ?>
