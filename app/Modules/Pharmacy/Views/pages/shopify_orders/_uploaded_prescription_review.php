<?php if ($canManage && (int) ($documentId ?? 0) > 0 && in_array((string) ($prescriptionDisplayStatus ?? ''), ['uploaded', 'awaiting_review', 'under_review', 'clarification_required', 'rejected'], true)): ?>
<section class="pharmacy-content-card mb-4 shopify-detail-card" aria-label="Uploaded prescription review">
    <div class="card-body">
        <span class="pharmacy-eyebrow">Uploaded prescription</span>
        <h2 class="h6 mb-1">Prescription document review</h2>
        <p class="small text-muted mb-3">This is a Shopify-uploaded document, not a ROOTINS clinical prescription. Accept it after verifying the file before approving medicine lines.</p>
        <form class="shopify-upload-review-form" method="post" action="<?= base_url('pharmacy/shopify-orders/prescriptions/review') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="upload_id" value="<?= (int) $documentId ?>">
            <div class="form-row align-items-end">
                <div class="form-group col-md-4 mb-md-0"><label for="shopify-upload-review-decision">Decision</label><select class="form-control" id="shopify-upload-review-decision" name="decision"><option value="approve">Accept upload</option><option value="clarification">Request clarification</option><option value="reject">Reject upload</option></select></div>
                <div class="form-group col-md-6 mb-md-0"><label for="shopify-upload-review-reason">Reason / note</label><input class="form-control" id="shopify-upload-review-reason" name="reason" maxlength="1000" placeholder="Required for rejection or clarification"></div>
                <div class="form-group col-md-2 mb-md-0"><button class="btn btn-primary btn-block" type="submit">Save review</button></div>
            </div>
        </form>
    </div>
</section>
<?php endif; ?>
