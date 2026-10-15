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
            <?php if ($canManage ?? false): ?><button type="button" class="btn btn-primary mr-2" id="shopify-webhooks-validate-all">Validate All</button><?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= base_url('admin/integrations/shopify') ?>">Back to Shopify integration</a>
        </div>
    </div>

    <div class="alert alert-light border small" role="note">
        Register these topics in the Shopify Dev Dashboard using a public HTTPS ROOTINS URL. ROOTINS does not automatically register or modify Shopify webhook subscriptions.
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
                        <td class="small text-nowrap"><span class="badge badge-<?= $webhook['status'] === 'Failed' ? 'danger' : ($webhook['status'] === 'Received' ? 'success' : 'secondary') ?>"><?= esc($webhook['status']) ?></span><div class="mt-1">Last received: <?= esc($webhook['last_received'] ?: 'Never') ?></div><div>Last processed: <?= esc($webhook['last_processed'] ?: 'Never') ?></div><div>Result: <?= esc($webhook['last_result']) ?></div></td>
                        <?php if ($canManage ?? false): ?><td class="text-nowrap"><button type="button" class="btn btn-sm btn-outline-primary shopify-webhook-validate" data-topic="<?= esc($webhook['topic'], 'attr') ?>">Validate</button> <button type="button" class="btn btn-sm btn-outline-secondary shopify-webhook-test" data-topic="<?= esc($webhook['topic'], 'attr') ?>">Test Endpoint</button><div class="small mt-2 webhook-validation-result" data-result-topic="<?= esc($webhook['topic'], 'attr') ?>">Not validated</div></td><?php endif; ?>
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
</script>
<script src="<?= base_url('assets/js/admin/integrations/shopify-webhooks.js') ?>"></script>
<?= $this->endSection() ?>
