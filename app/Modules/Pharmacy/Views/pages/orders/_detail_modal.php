<div class="modal fade pharmacy-order-detail-modal" id="pharmacyOrderDetailModal" tabindex="-1" role="dialog" aria-labelledby="pharmacyOrderDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content pharmacy-order-modal">
            <div class="modal-header pharmacy-order-modal-header"><div class="min-width-0"><div class="pharmacy-modal-eyebrow">Medicine Orders</div><h2 class="modal-title h5 mb-1" id="pharmacyOrderDetailTitle">Order details</h2><div class="pharmacy-order-modal-summary" id="orderDetailSummary" aria-live="polite">Loading order details...</div></div><button type="button" class="close" data-dismiss="modal" aria-label="Close order details"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body pharmacy-order-modal-body" id="orderDetailBody"><div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span>Loading order details...</div></div>
            <div class="modal-footer pharmacy-order-modal-footer"><button class="btn btn-light" type="button" data-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>
<div class="modal fade pharmacy-order-cancel-modal" id="pharmacyOrderCancelModal" tabindex="-1" role="dialog" aria-labelledby="pharmacyOrderCancelTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form class="modal-content" id="pharmacyOrderCancelForm">
            <div class="modal-header"><h2 class="modal-title h5" id="pharmacyOrderCancelTitle">Cancel order</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close cancellation form"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body pharmacy-order-form-body"><?= csrf_field() ?><input type="hidden" name="order_id" id="cancelOrderId"><p class="small text-muted" id="cancelOrderLabel"></p><div class="form-group"><label for="cancelReason">Cancellation reason</label><textarea class="form-control" id="cancelReason" name="reason" rows="3" required></textarea><div class="validation-error" id="cancel_reasonErr"></div></div><div class="custom-control custom-checkbox"><input class="custom-control-input" type="checkbox" name="confirmation" value="I confirm this order cancellation" id="cancelConfirmation"><label class="custom-control-label" for="cancelConfirmation">I confirm this order cancellation</label></div><div class="validation-error" id="cancel_confirmationErr"></div></div>
            <div class="modal-footer"><button class="btn btn-light" type="button" data-dismiss="modal">Close</button><button class="btn btn-danger" type="submit">Cancel order</button></div>
        </form>
    </div>
</div>
<div class="modal fade pharmacy-order-related-modal" id="pharmacyOrderRelatedModal" tabindex="-1" role="dialog" aria-labelledby="pharmacyOrderRelatedTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5" id="pharmacyOrderRelatedTitle">Details</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close related details"><span aria-hidden="true">&times;</span></button></div><div class="modal-body" id="pharmacyOrderRelatedBody"></div><div class="modal-footer"><button class="btn btn-light" type="button" data-dismiss="modal">Close</button></div></div></div></div>
