(function ($) {
    'use strict';

    var definitions = {
        '.patient-prescription-view': { modal: '#patient-prescription-detail-modal', key: 'prescription_number', path: 'prescriptions' },
        '.patient-invoice-view': { modal: '#patient-invoice-detail-modal', key: 'invoice_number', path: 'invoices' },
        '.patient-order-view': { modal: '#patient-order-detail-modal', key: 'order_number', path: 'orders' },
        '.patient-appointment-view': { modal: '#patient-appointment-detail-modal', key: 'appointment_number', path: 'appointments' }
    };

    function addDownloadButton(definition, record) {
        var reference = record && String(record[definition.key] || '').trim();
        var modal = $(definition.modal);
        var footer = modal.find('.modal-footer');
        var button = modal.find('.patient-pdf-download');

        if (! reference || ! footer.length) {
            return;
        }

        if (! button.length) {
            button = $('<a>', { class: 'btn btn-primary patient-pdf-download', target: '_blank', rel: 'noopener noreferrer' }).prependTo(footer);
        }
        button.attr('href', window.rootinsPatientPdfBase + '/' + definition.path + '/' + encodeURIComponent(reference) + '/pdf').html('<i class="bi bi-file-earmark-pdf mr-1" aria-hidden="true"></i>Download PDF');
        if (definition.path === 'invoices' && ['paid', 'refunded'].indexOf(String(record.payment_status || '').toLowerCase()) !== -1) {
            var receipt = modal.find('.patient-receipt-download');
            if (! receipt.length) {
                receipt = $('<a>', { class: 'btn btn-outline-primary patient-receipt-download', target: '_blank', rel: 'noopener noreferrer' }).insertAfter(button);
            }
            receipt.attr('href', window.rootinsPatientPdfBase + '/invoices/' + encodeURIComponent(reference) + '/receipt').html('<i class="bi bi-receipt mr-1" aria-hidden="true"></i>Payment receipt');
        }
    }

    function notifyDownloadError(message) {
        if (typeof window.showError === 'function') {
            window.showError(message);
        } else {
            window.alert(message);
        }
    }

    function downloadPdf(event) {
        var button = $(event.target).closest('.patient-pdf-download, .patient-receipt-download');
        if (! button.length || button.data('pdfLoading')) {
            return;
        }

        event.preventDefault();
        var href = button.attr('href');
        if (! href || ! window.fetch) {
            window.open(href, '_blank', 'noopener');
            return;
        }

        var originalHtml = button.html();
        var controller = window.AbortController ? new AbortController() : null;
        var timeout = window.setTimeout(function () {
            if (controller) {
                controller.abort();
            }
        }, 30000);

        button.data('pdfLoading', true).attr('aria-busy', 'true').addClass('disabled').css('pointer-events', 'none').html('<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span>Preparing PDF…');

        window.fetch(href, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/pdf' },
            signal: controller ? controller.signal : undefined
        }).then(function (response) {
            var contentType = String(response.headers.get('Content-Type') || '').toLowerCase();
            if (! response.ok || contentType.indexOf('application/pdf') === -1) {
                throw new Error('The PDF could not be downloaded.');
            }
            return response.blob();
        }).then(function (blob) {
            var objectUrl = window.URL.createObjectURL(blob);
            var anchor = document.createElement('a');
            anchor.href = objectUrl;
            var pathParts = new URL(href, window.location.origin).pathname.split('/').filter(Boolean);
            var action = pathParts[pathParts.length - 1] || 'pdf';
            var reference = pathParts[pathParts.length - 2] || 'document';
            var collection = pathParts[pathParts.length - 3] || 'document';
            var prefix = action === 'receipt' ? 'receipt' : collection.replace(/s$/, '');
            anchor.download = prefix + '-' + reference + '.pdf';
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
            window.setTimeout(function () { window.URL.revokeObjectURL(objectUrl); }, 1000);
        }).catch(function (error) {
            if (error && error.name === 'AbortError') {
                notifyDownloadError('The PDF download timed out. Please try again.');
            } else {
                notifyDownloadError('The PDF could not be downloaded. Please try again.');
            }
        }).finally(function () {
            window.clearTimeout(timeout);
            button.removeData('pdfLoading').removeAttr('aria-busy').removeClass('disabled').css('pointer-events', '').html(originalHtml);
        });
    }

    $(document).on('click.patientPdfDownloads', function (event) {
        $.each(definitions, function (selector, definition) {
            var trigger = $(event.target).closest(selector);
            if (! trigger.length) {
                return;
            }
            var records = window.patientPdfRecords || [];
            var id = Number(trigger.data('id'));
            var record = records.find(function (item) { return Number(item.id) === id; });
            addDownloadButton(definition, record);
        });
    });

    $(document).on('click.patientPdfDownload', '.patient-pdf-download, .patient-receipt-download', downloadPdf);
}(jQuery));
