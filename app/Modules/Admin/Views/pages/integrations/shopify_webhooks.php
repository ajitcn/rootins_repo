<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php
$webhookRows = $webhooks ?? [];
$requiredCount = count($webhookRows);
$missingCount = count(array_filter($webhookRows, static fn (array $row): bool => ($row['status'] ?? '') === 'Not configured'));
$failedCount = count(array_filter($webhookRows, static fn (array $row): bool => ($row['status'] ?? '') === 'Failed' || ! empty($row['last_error_category'])));
$registeredCount = max(0, $requiredCount - $missingCount - $failedCount);
$statusClass = static function (array $row): string {
    if (($row['status'] ?? '') === 'Failed' || ! empty($row['last_error_category'])) return 'is-failed';
    if (($row['status'] ?? '') === 'Not configured') return 'is-missing';
    if (! empty($row['last_processed'])) return 'is-processed';
    return 'is-registered';
};
$statusLabel = static function (array $row): string {
    if (($row['status'] ?? '') === 'Failed' || ! empty($row['last_error_category'])) return 'Failed';
    if (($row['status'] ?? '') === 'Not configured') return 'Missing';
    if (! empty($row['last_processed'])) return 'Processed';
    return 'Registered';
};
?>
<main class="container-fluid py-4 shopify-webhooks-page" id="shopify-webhooks-page">
    <div class="shopify-webhooks-shell">
        <header class="shopify-webhooks-header">
            <div><p class="shopify-eyebrow">Shopify integration</p><h1 class="h3 mb-1">Shopify Webhooks</h1><p class="text-muted mb-0">Manage and verify Shopify event subscriptions.</p></div>
            <a class="btn btn-outline-secondary" href="<?= base_url('admin/integrations/shopify') ?>">Back to Shopify integration</a>
        </header>

        <section class="shopify-webhooks-summary" aria-label="Webhook status summary">
            <div class="shopify-webhooks-summary-card"><span class="shopify-summary-label">Required topics</span><strong><?= $requiredCount ?></strong><span class="shopify-summary-meta">Approved catalog</span></div>
            <div class="shopify-webhooks-summary-card"><span class="shopify-summary-label">Registered</span><strong><?= $registeredCount ?></strong><span class="shopify-summary-meta">Ready subscriptions</span></div>
            <div class="shopify-webhooks-summary-card"><span class="shopify-summary-label">Missing</span><strong><?= $missingCount ?></strong><span class="shopify-summary-meta">Need registration</span></div>
            <div class="shopify-webhooks-summary-card"><span class="shopify-summary-label">Failed</span><strong><?= $failedCount ?></strong><span class="shopify-summary-meta">Needs attention</span></div>
            <div class="shopify-webhooks-summary-card"><span class="shopify-summary-label">Last checked</span><strong id="shopify-webhooks-last-checked">Not checked</strong><span class="shopify-summary-meta">Live subscription status</span></div>
        </section>

        <?php if ($canManage ?? false): ?>
            <!-- Review &amp; Apply Approved Topics is retained as the confirmation workflow behind management actions. -->
            <section class="shopify-webhooks-action-bar" aria-label="Webhook actions">
                <div class="shopify-action-group"><span class="shopify-action-label">Subscription management</span><div class="shopify-action-buttons"><button type="button" class="btn btn-dark" id="shopify-webhooks-sync" data-action="sync" data-action-url="<?= base_url('admin/integrations/shopify/webhooks/preview') ?>" data-result-channel="sync">Sync Webhooks</button><button type="button" class="btn btn-outline-dark" id="shopify-webhooks-register-missing" data-action="register-missing" data-action-url="<?= base_url('admin/integrations/shopify/webhooks/preview') ?>" data-result-channel="registration">Register Missing Webhooks</button><button type="button" class="btn btn-outline-danger" id="shopify-webhooks-remove-unwanted" data-action="remove-unwanted" data-action-url="<?= base_url('admin/integrations/shopify/webhooks/preview') ?>" data-result-channel="removal">Remove Unwanted Webhooks</button></div></div>
                <div class="shopify-action-group shopify-action-group-secondary"><span class="shopify-action-label">Endpoint verification</span><div class="shopify-action-buttons"><button type="button" class="btn btn-outline-primary" id="shopify-webhooks-validate-all" data-result-channel="validation">Validate Endpoints</button><button type="button" class="btn btn-outline-secondary" id="shopify-webhooks-verify-live" data-action="verify-live-delivery" data-action-url="<?= base_url('admin/integrations/shopify/webhooks/verify-live-delivery') ?>" data-result-channel="live">Verify Live Delivery</button></div></div>
            </section>
        <?php endif; ?>

        <div class="shopify-webhooks-workflow-note" role="note"><span><strong>ROOTINS Endpoint Test</strong> uses a synthetic signed request. <strong>Verify Live Delivery</strong> reports real Shopify events already received.</span><span><strong>Sync</strong> compares subscriptions; <strong>registration</strong> creates missing topics after confirmation. Store ownership, HMAC, idempotency, and approved-topic checks remain enforced via <code>X-Shopify-Hmac-SHA256</code> and <code>X-Shopify-Shop-Domain</code>.</span></div>

        <?php if (($callbackBase ?? '') === ''): ?><div class="alert alert-warning" role="alert">A valid public HTTPS Shopify app URL is not configured. Callback URLs are intentionally not generated from localhost or private addresses.</div><?php else: ?><div class="shopify-webhooks-callback-bar"><div><span class="shopify-summary-label">Public callback base</span><code class="text-break" id="shopify-webhook-callback-base"><?= esc($callbackBase) ?></code></div><button type="button" class="btn btn-sm btn-outline-secondary shopify-copy" data-copy-target="#shopify-webhook-callback-base">Copy callback base</button></div><?php endif; ?>

        <?php if ($canManage ?? false): ?><div id="shopify-webhook-management-status" class="alert d-none" role="status" aria-live="polite"></div><div class="shopify-webhooks-result-grid" aria-live="polite"><div id="shopify-webhook-sync-result" class="shopify-webhooks-result d-none"></div><div id="shopify-webhook-registration-result" class="shopify-webhooks-result d-none"></div><div id="shopify-webhook-removal-result" class="shopify-webhooks-result d-none"></div><div id="shopify-webhook-validation-result" class="shopify-webhooks-result d-none"></div><div id="shopify-webhook-live-result" class="shopify-webhooks-result d-none"></div></div><?php endif; ?>

        <section class="card shopify-webhook-catalog-card"><div class="card-header"><div><h2 class="h5 mb-1">Approved webhook topics</h2><p class="text-muted small mb-0">Each topic uses the configured HTTPS JSON callback and remains protected by the existing Shopify validation flow.</p></div></div><div class="shopify-webhook-table-wrap"><table class="table mb-0 shopify-webhook-table"><caption class="sr-only">Shopify webhook topics and ROOTINS processing behavior</caption><thead><tr><th>Event</th><th>Purpose</th><th>Callback URL</th><th>Delivery</th><th>Status</th><th>Last received</th><th>Last processed</th><?php if ($canManage ?? false): ?><th>Actions</th><?php endif; ?></tr></thead><tbody>
            <?php if ($webhookRows === []): ?><tr><td colspan="<?= ($canManage ?? false) ? '8' : '7' ?>"><div class="shopify-webhooks-empty"><strong>No approved webhook topics are available.</strong><span>Check the Shopify integration configuration and permissions.</span></div></td></tr><?php endif; ?>
            <?php foreach ($webhookRows as $webhook): ?><?php $key = str_replace(['/', '-'], '_', $webhook['topic']); $url = $webhook['callback_url'] ?? ''; ?><tr><td><div class="shopify-webhook-event"><code><?= esc($webhook['topic']) ?></code><span class="badge badge-light">Approved</span></div></td><td><div class="shopify-webhook-purpose"><?= esc($webhook['purpose']) ?></div><span class="small text-muted"><?= esc($webhook['processing']) ?></span></td><td><div class="shopify-webhook-url"><code id="webhook-url-<?= esc($key) ?>"><?= esc($url ?: 'Public HTTPS app URL not configured') ?></code></div><button type="button" class="btn btn-sm btn-link shopify-copy px-0" data-copy-target="#webhook-url-<?= esc($key) ?>" <?= $url === '' ? 'disabled' : '' ?>>Copy URL</button></td><td><span class="badge badge-light">POST</span><span class="badge badge-light ml-1">JSON</span><div class="small text-muted mt-1">HMAC protected</div></td><td><span class="shopify-webhook-status <?= esc($statusClass($webhook)) ?>"><?= esc($statusLabel($webhook)) ?></span><?php if (! empty($webhook['last_error_category'])): ?><div class="small text-muted mt-1"><?= esc($webhook['last_error_category']) ?></div><?php endif; ?></td><td class="small"><?= esc($webhook['last_received'] ?: 'Never') ?></td><td class="small"><?= esc($webhook['last_processed'] ?: 'Never') ?></td><?php if ($canManage ?? false): ?><td><div class="dropdown"><button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false">More Actions</button><div class="dropdown-menu dropdown-menu-right"><button type="button" class="dropdown-item shopify-webhook-validate" data-action="validate" data-action-url="<?= base_url('admin/integrations/shopify/webhooks/validate/' . $webhook['topic']) ?>" data-topic="<?= esc($webhook['topic'], 'attr') ?>">Validate subscription</button><button type="button" class="dropdown-item shopify-webhook-test" data-action="test-endpoint" data-action-url="<?= base_url('admin/integrations/shopify/webhooks/test-endpoint/' . $webhook['topic']) ?>" data-topic="<?= esc($webhook['topic'], 'attr') ?>">ROOTINS Endpoint Test</button><button type="button" class="dropdown-item shopify-copy" data-copy-target="#webhook-url-<?= esc($key) ?>">Copy URL</button></div></div><div class="small mt-2 webhook-validation-result" data-result-topic="<?= esc($webhook['topic'], 'attr') ?>">Not validated</div></td><?php endif; ?></tr><?php endforeach; ?>
        </tbody></table></div></section>
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
<div class="modal fade" id="shopify-webhook-review-modal" tabindex="-1" role="dialog" aria-labelledby="shopify-webhook-review-title" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable" role="document"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5" id="shopify-webhook-review-title">Review Shopify webhook changes</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body" id="shopify-webhook-review-body" aria-live="polite"><p class="text-muted mb-0">Querying the configured Shopify store...</p></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="shopify-webhook-apply" disabled>Confirm and apply changes</button></div></div></div></div>
<input type="hidden" id="shopify-webhooks-csrf" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">
<script src="<?= base_url('assets/js/admin/integrations/shopify-webhooks.js?v=20261016-2') ?>"></script>
<?= $this->endSection() ?>
