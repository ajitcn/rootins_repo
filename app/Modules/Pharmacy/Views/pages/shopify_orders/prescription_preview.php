<?= $this->extend(PHARMACY_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<?php
$document = is_array($document ?? null) ? $document : [];
$import = is_array($import ?? null) ? $import : [];
$mime = strtolower((string) ($document['mime_type'] ?? $document['file_type'] ?? ''));
$isImage = str_starts_with($mime, 'image/');
$documentName = (string) ($document['original_name'] ?? $document['file_name'] ?? 'Uploaded prescription');
$documentId = (int) ($document['id'] ?? 0);
$importId = (int) ($import['id'] ?? 0);
$uploadStatus = strtolower((string) ($document['upload_status'] ?? 'uploaded'));
$uploadStatusLabel = ucwords(str_replace('_', ' ', $uploadStatus));
$canReviewDocument = in_array($uploadStatus, ['uploaded', 'awaiting_review', 'under_review'], true);
?>
<main class="dashboard-container pharmacy-shopify-orders-page pharmacy-prescription-preview-page" aria-labelledby="uploaded-prescription-title">
    <header class="pharmacy-page-header">
        <div class="pharmacy-page-heading">
            <nav class="pharmacy-breadcrumb" aria-label="Breadcrumb">
                <a href="<?= base_url('pharmacy') ?>">Pharmacy</a><span aria-hidden="true">/</span>
                <a href="<?= base_url('pharmacy/shopify-orders') ?>">Shopify Orders</a><span aria-hidden="true">/</span>
                <a href="<?= base_url('pharmacy/shopify-orders/' . $importId) ?>">Order review</a><span aria-hidden="true">/</span>
                <span>Prescription</span>
            </nav>
            <div class="pharmacy-eyebrow">Uploaded prescription</div>
            <h1 class="pharmacy-page-title" id="uploaded-prescription-title">Prescription document review</h1>
            <p class="pharmacy-page-description">Verify this customer-uploaded document before approving prescription-required medicines.</p>
        </div>
        <div class="pharmacy-page-actions">
            <a class="btn btn-sm btn-light" href="<?= base_url('pharmacy/shopify-orders/' . $importId) ?>"><i class="bi bi-arrow-left mr-1" aria-hidden="true"></i>Back to order</a>
        </div>
    </header>

    <div id="shopifyOrderActionFeedback" class="d-none" role="alert" aria-live="polite"></div>
    <section class="pharmacy-content-card mb-4 shopify-detail-card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
                <div>
                    <span class="pharmacy-eyebrow">Document</span>
                    <h2 class="h5 mb-1"><?= esc($documentName) ?></h2>
                    <p class="small text-muted mb-0">Shopify order #<?= esc((string) ($import['shopify_order_number'] ?? $import['shopify_order_id'] ?? $importId)) ?> · Upload #<?= $documentId ?></p>
                </div>
                <span class="badge <?= $uploadStatus === 'accepted' ? 'badge-success' : ($uploadStatus === 'rejected' ? 'badge-danger' : 'badge-warning text-dark') ?>"><?= esc($uploadStatusLabel) ?></span>
            </div>
            <div class="shopify-prescription-preview-frame">
                <?php if ($isImage): ?>
                    <img src="<?= esc($temporaryUrl, 'attr') ?>" alt="Uploaded prescription" class="shopify-prescription-preview-image">
                <?php else: ?>
                    <iframe src="<?= esc($temporaryUrl, 'attr') ?>" title="Uploaded prescription document" class="shopify-prescription-preview-embed"></iframe>
                <?php endif; ?>
            </div>
            <p class="small text-muted mt-3 mb-0">This private preview link expires automatically. Do not copy or share the URL.</p>
        </div>
    </section>

    <section class="pharmacy-content-card mb-4 shopify-detail-card" aria-label="Prescription document review actions">
        <div class="card-body">
            <span class="pharmacy-eyebrow">Pharmacist action</span>
            <h2 class="h5 mb-1">Review uploaded prescription</h2>
            <p class="small text-muted mb-3">Accept only when the document is readable and valid for the medicines in this order.</p>
            <?php if (! $canReviewDocument): ?>
                <div class="alert <?= $uploadStatus === 'accepted' ? 'alert-success' : 'alert-danger' ?> mb-0">This upload is <strong><?= esc($uploadStatusLabel) ?></strong> and cannot be reviewed again.</div>
            <?php elseif (! $canManage): ?>
                <div class="alert alert-info mb-0">You have read-only access. A pharmacist with prescription-review permission must accept, request clarification, or reject this upload.</div>
            <?php else: ?>
                <form class="shopify-upload-review-form" data-no-global-loader method="post" action="<?= base_url('pharmacy/shopify-orders/prescriptions/review') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="upload_id" value="<?= $documentId ?>">
                    <div class="form-row align-items-end">
                        <div class="form-group col-lg-4"><label for="preview-review-decision">Decision</label><select class="form-control" id="preview-review-decision" name="decision"><option value="approve">Accept upload</option><option value="clarify">Request clarification</option><option value="reject">Reject upload</option></select></div>
                        <div class="form-group col-lg-5"><label for="preview-review-reason">Reason / note</label><input class="form-control" id="preview-review-reason" name="reason" maxlength="1000" placeholder="Required for clarification or rejection"></div>
                        <div class="form-group col-lg-3"><div class="custom-control custom-checkbox mb-2"><input class="custom-control-input" type="checkbox" id="preview-review-confirmation" name="confirmation" value="1" required><label class="custom-control-label small" for="preview-review-confirmation">Confirm decision <span class="text-danger">*</span></label></div><button type="submit" class="btn btn-primary btn-block">Save review</button></div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </section>
</main>
<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/pharmacy/shopify_orders.js?v=20261112-upload-preview3') ?>"></script>
<?= $this->endSection() ?>
<?= $this->endSection() ?>
