(function ($) {
    'use strict';

    const appBase = (typeof BASE_URL !== 'undefined' ? BASE_URL : (window.BASE_URL || '/')).replace(/\/+$/, '');
    const base = appBase + '/pharmacy/orders/';
    let labelData = null;

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : value).html();
    }

    function text(value, fallback) {
        const result = String(value == null ? '' : value).trim();

        return escapeHtml(result || fallback || 'Not recorded');
    }

    function field(label, value) {
        return '<div class="pharmacy-print-label-field"><span>' + escapeHtml(label) + '</span><strong>' + value + '</strong></div>';
    }

    function barcode(value) {
        const code = String(value || 'Not recorded');
        let x = 0;
        let bars = '';

        Array.from(code).forEach(function (character) {
            const width = 2 + (character.charCodeAt(0) % 3);
            const gap = 1 + (character.charCodeAt(0) % 2);

            bars += '<rect x="' + x + '" y="0" width="' + width + '" height="34"></rect>';
            x += width + gap;
        });

        return '<div class="pharmacy-print-label-code" role="img" aria-label="Barcode for ' + escapeHtml(code) + '">'
            + '<svg viewBox="0 0 ' + Math.max(x, 80) + ' 34" preserveAspectRatio="xMidYMid meet" aria-hidden="true">'
            + bars
            + '</svg><span>' + escapeHtml(code) + '</span></div>';
    }

    function labelBox(title, data, fields, codeValue) {
        return '<article class="pharmacy-print-label">'
            + '<div class="pharmacy-print-label-brand">' + text(data.clinic_name, 'Clinic') + '</div>'
            + '<h3>' + escapeHtml(title) + '</h3>'
            + fields
            + (codeValue ? barcode(codeValue) : '')
            + '</article>';
    }

    function renderLabels(type) {
        if (!labelData) {
            return;
        }

        const labels = labelData.labels || {};
        const order = labels.order || {};
        const patient = labels.patient || {};
        const medicines = labels.medicine || [];
        let html = '';

        if (type === 'order' || type === 'all') {
            html += labelBox('Order / shipping label', order,
                field('Order', text(order.order_number))
                + field('Clinic contact', text(order.clinic_contact))
                + field('Patient', text(order.patient_name))
                + field('Mobile', text(order.patient_mobile))
                + field('Order date', text(order.order_date))
                + field('Prescription', text(order.prescription_number))
                + field('Label', text(order.sequence))
                + field('Address', text(order.delivery_address))
                + field('Contact', text(order.delivery_contact))
                + field('Provider', text(order.provider))
                + field('Tracking', text(order.tracking_reference)), order.barcode_value || order.order_number);
        }

        if (type === 'patient' || type === 'all') {
            html += labelBox('Patient / order summary', patient,
                field('Order', text(patient.order_number))
                + field('Patient', text(patient.patient_name))
                + field('Mobile', text(patient.patient_mobile))
                + field('Order date', text(patient.order_date))
                + field('Prescription', text(patient.prescription_number))
                + field('Label', text(patient.sequence))
                + field('Packages', text(patient.item_count, '0')), patient.order_number);
        }

        if (type === 'medicine' || type === 'all') {
            medicines.forEach(function (medicine) {
                html += labelBox('Medicine package ' + text(medicine.sequence, ''), medicine,
                    field('Medicine', text(medicine.medicine_name))
                    + field('Strength', text(medicine.strength))
                    + field('Form', text(medicine.form))
                    + field('Unit', text(medicine.unit))
                    + field('Quantity', text(medicine.quantity, '0'))
                    + field('Dosage', text(medicine.dosage))
                    + field('Frequency', text(medicine.frequency))
                    + field('Instructions', text(medicine.instructions))
                    + field('Batch', text(medicine.batch_number))
                    + field('Expiry', text(medicine.expiry_date))
                    + field('Order', text(medicine.order_number)));
            });
        }

        const preview = $('#orderLabelsPreview');
        const size = $('#orderLabelSize').val() || 'a4';

        preview
            .removeClass('label-size-a4 label-size-thermal label-size-small_package')
            .addClass('label-size-' + size)
            .html(html || '<div class="alert alert-info">No labels are available for this order.</div>')
            .prop('hidden', false);
        $('#orderLabelsState').prop('hidden', true);
    }

    function loadLabels(orderId, orderNumber) {
        labelData = null;
        $('#orderLabelOrderId').val(orderId);
        $('#pharmacyOrderLabelsSummary').text('Loading labels for ' + orderNumber + '...');
        $('#orderLabelsPreview').prop('hidden', true).empty();
        $('#orderLabelsState').prop('hidden', false).html('<span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span>Loading label preview...');
        $('#printOrderLabels').prop('disabled', true);

        ajaxRequest({
            url: base + 'labels/' + encodeURIComponent(orderId),
            method: 'GET',
            dataType: 'json'
        }).done(function (response) {
            if (!response.status || !response.data) {
                $('#orderLabelsState').text(response.msg || 'Labels are unavailable.');
                return;
            }

            labelData = response.data;
            $('#pharmacyOrderLabelsSummary').text((response.data.order.order_number || orderNumber) + ' · ' + (response.data.order.order_date || 'Date unavailable'));
            $('#printOrderLabels').prop('disabled', !response.data.can_print);
            renderLabels($('input[name="label_type"]:checked').val() || 'order');
        }).fail(function (xhr) {
            $('#orderLabelsState').text((xhr.responseJSON && xhr.responseJSON.msg) || 'Labels could not be loaded safely.');
        });
    }

    function printMarkup() {
        const preview = $('#orderLabelsPreview').clone().removeAttr('id').removeAttr('hidden').prop('outerHTML');
        const size = $('#orderLabelSize').val();
        const pageSize = size === 'a4' ? 'A4' : (size === 'small_package' ? '58mm auto' : '80mm auto');

        return '<!doctype html><html><head><meta charset="utf-8"><title>Order labels</title><link rel="stylesheet" href="' + appBase + '/assets/css/print.css?v=20261016-rootins-print1"><style>'
            + '@page{size:' + pageSize + ';margin:8mm}'
            + 'html,body{margin:0;padding:0;background:#fff;color:#1f2940;font:12px Arial,sans-serif}'
            + '.pharmacy-label-print-sheet{display:grid;gap:8mm}.pharmacy-print-label{box-sizing:border-box;border:1px solid #1f2940;padding:12px;break-inside:avoid;page-break-inside:avoid}.pharmacy-print-label-brand{font-weight:700;font-size:14px}.pharmacy-print-label h3{font-size:13px;margin:8px 0;border-bottom:1px solid #ccd3df;padding-bottom:5px}.pharmacy-print-label-field{display:grid;grid-template-columns:34% 66%;gap:8px;border-bottom:1px solid #edf0f4;padding:4px 0;overflow-wrap:anywhere}.pharmacy-print-label-field span{color:#5e6b82}.pharmacy-print-label-field strong{font-weight:600;overflow-wrap:anywhere}.pharmacy-print-label-code{margin-top:12px;padding-top:10px;border-top:1px solid #edf0f4;text-align:center;break-inside:avoid;page-break-inside:avoid}.pharmacy-print-label-code svg{display:block;width:auto;height:auto;margin:0 auto;fill:#1f2940}.pharmacy-print-label-code span{display:block;margin-top:4px;color:#5e6b82;font-size:10px;letter-spacing:.08em;overflow-wrap:anywhere}.label-size-a4 .pharmacy-print-label-code svg{width:44mm;height:12mm;max-width:44mm;max-height:12mm}.label-size-thermal .pharmacy-print-label-code svg{width:48mm;height:12mm;max-width:48mm;max-height:12mm}.label-size-small_package .pharmacy-print-label-code svg{width:32mm;height:10mm;max-width:32mm;max-height:10mm}'
            + '@media print{.pharmacy-print-label{break-inside:avoid;page-break-inside:avoid}}'
            + '</style></head><body><main class="pharmacy-label-print-sheet">' + preview + '</main></body></html>';
    }

    function printInFrame() {
        const frame = document.createElement('iframe');
        frame.setAttribute('aria-hidden', 'true');
        frame.style.position = 'fixed';
        frame.style.width = '1px';
        frame.style.height = '1px';
        frame.style.right = '0';
        frame.style.bottom = '0';
        frame.style.border = '0';
        document.body.appendChild(frame);
        frame.contentWindow.document.open();
        frame.contentWindow.document.write(printMarkup());
        frame.contentWindow.document.close();
        frame.onload = function () {
            window.setTimeout(function () {
                frame.contentWindow.focus();
                frame.contentWindow.print();
                window.setTimeout(function () { frame.remove(); }, 500);
            }, 150);
        };
    }

    $(document).on('click', '.js-order-labels', function () {
        const button = $(this);
        $('#pharmacyOrderLabelsForm')[0].reset();
        $('#orderLabelOrderId').val(button.data('order-id'));
        $('#pharmacyOrderLabelsModal').modal('show');
        loadLabels(button.data('order-id'), button.data('order-number') || 'order');
    });

    $(document).on('change', '.js-order-label-type', function () {
        renderLabels($(this).val());
    });

    $(document).on('change', '#orderLabelSize', function () {
        renderLabels($('input[name="label_type"]:checked').val() || 'order');
    });

    $('#pharmacyOrderLabelsForm').on('submit', function (event) {
        event.preventDefault();
        const form = $(this);
        const submit = $('#printOrderLabels');

        if (submit.prop('disabled') || !labelData) {
            return;
        }

        clearFormErrors(form);
        submit.prop('disabled', true).attr('aria-busy', 'true');

        ajaxRequest({
            url: base + 'labels/print',
            method: 'POST',
            data: form.serialize(),
            dataType: 'json'
        }).done(function (response) {
            if (!response.status) {
                bindFormError(form, response.errors || {});
                showError(response.msg || 'Labels could not be printed.');
                return;
            }

            printInFrame();
        }).fail(function (xhr) {
            handleFormAjaxError(form, xhr, 'Labels could not be printed safely.');
        }).always(function () {
            submit.prop('disabled', false).removeAttr('aria-busy');
        });
    });
})(jQuery);
