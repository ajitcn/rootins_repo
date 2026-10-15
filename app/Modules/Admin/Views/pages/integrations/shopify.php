<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<main class="container-fluid py-4" id="shopify-integration-page">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <p class="text-uppercase text-muted small mb-1">Integration readiness</p>
            <h1 class="h4 mb-1">Shopify integration</h1>
            <p class="text-muted mb-0">Phase 1 foundation status. Customer, appointment, prescription, and order synchronization is not enabled.</p>
        </div>
        <button class="btn btn-primary" id="shopify-test-connection" type="button">
            <i class="fa-solid fa-plug mr-1" aria-hidden="true"></i>Test connection
        </button>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="small text-muted">Readiness</div>
                    <div class="font-weight-bold <?= $shopify['configured'] ? 'text-success' : 'text-warning' ?>">
                        <?= $shopify['configured'] ? 'Configured' : 'Not configured' ?>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="small text-muted">Store domain</div>
                    <div class="font-weight-bold text-break"><?= esc($shopify['store_domain'] ?: 'Not set') ?></div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="small text-muted">API version</div>
                    <div class="font-weight-bold"><?= esc($shopify['api_version'] ?: 'Not set') ?></div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="small text-muted">Configuration source</div>
                    <div class="font-weight-bold"><?= esc(ucfirst((string) ($shopify['source'] ?? 'unknown'))) ?></div>
                </div>
            </div>
            <hr>
            <p class="small text-muted mb-0">Credentials and encryption material are intentionally never displayed. The active source is shown above.</p>
            <div class="alert alert-light border mt-3 mb-0 small" role="note"><strong>Client-credentials scope:</strong> the Shopify app and store must belong to the same Shopify organization. A different merchant or store requires Shopify installation/OAuth; changing the ROOTINS encryption key, clinic ID, API version, or app URL will not change that organization restriction.</div>
        </div>
    </div>

    <?php if ($canManage ?? false): ?>
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-white"><h2 class="h6 mb-1">Shopify configuration</h2><p class="small text-muted mb-0">Client credentials and webhook secrets are encrypted at rest. Shopify Admin access tokens are short-lived, cached server-side, and never stored here.</p></div>
        <div class="card-body">
            <form id="shopify-configuration-form" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="shopify-config-id" value="">
                <div class="form-row">
                    <div class="form-group col-md-6"><label for="shopify-config-store-domain">Store domain</label><input class="form-control" id="shopify-config-store-domain" name="store_domain" placeholder="example.myshopify.com" required></div>
                    <div class="form-group col-md-6"><label for="shopify-config-client-id">Client ID</label><input class="form-control" id="shopify-config-client-id" name="client_id" autocomplete="off" required></div>
                    <div class="form-group col-md-6"><label for="shopify-config-client-secret">Client secret</label><input class="form-control" id="shopify-config-client-secret" name="client_secret" type="password" autocomplete="new-password"><small class="form-text text-muted">Required on create; blank preserves the existing encrypted credential.</small></div>
                    <div class="form-group col-md-6"><label for="shopify-config-version">API version</label><input class="form-control" id="shopify-config-version" name="api_version" placeholder="2025-01" required></div>
                    <div class="form-group col-md-6"><label for="shopify-config-webhook-secret">Webhook secret</label><input class="form-control" id="shopify-config-webhook-secret" name="webhook_secret" type="password" autocomplete="new-password"><small class="form-text text-muted">Never displayed after submission.</small></div>
                    <div class="form-group col-md-6"><label for="shopify-config-clinic-id">Clinic ID</label><input class="form-control" id="shopify-config-clinic-id" name="clinic_id" type="number" min="1" required><small class="form-text text-muted">Must reference an active clinic.</small></div>
                    <div class="form-group col-md-6"><label for="shopify-config-app-url">Shopify App URL</label><input class="form-control" id="shopify-config-app-url" name="app_url" type="url" placeholder="https://api.rootins.com" required aria-describedby="shopify-config-app-url-help shopify-config-webhook-url"><small class="form-text text-muted" id="shopify-config-app-url-help">This is the public HTTPS URL of the ROOTINS application, not the Shopify store domain.</small><div class="small mt-2"><span class="text-muted">Webhook callback URL:</span> <code id="shopify-config-webhook-url" class="text-break">Not available until a valid App URL is entered.</code> <button type="button" class="btn btn-sm btn-link p-0 ml-1" id="shopify-config-webhook-copy" disabled>Copy</button></div></div>
                    <div class="form-group col-md-6"><label for="shopify-config-encryption-key">Encryption key</label><input class="form-control" id="shopify-config-encryption-key" name="encryption_key" type="password" autocomplete="new-password"><small class="form-text text-muted">Used for signed Shopify handoff tokens; never displayed.</small></div>
                    <div class="form-group col-md-6 d-flex align-items-center"><div class="custom-control custom-switch mt-3"><input class="custom-control-input" id="shopify-config-auto-create" name="auto_create_patients" type="checkbox" value="1"><label class="custom-control-label" for="shopify-config-auto-create">Auto-create patients</label></div></div>
                </div>
                <button class="btn btn-primary" type="submit" id="shopify-config-save">Save configuration</button>
                <button class="btn btn-outline-secondary ml-2" type="button" id="shopify-config-reset">New configuration</button>
            </form>
            <div id="shopify-config-status" class="alert d-none mt-3" role="status" aria-live="polite"></div>
        </div>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-header bg-white"><h2 class="h6 mb-0">Saved configurations</h2></div>
        <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Scope</th><th>Store</th><th>API version</th><th>Secrets</th><th>Status</th><th>Actions</th></tr></thead><tbody>
            <?php foreach (($configurations ?? []) as $configuration): ?>
                <tr><td>Application</td><td><?= esc($configuration['store_domain']) ?></td><td><?= esc($configuration['api_version']) ?></td><td><span class="badge badge-light">Client <?= esc($configuration['client_id_masked'] ?: 'missing') ?></span> <span class="badge badge-light">Webhook <?= esc($configuration['webhook_secret_masked'] ?: 'missing') ?></span> <span class="badge badge-light">Key <?= esc($configuration['encryption_key_masked'] ?: 'missing') ?></span></td><td><?= (int) $configuration['status'] === 1 ? 'Active' : 'Inactive' ?></td><td class="text-nowrap"><button type="button" class="btn btn-sm btn-outline-primary shopify-config-edit" data-config='<?= esc(json_encode($configuration, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), 'attr') ?>'>Edit</button> <button type="button" class="btn btn-sm btn-outline-secondary shopify-config-test" data-id="<?= (int) $configuration['id'] ?>">Test</button> <button type="button" class="btn btn-sm btn-outline-warning shopify-config-rotate" data-id="<?= (int) $configuration['id'] ?>">Rotate credential</button></td></tr>
            <?php endforeach; ?>
            <?php if (($configurations ?? []) === []): ?><tr><td colspan="6" class="text-muted">No database-backed Shopify configuration exists yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
    <?php endif; ?>

</main>

<?php if ($canManage ?? false): ?><div class="modal fade" id="shopify-rotate-secret-modal" tabindex="-1" role="dialog" aria-hidden="true"><div class="modal-dialog" role="document"><div class="modal-content"><form id="shopify-rotate-secret-form"><div class="modal-header"><h2 class="modal-title h5">Rotate Shopify credential</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>"><input type="hidden" name="id" id="shopify-rotate-id"><div class="form-group"><label for="shopify-rotate-type">Credential</label><select class="form-control" name="secret_type" id="shopify-rotate-type"><option value="client_secret">Client secret</option><option value="webhook_secret">Webhook secret</option><option value="encryption_key">Encryption key</option></select></div><div class="form-group"><label for="shopify-rotate-value">Replacement credential</label><input class="form-control" type="password" name="secret_value" id="shopify-rotate-value" autocomplete="new-password" required minlength="16"><small class="form-text text-muted">The value is encrypted and never returned in the response.</small></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-warning">Rotate credential</button></div></form></div></div></div><?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    const shopifyConfigForm = document.getElementById('shopify-configuration-form');
    const shopifyConfigStatus = document.getElementById('shopify-config-status');
    const showShopifyConfigStatus = (message, success) => {
        if (!shopifyConfigStatus) return;
        shopifyConfigStatus.className = `alert mt-3 ${success ? 'alert-success' : 'alert-danger'}`;
        shopifyConfigStatus.textContent = message;
    };
    const showShopifyConnectionResult = (data) => {
        const result = data.data || {};
        const expiry = result.token?.expires_at ? new Date(result.token.expires_at * 1000).toLocaleString() : 'not cached';
        const scopes = (result.granted_scopes || []).join(', ') || 'not returned';
        const clinic = result.clinic || {};
        window.alert(`${data.msg || 'Connection failed.'}\nSource: ${result.configuration_source || 'unknown'}\nStore: ${result.store_domain || 'not set'}\nAPI version: ${result.api_version || 'not set'}\nToken: ${result.token?.status || 'unknown'} (${expiry})\nGranted scopes: ${scopes}\nClinic: ${clinic.status || 'unknown'}${clinic.id ? ` (#${clinic.id})` : ''}`);
    };
    const updateShopifyWebhookUrl = () => {
        const input = document.getElementById('shopify-config-app-url');
        const output = document.getElementById('shopify-config-webhook-url');
        const copy = document.getElementById('shopify-config-webhook-copy');
        if (!input || !output || !copy) return;
        try {
            const url = new URL(input.value.trim());
            if (url.protocol !== 'https:' || url.username || url.password || url.search || url.hash || !url.hostname) throw new Error('invalid');
            output.textContent = url.href.replace(/\/$/, '') + '/integrations/shopify/webhooks';
            copy.disabled = false;
        } catch (error) {
            output.textContent = 'Not available until a valid App URL is entered.';
            copy.disabled = true;
        }
    };
    document.getElementById('shopify-config-app-url')?.addEventListener('input', updateShopifyWebhookUrl);
    document.getElementById('shopify-config-webhook-copy')?.addEventListener('click', function () {
        const output = document.getElementById('shopify-config-webhook-url');
        if (!output || !navigator.clipboard || this.disabled) return;
        const button = this;
        navigator.clipboard.writeText(output.textContent).then(() => { button.textContent = 'Copied'; window.setTimeout(() => { button.textContent = 'Copy'; }, 1200); });
    });
    const resetShopifyConfigForm = () => {
        shopifyConfigForm?.reset();
        const id = document.getElementById('shopify-config-id');
        if (id) id.value = '';
        const save = document.getElementById('shopify-config-save');
        if (save) save.textContent = 'Save configuration';
        updateShopifyWebhookUrl();
    };
    document.getElementById('shopify-config-reset')?.addEventListener('click', resetShopifyConfigForm);
    document.querySelectorAll('.shopify-config-edit').forEach((button) => button.addEventListener('click', () => {
        const config = JSON.parse(button.dataset.config || '{}');
        document.getElementById('shopify-config-id').value = config.id || '';
        document.getElementById('shopify-config-store-domain').value = config.store_domain || '';
        document.getElementById('shopify-config-client-id').value = config.client_id || '';
        document.getElementById('shopify-config-clinic-id').value = config.clinic_id || '';
        document.getElementById('shopify-config-version').value = config.api_version || '';
        document.getElementById('shopify-config-app-url').value = config.app_url || '';
        updateShopifyWebhookUrl();
        document.getElementById('shopify-config-auto-create').checked = !!config.auto_create_patients;
        document.getElementById('shopify-config-client-secret').value = '';
        document.getElementById('shopify-config-webhook-secret').value = '';
        document.getElementById('shopify-config-encryption-key').value = '';
        document.getElementById('shopify-config-save').textContent = 'Update configuration';
        document.getElementById('shopify-config-store-domain').focus();
    }));
    shopifyConfigForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        const button = document.getElementById('shopify-config-save');
        button.disabled = true;
        rootinsFetch('<?= base_url('admin/integrations/shopify/configuration/save') ?>', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: new FormData(shopifyConfigForm) })
            .then((response) => response.json().then((data) => ({ response, data })))
            .then(({ response, data }) => { showShopifyConfigStatus(data.msg || 'Configuration could not be saved.', response.ok && data.status); if (response.ok && data.status) window.setTimeout(() => window.location.reload(), 500); })
            .catch(() => showShopifyConfigStatus('Configuration could not be saved safely.', false))
            .finally(() => { button.disabled = false; });
    });
    document.querySelectorAll('.shopify-config-test').forEach((button) => button.addEventListener('click', () => {
        button.disabled = true;
        const body = new URLSearchParams({ id: button.dataset.id || '', '<?= csrf_token() ?>': '<?= csrf_hash() ?>' });
        rootinsFetch('<?= base_url('admin/integrations/shopify/test-connection') ?>', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body })
            .then((response) => response.json().then((data) => ({ response, data })))
            .then(({ response, data }) => showShopifyConnectionResult(data))
            .catch(() => window.alert('Connection could not be verified safely.'))
            .finally(() => { button.disabled = false; });
    }));
    document.querySelectorAll('.shopify-config-rotate').forEach((button) => button.addEventListener('click', () => {
        document.getElementById('shopify-rotate-id').value = button.dataset.id || '';
        document.getElementById('shopify-rotate-value').value = '';
        $('#shopify-rotate-secret-modal').modal('show');
    }));
    document.getElementById('shopify-rotate-secret-form')?.addEventListener('submit', (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        rootinsFetch('<?= base_url('admin/integrations/shopify/configuration/rotate-secret') ?>', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: new FormData(form) })
            .then((response) => response.json().then((data) => ({ response, data })))
            .then(({ response, data }) => { window.alert(data.msg || 'Secret rotation failed.'); if (response.ok && data.status) window.location.reload(); })
            .catch(() => window.alert('Secret rotation could not be completed safely.'))
            .finally(() => { button.disabled = false; });
    });
</script>
<script>
    document.getElementById('shopify-test-connection')?.addEventListener('click', function () {
        const button = this;
        button.disabled = true;

        rootinsFetch('<?= base_url('admin/integrations/shopify/test-connection') ?>', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: new URLSearchParams({
                '<?= csrf_token() ?>': '<?= csrf_hash() ?>',
            }),
        })
            .then((response) => response.json().then((data) => ({ response, data })))
            .then(({ response, data }) => {
                showShopifyConnectionResult(data);
            })
            .catch(() => window.alert('Connection could not be verified safely.'))
            .finally(() => { button.disabled = false; });
    });
</script>
<script>
    document.getElementById('legacy-shopify-details-form')?.addEventListener('submit', function (event) {
        event.preventDefault();
        const form = event.currentTarget;
        const button = form.querySelector('button[type="submit"]');
        const result = document.getElementById('legacy-shopify-details-result');
        button.disabled = true;
        result.textContent = 'Loading customer details…';
        rootinsFetch('', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: new FormData(form) })
            .then((response) => response.json().then((data) => ({ response, data })))
            .then(({ response, data }) => {
                if (!response.ok || !data.status) throw new Error(data.msg || 'Customer details could not be loaded.');
                const customer = data.data || {};
                result.textContent = `${customer.name || 'Unnamed'} | ${customer.email || 'No email'} | ${customer.mobile || 'No mobile'} | ${customer.store_domain || ''} | Match: ${customer.match_status || 'unmatched'}${customer.linked_patient_reference ? ` | Patient: ${customer.linked_patient_reference}` : ''}`;
            })
            .catch((error) => { result.textContent = error.message || 'Customer details could not be loaded safely.'; })
            .finally(() => { button.disabled = false; });
    });
</script>
<?= $this->endSection() ?>
