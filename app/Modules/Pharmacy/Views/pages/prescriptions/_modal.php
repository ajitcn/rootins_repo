<div class="modal fade pharmacy-prescription-modal" id="pharmacy-prescription-modal" tabindex="-1" role="dialog" aria-labelledby="pharmacy-prescription-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content pharmacy-prescription-modal-content">
            <div class="modal-header pharmacy-prescription-modal-header">
                <div class="min-width-0">
                    <div class="pharmacy-modal-eyebrow">Prescription Review</div>
                    <h2 class="modal-title h5 mb-1" id="pharmacy-prescription-modal-title">Prescription Review</h2>
                    <div class="pharmacy-prescription-modal-summary" aria-live="polite">
                        <span id="review-header-number">Prescription not selected</span>
                        <span aria-hidden="true">&middot;</span>
                        <span id="review-header-date">Date not recorded</span>
                        <span class="badge badge-light" id="review-header-status">Status unavailable</span>
                        <span class="badge badge-light" id="review-header-state">Review state unavailable</span>
                    </div>
                    <span class="sr-only" id="pharmacy-prescription-modal-subtitle"></span>
                </div>
                <button type="button" class="close pharmacy-prescription-modal-close" data-dismiss="modal" aria-label="Close prescription review"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body pharmacy-prescription-modal-body">
                <section class="review-modal-section" aria-labelledby="review-patient-heading">
                    <div class="review-modal-section-heading"><h3 class="review-modal-section-title" id="review-patient-heading">Patient information</h3></div>
                    <div class="review-modal-info-grid">
                        <div class="review-modal-info-item"><span class="review-summary-label">Name</span><strong id="review-patient-name">Not recorded</strong></div>
                        <div class="review-modal-info-item"><span class="review-summary-label">Patient number</span><span id="review-patient-number">Not recorded</span></div>
                        <div class="review-modal-info-item"><span class="review-summary-label">Mobile</span><span id="review-patient-mobile">Not recorded</span></div>
                    </div>
                </section>
                <section class="review-modal-section" aria-labelledby="review-doctor-heading">
                    <div class="review-modal-section-heading"><h3 class="review-modal-section-title" id="review-doctor-heading">Doctor information</h3></div>
                    <div class="review-modal-info-grid review-modal-info-grid-two">
                        <div class="review-modal-info-item"><span class="review-summary-label">Doctor</span><strong id="review-doctor-name">Not recorded</strong></div>
                        <div class="review-modal-info-item"><span class="review-summary-label">Doctor code</span><span id="review-doctor-code">Not recorded</span></div>
                    </div>
                </section>
                <section class="review-modal-section" aria-labelledby="review-summary-heading">
                    <div class="review-modal-section-heading"><h3 class="review-modal-section-title" id="review-summary-heading">Prescription summary</h3></div>
                    <div class="review-prescription-meta review-modal-summary-grid">
                        <span><small>Prescription number</small><strong id="review-prescription-number">Not recorded</strong></span>
                        <span><small>Prescription date</small><strong id="review-prescription-date">Not recorded</strong></span>
                        <span><small>Prescription status</small><span class="badge badge-light" id="review-current-status">Not recorded</span></span>
                        <span><small id="review-date-label">Last updated</small><strong id="review-date">Not recorded</strong></span>
                        <span><small>Review status</small><span class="badge badge-light" id="review-state">Not recorded</span></span>
                    </div>
                </section>
                <section class="review-modal-section" aria-labelledby="review-medicines-heading">
                    <div class="review-modal-section-heading"><h3 class="review-modal-section-title" id="review-medicines-heading">Medicine details</h3><span class="text-muted small">Review the prescribed medicines before deciding.</span></div>
                    <div class="table-responsive review-modal-table-wrap">
                        <table class="table table-sm review-medicine-table mb-0">
                            <caption class="sr-only">Prescription medicines</caption>
                            <thead><tr><th scope="col">Medicine</th><th scope="col">Duration</th><th scope="col">Frequency</th><th scope="col">Dose</th><th scope="col">Intake</th><th scope="col">Note</th></tr></thead>
                            <tbody id="review-medicine-rows"></tbody>
                        </table>
                    </div>
                </section>
                <section class="review-modal-section review-modal-action-section" aria-labelledby="review-action-heading">
                    <div class="review-modal-section-heading"><h3 class="review-modal-section-title" id="review-action-heading">Review action</h3></div>
                    <form id="pharmacy-review-form" class="pharmacy-review-form" data-review-url="<?= base_url('pharmacy/prescriptions/review') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="prescription_id" id="review-prescription-id">
                        <input type="hidden" name="decision" id="review-decision">
                        <div class="form-group review-reason-group mb-0"><label for="review-reason">Reason <span class="text-danger review-reason-required">*</span></label><textarea class="form-control" name="reason" id="review-reason" rows="3" maxlength="1000" placeholder="Explain this review decision"></textarea><div class="validation-error" id="reasonErr"></div></div>
                        <div class="form-group review-confirmation-group mb-0"><div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" value="1" name="approve_confirmation" id="review-approve-confirmation"><label class="custom-control-label" for="review-approve-confirmation">I confirm this prescription is ready for fulfilment</label></div><input type="hidden" name="approve_confirmation_text" id="review-approve-confirmation-text" value="I confirm this prescription is ready for fulfilment"><div class="validation-error" id="approve_confirmationErr"></div></div>
                        <div class="validation-error" id="decisionErr"></div>
                    </form>
                </section>
            </div>
            <div class="modal-footer pharmacy-prescription-modal-footer"><span class="text-muted small mr-auto" id="review-modal-help">Select a review action to continue.</span><button class="btn btn-light" type="button" data-dismiss="modal">Close</button><button class="btn btn-warning d-none review-submit-action" type="button" data-decision="clarification">Request clarification</button><button class="btn btn-danger d-none review-submit-action" type="button" data-decision="reject">Reject</button><button class="btn btn-outline-danger d-none review-submit-action" type="button" data-decision="cancel">Cancel</button><button class="btn btn-success d-none review-submit-action" type="button" data-decision="approve">Approve</button></div>
        </div>
    </div>
</div>
