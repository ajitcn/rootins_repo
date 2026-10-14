<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/admin/core-api.css?v=20261008-core-api-ui') ?>">
<?= $this->endSection() ?>

<?= $this->section('main-content') ?>
<main class="container-fluid py-4" id="core-api-page" data-base-url="<?= esc($apiBaseUrl) ?>" data-create-url="<?= base_url('admin/integrations/core-api/create') ?>" data-action-base="<?= base_url('admin/integrations/core-api') ?>">
    <div class="core-api-page-header d-flex flex-wrap align-items-center justify-content-between">
        <div class="core-api-page-heading">
            <p class="core-api-eyebrow text-uppercase text-muted small mb-1">Integration management</p>
            <h1 class="mb-1">ROOTINS Core API</h1>
            <p class="text-muted mb-0">Application-wide signed API access for approved integrations.</p>
        </div>
        <?php if ($canCreate): ?><button class="btn btn-primary core-api-create-button" type="button" data-toggle="modal" data-target="#core-api-create-modal"><i class="fa-solid fa-plus mr-1" aria-hidden="true"></i>Create credential</button><?php endif; ?>
    </div>

    <div id="core-api-alert" class="alert d-none" role="alert"></div>

    <div class="card core-api-panel mb-4">
        <div class="card-body core-api-base-url-card">
            <div class="small text-muted">API base URL</div>
            <div class="input-group mt-1">
                <input class="form-control" id="core-api-base-url" value="<?= esc($apiBaseUrl) ?>" readonly aria-label="Core API base URL">
                <div class="input-group-append"><button class="btn btn-outline-secondary" type="button" data-copy-target="#core-api-base-url">Copy</button></div>
            </div>
            <p class="small text-muted mt-2 mb-0">Secrets are encrypted at rest and are never included in this page or its source.</p>
        </div>
    </div>
    <div class="card core-api-panel mb-4">
        <div class="card-header core-api-card-header d-flex justify-content-between align-items-center"><div><h2 class="h6 mb-1">Verified client signature example</h2><p class="small text-muted mb-0">This PHP/cURL example matches the server implementation. Replace only the placeholders.</p></div><button class="btn btn-sm btn-outline-primary" type="button" id="core-api-copy-example"><i class="fa-regular fa-copy mr-1" aria-hidden="true"></i>Copy PHP example</button></div>
        <div class="card-body"><pre class="core-api-code-block mb-0" id="core-api-client-example"><code><?= esc($clientExample) ?></code></pre></div>
    </div>

    <div class="card core-api-panel mb-4">
        <div class="card-header core-api-card-header d-flex justify-content-between align-items-center"><div><h2 class="h6 mb-1">Active API credentials</h2><p class="small text-muted mb-0">Only public metadata is shown. Rotate immediately if a secret is lost.</p></div><div class="core-api-header-actions"><button class="btn btn-sm btn-outline-primary" type="button" id="core-api-copy-instructions"><i class="fa-regular fa-copy mr-1" aria-hidden="true"></i>Copy instructions</button><button class="btn btn-sm btn-outline-secondary" type="button" id="core-api-download-instructions"><i class="fa-solid fa-download mr-1" aria-hidden="true"></i>Download instructions</button></div></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle core-api-credentials-table">
                    <caption class="sr-only">Core API credentials</caption>
                    <thead><tr><th>API key ID</th><th>Status</th><th>Rate limit</th><th>Created</th><th>Last used</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if ($credentials === []): ?><tr><td colspan="6"><div class="core-api-empty-state"><i class="fa-solid fa-key" aria-hidden="true"></i><strong>No API credentials yet</strong><span>Create an application-wide credential to begin signing Core API requests.</span></div></td></tr><?php endif; ?>
                    <?php foreach ($credentials as $credential): ?>
                        <tr data-credential-row="<?= (int) $credential['id'] ?>">
                            <td data-label="API key ID"><code class="core-api-key-id"><?= esc($credential['key_id']) ?></code></td>
                            <td data-label="Status"><span class="badge <?= $credential['status'] === 'active' ? 'badge-success' : 'badge-secondary' ?> core-api-status-badge"><?= esc(ucfirst($credential['status'])) ?></span></td>
                            <td data-label="Rate limit"><span class="core-api-rate-limit"><?= (int) $credential['rate_limit_per_minute'] ?><small>/min</small></span></td>
                            <td><?= esc($credential['created_at'] ?: '—') ?></td>
                            <td data-label="Last used"><time datetime="<?= esc((string) ($credential['last_used_at'] ?? ''), 'attr') ?>"><?= esc($credential['last_used_at'] ?: 'Never') ?></time></td>
                            <td data-label="Actions" class="core-api-actions-cell">
                                <div class="core-api-action-group">
                                <?php if ($canManage && $credential['status'] === 'active'): ?>
                                    <button class="btn btn-sm btn-outline-primary js-core-api-test" data-id="<?= (int) $credential['id'] ?>" type="button"><i class="fa-solid fa-plug mr-1" aria-hidden="true"></i>Test</button>
                                <?php endif; ?>
                                    <div class="dropdown core-api-more-actions">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fa-solid fa-ellipsis-vertical mr-1" aria-hidden="true"></i>More Actions</button>
                                        <div class="dropdown-menu dropdown-menu-right">
                                            <?php if ($canManage && $credential['status'] === 'active'): ?>
                                                <button class="dropdown-item js-core-api-rotate" data-id="<?= (int) $credential['id'] ?>" type="button"><i class="fa-solid fa-arrows-rotate mr-2" aria-hidden="true"></i>Rotate credential</button>
                                                <button class="dropdown-item js-core-api-revoke text-danger" data-id="<?= (int) $credential['id'] ?>" type="button"><i class="fa-solid fa-ban mr-2" aria-hidden="true"></i>Revoke credential</button>
                                            <?php endif; ?>
                                            <button class="dropdown-item js-core-api-copy-key" data-key="<?= esc($credential['key_id']) ?>" type="button"><i class="fa-regular fa-copy mr-2" aria-hidden="true"></i>Copy API key</button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card core-api-panel mb-4">
        <div class="card-header core-api-card-header"><h2 class="h6 mb-0">Available endpoints</h2><p class="small text-muted mb-0 mt-1">Signed request contracts and response examples for approved integrations.</p></div>
        <div class="card-body">
            <p class="small mb-3">Every request uses <code>X-API-Key-Id</code>, <code>X-API-Timestamp</code>, and <code>X-API-Signature</code>. Every POST also requires <code>Idempotency-Key</code>.</p>
            <div class="accordion" id="core-api-endpoints">
                <?php foreach ($endpoints as $index => $endpoint): ?>
                    <div class="card endpoint-card"><div class="card-header p-0" id="heading-<?= esc($endpoint['key']) ?>"><button class="btn btn-link btn-block text-left px-3 py-3 collapsed" type="button" data-toggle="collapse" data-target="#collapse-<?= esc($endpoint['key']) ?>"><span class="badge badge-<?= $endpoint['method'] === 'GET' ? 'info' : 'dark' ?> mr-2"><?= esc($endpoint['method']) ?></span><code><?= esc($endpoint['path']) ?></code></button></div><div id="collapse-<?= esc($endpoint['key']) ?>" class="collapse" data-parent="#core-api-endpoints"><div class="card-body small"><p><?= esc($endpoint['purpose']) ?></p><div><strong>Required headers</strong><br><?= esc($endpoint['headers']) ?></div><div class="mt-2"><strong>Path/query/body parameters</strong><br><?= esc($endpoint['parameters']) ?></div><div class="mt-2"><strong>Response fields</strong><br><?= esc($endpoint['response_fields']) ?></div><?php if (! empty($endpoint['status_values'])): ?><div class="mt-2"><strong>Prescription status values</strong><br><?= esc($endpoint['status_values']) ?></div><?php endif; ?><?php if (! empty($endpoint['temporary_url'])): ?><div class="mt-2"><strong>Temporary download URL</strong><br><?= esc($endpoint['temporary_url']) ?></div><?php endif; ?><?php if (! empty($endpoint['example_request'])): ?><div class="mt-2"><strong>Example request</strong><pre class="bg-light p-2 mb-2"><code><?= esc($endpoint['example_request']) ?></code></pre></div><?php endif; ?><div class="mt-2"><strong>Example response</strong><pre class="bg-light p-2 mb-2"><code><?= esc($endpoint['response_example']) ?></code></pre></div><div><strong>Error codes</strong><br><code><?= esc($endpoint['errors']) ?></code></div></div></div></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="small text-muted">Signature format: <code>timestamp + "\n" + uppercase_method + "\n" + /api/v1/path + "\n" + raw_request_body</code>. The secret is shown only in the one-time handoff dialog after create or rotate.</div>
</main>

<?php if ($canCreate): ?><div class="modal fade" id="core-api-create-modal" tabindex="-1" role="dialog" aria-labelledby="core-api-create-title" aria-hidden="true"><div class="modal-dialog" role="document"><form class="modal-content" id="core-api-create-form"><div class="modal-header"><h2 class="modal-title h5" id="core-api-create-title">Create Core API credential</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><p class="small text-muted">This credential is valid across the ROOTINS application. Patient, doctor, appointment, prescription, and order data remain clinic-authorized.</p><div class="form-group"><label for="core-api-key-id">API key ID <span class="text-muted">(optional)</span></label><input class="form-control" name="key_id" id="core-api-key-id" maxlength="100" placeholder="Generated if blank"></div><div class="form-group"><label for="core-api-rate">Rate limit per minute</label><input class="form-control" type="number" name="rate_limit_per_minute" id="core-api-rate" value="60" min="1" max="10000" required></div><p class="small text-warning mb-0">The secret will appear once after creation. Copy it to the integration owner before closing the dialog.</p></div><div class="modal-footer"><input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>"><button class="btn btn-primary" type="submit">Create credential</button></div></form></div></div><?php endif; ?>
<div class="modal fade" id="core-api-secret-modal" tabindex="-1" role="dialog" aria-labelledby="core-api-secret-title" aria-hidden="true"><div class="modal-dialog" role="document"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5" id="core-api-secret-title">One-time secret handoff</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><div class="alert alert-warning small">This secret will not be shown again. Copy it now and store it in the integration secret manager.</div><div class="input-group"><input class="form-control" id="core-api-secret-value" readonly aria-label="One-time API secret"><div class="input-group-append"><button class="btn btn-outline-secondary" type="button" id="core-api-copy-secret">Copy</button></div></div></div></div></div></div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?><script src="<?= base_url('assets/js/admin/core-api/core-api.js') ?>"></script><?= $this->endSection() ?>
