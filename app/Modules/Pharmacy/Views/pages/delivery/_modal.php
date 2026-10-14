<div class="modal fade" id="pharmacyDeliveryDetailModal" tabindex="-1" role="dialog" aria-labelledby="pharmacyDeliveryDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content pharmacy-delivery-modal">
            <div class="modal-header"><div><h2 class="modal-title h5 mb-1" id="pharmacyDeliveryDetailTitle">Delivery details</h2><div class="small text-muted" id="deliveryDetailSummary">Loading...</div></div><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body" id="deliveryDetailBody"></div>
            <div class="modal-footer"><button class="btn btn-light" type="button" data-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<div class="modal fade pharmacy-delivery-update-modal" id="pharmacyDeliveryUpdateModal" tabindex="-1" role="dialog" aria-labelledby="pharmacyDeliveryUpdateTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <form class="modal-content" id="pharmacyDeliveryUpdateForm">
            <?= csrf_field() ?>
            <div class="modal-header"><div><h2 class="modal-title h5 mb-1" id="pharmacyDeliveryUpdateTitle">Update delivery</h2><div class="small text-muted" id="deliveryOrderLabel"></div></div><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body pharmacy-delivery-update-body">
                <input type="hidden" name="order_id" id="deliveryOrderId">

                <section class="delivery-status-summary-section" aria-labelledby="deliveryStatusSummaryHeading">
                    <h3 class="sr-only" id="deliveryStatusSummaryHeading">Current order statuses</h3>
                    <div class="delivery-status-summary">
                        <div class="delivery-status-summary-card"><span class="delivery-card-label">Order status</span><span id="deliverySummaryOrderStatus" class="badge delivery-status-badge badge-light">Not recorded</span></div>
                        <div class="delivery-status-summary-card"><span class="delivery-card-label">Payment status</span><span id="deliverySummaryPaymentStatus" class="badge delivery-status-badge badge-light">Not recorded</span></div>
                        <div class="delivery-status-summary-card"><span class="delivery-card-label">Delivery status</span><span id="deliverySummaryDeliveryStatus" class="badge delivery-status-badge badge-light">Not recorded</span></div>
                    </div>
                </section>

                <section class="delivery-update-section" aria-labelledby="deliveryOrderSummaryHeading">
                    <h3 class="delivery-update-heading" id="deliveryOrderSummaryHeading">Order Summary</h3>
                    <div class="delivery-update-grid">
                        <div><span class="delivery-card-label">Order number</span><strong id="deliverySummaryOrderNumber">Not recorded</strong></div>
                        <div><span class="delivery-card-label">Patient</span><strong id="deliverySummaryPatient">Not recorded</strong></div>
                        <div><span class="delivery-card-label">Patient number</span><strong id="deliverySummaryPatientNumber">Not recorded</strong></div>
                        <div><span class="delivery-card-label">Prescription</span><strong id="deliverySummaryPrescription">Not recorded</strong></div>
                    </div>
                </section>

                <section class="delivery-update-section" aria-labelledby="deliveryInformationHeading">
                    <h3 class="delivery-update-heading" id="deliveryInformationHeading">Delivery Information</h3>
                    <div class="delivery-update-grid">
                        <div class="delivery-update-field"><label class="delivery-card-label" for="deliveryProviderInput">Delivery provider</label><select class="form-control" name="provider_id" id="deliveryProviderInput" aria-describedby="providerHelp provider_idErr"><option value="" data-code="manual" data-template="" selected>Choose an active provider</option><?php foreach (($deliveryProviders ?? []) as $provider): ?><option value="<?= (int) $provider['id'] ?>" data-code="<?= esc($provider['provider_code'], 'attr') ?>" data-template="<?= esc($provider['tracking_url_template'] ?? '', 'attr') ?>"><?= esc($provider['provider_name']) ?> (<?= esc($provider['provider_code']) ?>)</option><?php endforeach; ?></select><input type="hidden" name="provider" id="deliveryProviderCode" value="manual"><small class="form-text" id="providerHelp">Choose an active provider. The configured tracking URL is used for the preview.</small><div class="validation-error" id="providerErr"></div><div class="validation-error" id="provider_idErr"></div></div>
                        <div><span class="delivery-card-label">Tracking reference</span><input class="form-control form-control-sm" name="tracking_reference" id="trackingReference"><div class="validation-error" id="tracking_referenceErr"></div></div>
                        <div id="deliveryTrackingPreviewWrap" class="delivery-detail-wide d-none"><span class="delivery-card-label">Tracking link preview</span><a id="deliveryTrackingPreview" href="#" target="_blank" rel="noopener"></a></div>
                        <div class="delivery-update-wide"><span class="delivery-card-label">Delivery address snapshot</span><textarea class="form-control form-control-sm" id="deliveryAddress" rows="2" readonly></textarea><small class="form-text text-muted">Historical snapshot; cannot be changed here.</small></div>
                        <div><span class="delivery-card-label">Delivery contact snapshot</span><input class="form-control form-control-sm" id="deliveryContact" readonly><small class="form-text text-muted">Historical snapshot; cannot be changed here.</small></div>
                        <div><span class="delivery-card-label">Dispatched date/time</span><input class="form-control form-control-sm" id="deliveryDispatchedAt" readonly></div>
                        <div><span class="delivery-card-label">Delivered date/time</span><input class="form-control form-control-sm" id="deliveryDeliveredAt" readonly></div>
                    </div>
                </section>

                <section class="delivery-update-section" aria-labelledby="deliveryStatusUpdateHeading">
                    <h3 class="delivery-update-heading" id="deliveryStatusUpdateHeading">Status Update</h3>
                    <div class="delivery-update-grid">
                        <div><label class="delivery-card-label" for="deliveryCurrentStatus">Current delivery status</label><input class="form-control form-control-sm" id="deliveryCurrentStatus" readonly><div class="validation-error" id="delivery_statusErr"></div></div>
                        <?php $canOverrideDeliveryStatus = in_array('delivery.status.override', $permissions ?? (session('permissions') ?? []), true); ?><div><label class="delivery-card-label" for="deliveryTarget">New delivery status</label><select class="form-control form-control-sm" name="delivery_status" id="deliveryTarget" data-can-override="<?= $canOverrideDeliveryStatus ? '1' : '0' ?>"></select><small class="form-text text-muted"><?= $canOverrideDeliveryStatus ? 'All approved delivery statuses are available.' : 'Only the normal delivery workflow statuses are available.' ?> Change reason is optional.</small></div>
                        <div class="delivery-update-wide"><label for="deliveryReason" id="deliveryReasonLabel">Change reason (optional)</label><textarea class="form-control form-control-sm" name="reason" id="deliveryReason" rows="2"></textarea><div class="validation-error" id="reasonErr"></div></div>
                    </div>
                    <div class="delivery-confirmation-panel custom-control custom-checkbox mt-3"><input class="custom-control-input" type="checkbox" id="deliveryConfirmation" required aria-describedby="confirmationErr"><label class="custom-control-label" for="deliveryConfirmation">I confirm this delivery update before saving.</label><input type="hidden" name="confirmation" id="deliveryConfirmationValue"><div class="validation-error" id="confirmationErr" aria-live="polite"></div></div>
                </section>
            </div>
            <div class="modal-footer"><button class="btn btn-light" type="button" data-dismiss="modal">Close</button><button class="btn btn-primary" type="submit">Save delivery status</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="pharmacyDeliveryRelatedModal" tabindex="-1" role="dialog" aria-labelledby="pharmacyDeliveryRelatedTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable" role="document"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5" id="pharmacyDeliveryRelatedTitle">Details</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body" id="pharmacyDeliveryRelatedBody"></div><div class="modal-footer"><button class="btn btn-light" type="button" data-dismiss="modal">Close</button></div></div></div></div>
