<div class="modal fade pharmacy-order-labels-modal" id="pharmacyOrderLabelsModal" tabindex="-1" role="dialog" aria-labelledby="pharmacyOrderLabelsTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <form class="modal-content pharmacy-label-modal" id="pharmacyOrderLabelsForm">
            <?= csrf_field() ?>
            <div class="modal-header pharmacy-label-modal-header">
                <div class="min-width-0">
                    <div class="pharmacy-modal-eyebrow">Order processing</div>
                    <h2 class="modal-title h5 mb-1" id="pharmacyOrderLabelsTitle">Print labels</h2>
                    <div class="small text-light" id="pharmacyOrderLabelsSummary" aria-live="polite">Select an order label to preview.</div>
                </div>
                <button class="close text-white" type="button" data-dismiss="modal" aria-label="Close label printing">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body pharmacy-label-modal-body">
                <input type="hidden" name="order_id" id="orderLabelOrderId">
                <div class="form-row">
                    <div class="form-group col-md-7">
                        <label class="d-block">Label type</label>
                        <div class="pharmacy-label-options" role="radiogroup" aria-label="Label type">
                            <label class="custom-control custom-radio">
                                <input class="custom-control-input js-order-label-type" type="radio" name="label_type" value="order" checked>
                                <span class="custom-control-label">Order/shipping</span>
                            </label>
                            <label class="custom-control custom-radio">
                                <input class="custom-control-input js-order-label-type" type="radio" name="label_type" value="medicine">
                                <span class="custom-control-label">Medicine/package</span>
                            </label>
                            <label class="custom-control custom-radio">
                                <input class="custom-control-input js-order-label-type" type="radio" name="label_type" value="patient">
                                <span class="custom-control-label">Patient/order summary</span>
                            </label>
                            <label class="custom-control custom-radio">
                                <input class="custom-control-input js-order-label-type" type="radio" name="label_type" value="all">
                                <span class="custom-control-label">All labels</span>
                            </label>
                        </div>
                        <div class="validation-error" id="label_typeErr"></div>
                    </div>
                    <div class="form-group col-md-5">
                        <label for="orderLabelSize">Print size</label>
                        <select class="form-control" id="orderLabelSize" name="size">
                            <option value="a4">A4</option>
                            <option value="thermal">Thermal/receipt</option>
                            <option value="small_package">Small package label</option>
                        </select>
                        <div class="validation-error" id="sizeErr"></div>
                    </div>
                </div>
                <div class="pharmacy-label-state" id="orderLabelsState" role="status" aria-live="polite">
                    Select an order from More Actions to load a preview.
                </div>
                <div class="pharmacy-label-preview" id="orderLabelsPreview" hidden></div>
            </div>
            <div class="modal-footer pharmacy-label-modal-footer">
                <button class="btn btn-light" type="button" data-dismiss="modal">Close</button>
                <button class="btn btn-primary" id="printOrderLabels" type="submit" disabled>
                    <i class="bi bi-printer mr-1" aria-hidden="true"></i>Print selected labels
                </button>
            </div>
        </form>
    </div>
</div>
