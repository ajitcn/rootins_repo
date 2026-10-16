<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<main class="container-fluid py-4" id="shopify-webhooks-page">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <p class="text-uppercase text-muted small mb-1">Shopify integration</p>
            <h1 class="h4 mb-1">Webhooks</h1>
            <p class="text-muted mb-0">Required topics, secure callback details, minimized payload definitions, and observed processing state.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center">
            <?php if ($canManage ?? false): ?><button type="button" class="btn btn-primary mr-2" id="shopify-webhooks-review" data-action="preview" data-action-url="<?= base_url('admin/integrations/shopify/webhooks/preview') ?>">Review &amp; Apply Approved Topics</button><button type="button" class="btn btn-outline-secondary mr-2" id="shopify-webhooks-verify-live" data-action="verify-live-delivery" data-action-url="<?= base_url('admin/integrations/shopify/webhooks/verify-live-delivery') ?>">Verify Live Delivery</button><button type="button" class="btn btn-outline-primary mr-2" id="shopify-webhooks-validate-all">Validate All</button><?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= base_url('admin/integrations/shopify') ?>">Back to Shopify integration</a>
        </div>
    </div>

    <?php if ($canManage ?? false): ?><div id="shopify-webhook-management-status" class="alert d-none" role="status" aria-live="polite"></div><div id="shopify-webhook-management-results" class="table-responsive d-none" style="max-height: 24rem; overflow-y: auto;"><table class="table table-sm bg-white border mb-3"><thead class="thead-light"><tr><th>Topic</th><th>Callback URL</th><th>Status</th><th>HTTP</th><th>Error</th></tr></thead><tbody></tbody></table></div><?php endif; ?>

    <div class="alert alert-light border small" role="note">
        <strong>ROOTINS Endpoint Test</strong> generates a synthetic signed request locally; it is not evidence of Shopify delivery. <strong>Verify Live Delivery</strong> reports only real events already received by ROOTINS.
        <br>
        The approved list is limited to order, payment/refund, and fulfillment/delivery topics. Review the live Shopify subscriptions before explicitly applying any create or delete operation.
        Every request must use <code>POST</code>, include <code>X-Shopify-Hmac-SHA256</code>, and include <code>X-Shopify-Shop-Domain</code> for store scope validation.
    </div>

    <?php if (($callbackBase ?? '') === ''): ?>
        <div class="alert alert-warning" role="alert">A valid public HTTPS Shopify app URL is not configured. Callback URLs are intentionally not generated from localhost or private addresses.</div>
    <?php else: ?>
        <div class="card shadow-sm mb-4">
            <div class="card-body d-flex flex-wrap align-items-center justify-content-between">
                <div><div class="small text-muted">Public callback base</div><code class="text-break" id="shopify-webhook-callback-base"><?= esc($callbackBase) ?></code></div>
                <button type="button" class="btn btn-sm btn-outline-primary mt-2 mt-md-0 shopify-copy" data-copy-target="#shopify-webhook-callback-base">Copy callback base</button>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($canManage ?? false): ?>
        <div class="modal fade" id="shopify-webhook-review-modal" tabindex="-1" role="dialog" aria-labelledby="shopify-webhook-review-title" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
                <div class="modal-content">
                    <div class="modal-header"><h2 class="modal-title h5" id="shopify-webhook-review-title">Review Shopify webhook changes</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
                    <div class="modal-body" id="shopify-webhook-review-body" aria-live="polite"><p class="text-muted mb-0">Querying the configured Shopify store…</p></div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="shopify-webhook-apply" disabled>Confirm and apply changes</button></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <input type="hidden" id="shopify-webhooks-csrf" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">

    <div class="card shadow-sm">
        <div class="card-header bg-white"><h2 class="h6 mb-0">Webhook catalog</h2></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <caption class="sr-only">Shopify webhook topics and ROOTINS processing behavior</caption>
                <thead class="thead-light">
                    <tr><th>Topic</th><th>Purpose and processing</th><th>Callback URL</th><th>Payload fields</th><th>Security/scope and response</th><th>Status</th><?php if ($canManage ?? false): ?><th>Actions</th><?php endif; ?></tr>
                </thead>
                <tbody>
                <?php foreach (($webhooks ?? []) as $webhook): ?>
                    <?php $fields = implode("\n", $webhook['required_fields']); ?>
                    <tr>
                        <td class="text-nowrap"><code><?= esc($webhook['topic']) ?></code><br><span class="badge badge-<?= $webhook['required'] ? 'primary' : 'light' ?> mt-1"><?= $webhook['required'] ? 'Required' : 'Optional' ?></span></td>
                        <td><strong><?= esc($webhook['purpose']) ?></strong><div class="small text-muted mt-1"><?= esc($webhook['processing']) ?></div></td>
                        <td class="webhook-url-cell"><code class="small text-break d-block" id="webhook-url-<?= esc(str_replace(['/', '-'], '_', $webhook['topic'])) ?>"><?= esc($webhook['callback_url'] ?: 'Public HTTPS app URL not configured') ?></code><button type="button" class="btn btn-sm btn-link px-0 shopify-copy" data-copy-target="#webhook-url-<?= esc(str_replace(['/', '-'], '_', $webhook['topic'])) ?>" <?= $webhook['callback_url'] === '' ? 'disabled' : '' ?>>Copy URL</button><div><code><?= esc($webhook['method']) ?></code></div></td>
                        <td><details><summary><?= count($webhook['required_fields']) ?> required fields</summary><pre class="small mt-2 mb-1 text-wrap" id="webhook-fields-<?= esc(str_replace(['/', '-'], '_', $webhook['topic'])) ?>"><?= esc($fields) ?></pre><button type="button" class="btn btn-sm btn-link p-0 shopify-copy" data-copy-target="#webhook-fields-<?= esc(str_replace(['/', '-'], '_', $webhook['topic'])) ?>">Copy fields</button></details></td>
                        <td class="small"><div><span class="badge badge-light">HMAC <?= esc($webhook['hmac']) ?></span></div><div class="mt-1"><?= esc($webhook['scope']) ?></div><div class="mt-1 text-muted"><?= esc($webhook['duplicate_behavior']) ?></div><div class="mt-1"><strong>Response:</strong> <?= esc($webhook['expected_response']) ?></div></td>
                        <td class="small"><span class="badge badge-<?= in_array($webhook['status'], ['Failed'], true) ? 'danger' : ($webhook['status'] === 'Received' ? 'success' : 'secondary') ?>"><?= esc($webhook['status']) ?></span><div class="mt-1">Live status: <?= esc($webhook['live_status']) ?></div><div>Last received: <?= esc($webhook['last_received'] ?: 'Never') ?></div><div>Last processed: <?= esc($webhook['last_processed'] ?: 'Never') ?></div><div>Processing: <?= esc($webhook['last_result']) ?></div><?php if (! empty($webhook['last_event_reference'])): ?><div>Event ref: <code><?= esc(substr($webhook['last_event_reference'], 0, 16)) ?>…</code></div><?php endif; ?><?php if (! empty($webhook['last_error_category'])): ?><div>Error: <?= esc($webhook['last_error_category']) ?></div><?php endif; ?></td>
                        <?php if ($canManage ?? false): ?><td class="text-nowrap"><button type="button" class="btn btn-sm btn-outline-primary shopify-webhook-validate" data-action="validate" data-action-url="<?= base_url('admin/integrations/shopify/webhooks/validate/' . $webhook['topic']) ?>" data-topic="<?= esc($webhook['topic'], 'attr') ?>">Validate</button> <button type="button" class="btn btn-sm btn-outline-secondary shopify-webhook-test" data-action="test-endpoint" data-action-url="<?= base_url('admin/integrations/shopify/webhooks/test-endpoint/' . $webhook['topic']) ?>" data-topic="<?= esc($webhook['topic'], 'attr') ?>">ROOTINS Endpoint Test</button><div class="small mt-2 webhook-validation-result" data-result-topic="<?= esc($webhook['topic'], 'attr') ?>">Not validated</div></td><?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    window.rootinsShopifyWebhookValidateBase = <?= json_encode(base_url('admin/integrations/shopify/webhooks/validate/')) ?>;
    window.rootinsShopifyWebhookTestBase = <?= json_encode(base_url('admin/integrations/shopify/webhooks/test-endpoint/')) ?>;
    window.rootinsShopifyWebhookValidateAll = <?= json_encode(base_url('admin/integrations/shopify/webhooks/validate-all')) ?>;
    window.rootinsShopifyWebhookPreview = <?= json_encode(base_url('admin/integrations/shopify/webhooks/preview')) ?>;
    window.rootinsShopifyWebhookApply = <?= json_encode(base_url('admin/integrations/shopify/webhooks/apply')) ?>;
    window.rootinsShopifyWebhookConfirmation = <?= json_encode(\App\Services\Shopify\ShopifyWebhookRegistrationService::CONFIRMATION) ?>;
    window.rootinsShopifyWebhookSync = <?= json_encode(base_url('admin/integrations/shopify/webhooks/sync')) ?>;
    window.rootinsShopifyWebhookRegisterMissing = <?= json_encode(base_url('admin/integrations/shopify/webhooks/register-missing')) ?>;
</script>
<script src="<?= base_url('assets/js/admin/integrations/shopify-webhooks.js?v=20261016-1') ?>"></script>
<?= $this->endSection() ?>
