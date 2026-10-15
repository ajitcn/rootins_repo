(function ($) {
    'use strict';

    $(document).on('click.patientOrderTracking', '.patient-order-view', function () {
        $('#patient-order-detail-modal').data('patient-tracking-url', $(this).attr('data-tracking-url') || '');
    });

    $(document).on('shown.bs.modal.patientOrderTracking', '#patient-order-detail-modal', function () {
        var modal = $(this);
        var url = modal.data('patient-tracking-url');
        var body = modal.find('#patient-order-detail-body');

        if (! url || ! /^https?:\/\//i.test(url) || body.find('.patient-track-order-link').length) {
            return;
        }

        body.append('<div class="patient-modal-actions"><a class="btn btn-primary patient-track-order-link" href="' + $('<div>').text(url).html() + '" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right mr-1" aria-hidden="true"></i>Track Order</a></div>');
    });

    $(document).on('hidden.bs.modal.patientOrderTracking', '#patient-order-detail-modal', function () {
        $(this).removeData('patient-tracking-url');
    });
}(jQuery));
