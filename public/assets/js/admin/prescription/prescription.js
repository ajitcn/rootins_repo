$(function () {
    const form = '#prescriptionForm';
    const medicineLookupEnabled = $(form).data('medicine-lookup') === 1 || $(form).data('medicine-lookup') === '1';
    let itemIndex = 1;

    function itemRow(index, item = {}) {
        const lookup = medicineLookupEnabled
            ? '<input type="search" class="form-control medicine-search" placeholder="Search medicine master" autocomplete="off"><div class="medicine-suggestions list-group position-absolute w-100 shadow-sm bg-white" style="z-index: 1051;"></div>'
            : '';

        return '<div class="prescription-item-row border rounded p-3 mb-2" data-index="' + index + '">' +
            '<div class="row align-items-end">' +
            '<div class="col-md-3 position-relative"><label>Medicine Name <span class="text-danger">*</span></label>' + lookup + '<input name="items[' + index + '][medicine_name]" class="form-control" maxlength="255" value="' + $('<div>').text(item.medicine_name || '').html() + '"><div class="validation-error item-error-medicine_name"></div></div>' +
            '<div class="col-md-2"><label>Dosage</label><input name="items[' + index + '][dosage]" class="form-control" maxlength="100" value="' + $('<div>').text(item.dosage || '').html() + '"><div class="validation-error item-error-dosage"></div></div>' +
            '<div class="col-md-2"><label>Frequency</label><input name="items[' + index + '][frequency]" class="form-control" maxlength="100" value="' + $('<div>').text(item.frequency || '').html() + '"><div class="validation-error item-error-frequency"></div></div>' +
            '<div class="col-md-1"><label>Duration</label><input name="items[' + index + '][duration]" class="form-control" maxlength="50" value="' + $('<div>').text(item.duration || '').html() + '"><div class="validation-error item-error-duration"></div></div>' +
            '<div class="col-md-2"><label>Duration Unit</label><input name="items[' + index + '][duration_unit]" class="form-control" maxlength="30" value="' + $('<div>').text(item.duration_unit || '').html() + '"><div class="validation-error item-error-duration_unit"></div></div>' +
            '<div class="col-md-1"><label>Quantity</label><input type="number" step="0.01" min="0" name="items[' + index + '][quantity]" class="form-control" value="' + $('<div>').text(item.quantity || '').html() + '"><div class="validation-error item-error-quantity"></div></div>' +
            '<div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger remove-prescription-item"><i class="bi bi-trash"></i></button></div>' +
            '<div class="col-md-6 mt-2"><label>Instructions</label><textarea name="items[' + index + '][instructions]" class="form-control" rows="2">' + $('<div>').text(item.instructions || '').html() + '</textarea></div>' +
            '<div class="col-md-6 mt-2"><label>Notes</label><textarea name="items[' + index + '][notes]" class="form-control" rows="2">' + $('<div>').text(item.notes || '').html() + '</textarea></div>' +
            '</div></div>';
    }

    function resetPrescriptionForm() {
        resetForm(form);
        clearFormErrors();
        $('#prescription_id').val('');
        if (window.setSharedDateValue) {
            setSharedDateValue('#prescription_date', new Date().toISOString().slice(0, 10));
        } else {
            $('#prescription_date').val(new Date().toISOString().slice(0, 10));
        }
        $('#prescription_status').val('draft');
        $('#prescription_notes').val('');
        $('#prescriptionItemRows').html(itemRow(0));
        itemIndex = 1;
        $('#consultationSummary').text('Select a consultation to see its linked patient, doctor, and clinic.');
        $('#prescriptionModal .modal-title').text('Add Prescription');
        $(form + ' .save').text('Save Prescription');
        $(form + ' .btSelect').selectpicker('refresh');
    }

    $('#openPrescriptionModal').on('click', function () {
        resetPrescriptionForm();
        $('#prescriptionModal').modal('show');
    });

    const consultationId = new URLSearchParams(window.location.search).get('consultation_id');

    if (consultationId && $('#prescription_consultation_id option[value="' + consultationId + '"]').length) {
        resetPrescriptionForm();
        $('#prescription_consultation_id').val(consultationId).trigger('change');
        $('#prescriptionModal').modal('show');
    }

    $('#addPrescriptionItem').on('click', function () {
        $('#prescriptionItemRows').append(itemRow(itemIndex++));
    });

    $(document).on('click', '.remove-prescription-item', function () {
        if ($('.prescription-item-row').length === 1) {
            $(this).closest('.prescription-item-row').find('input, textarea').val('');
            return;
        }
        $(this).closest('.prescription-item-row').remove();
    });

    let medicineSearchTimer;
    let medicineSearchRequest = 0;

    function closeMedicineSuggestions(row) {
        row.find('.medicine-suggestions').empty().hide();
        row.removeData('medicine-results medicine-highlight');
    }

    function renderMedicineSuggestions(row, medicines) {
        const suggestions = row.find('.medicine-suggestions').empty().show();

        if (! medicines.length) {
            suggestions.append('<div class="list-group-item small text-muted py-2">No active medicines found.<br><span class="text-dark">Enter the medicine name manually.</span></div>');
            return;
        }

        medicines.forEach(function (medicine, index) {
            const secondary = [medicine.generic_name, medicine.strength, medicine.dosage_form, medicine.medicine_code]
                .filter(Boolean).join(' | ');
            const button = $('<button type="button" class="list-group-item list-group-item-action medicine-suggestion py-2 text-left"></button>')
                .attr('data-suggestion-index', index)
                .attr('data-medicine-name', medicine.medicine_name);
            $('<div class="font-weight-bold text-dark"></div>').text(medicine.medicine_name).appendTo(button);
            $('<small class="d-block text-muted"></small>').text(secondary || 'Medicine master record').appendTo(button);
            button.appendTo(suggestions);
        });
    }

    function highlightMedicineSuggestion(row, index) {
        const buttons = row.find('.medicine-suggestion');
        if (! buttons.length) return;
        index = (index + buttons.length) % buttons.length;
        buttons.removeClass('active').eq(index).addClass('active');
        row.data('medicine-highlight', index);
    }

    $(document).on('input', '.medicine-search', function () {
        const search = $(this);
        const row = search.closest('.prescription-item-row');
        const term = search.val().trim();
        const suggestions = search.siblings('.medicine-suggestions');
        clearTimeout(medicineSearchTimer);
        row.removeData('medicine-results medicine-highlight');
        suggestions.empty().hide();

        if (term.length < 2) return;

        medicineSearchTimer = setTimeout(function () {
            const requestId = ++medicineSearchRequest;
            row.data('medicine-request', requestId);
            suggestions.html('<div class="list-group-item small text-muted py-2"><span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span>Searching medicines...</div>').show();
            ajaxRequest({
                url: ROUTES.medicines.search,
                method: 'GET',
                data: {q: term},
                success: function (response) {
                    if (row.data('medicine-request') !== requestId || ! response.status) return;
                    row.data('medicine-results', response.data || []);
                    row.data('medicine-highlight', -1);
                    renderMedicineSuggestions(row, response.data || []);
                }
            });
        }, 250);
    });

    $(document).on('keydown', '.medicine-search', function (event) {
        const search = $(this);
        const row = search.closest('.prescription-item-row');
        const buttons = row.find('.medicine-suggestion');
        const key = event.key;

        if (key === 'Escape') {
            event.preventDefault();
            closeMedicineSuggestions(row);
            return;
        }

        if (key === 'ArrowDown' || key === 'ArrowUp') {
            event.preventDefault();
            if (! buttons.length) return;
            const current = Number(row.data('medicine-highlight'));
            highlightMedicineSuggestion(row, (Number.isInteger(current) ? current : -1) + (key === 'ArrowDown' ? 1 : -1));
            return;
        }

        if (key === 'Enter') {
            event.preventDefault();
            if (buttons.length) {
                const current = Number(row.data('medicine-highlight'));
                buttons.eq(Number.isInteger(current) && current >= 0 ? current : 0).trigger('click');
            }
        }
    });

    $(document).on('mouseenter', '.medicine-suggestion', function () {
        const suggestion = $(this);
        highlightMedicineSuggestion(suggestion.closest('.prescription-item-row'), Number(suggestion.data('suggestion-index')));
    });

    $(document).on('click', '.medicine-suggestion', function () {
        const suggestion = $(this);
        const row = suggestion.closest('.prescription-item-row');
        row.find('input[name$="[medicine_name]"]').val(suggestion.data('medicine-name'));
        row.find('.medicine-search').val('');
        closeMedicineSuggestions(row);
    });

    $(document).on('click', function (event) {
        if (! $(event.target).closest('.medicine-search, .medicine-suggestions').length) {
            $('.prescription-item-row').each(function () {
                closeMedicineSuggestions($(this));
            });
        }
    });

    $('#prescription_consultation_id').on('change', function () {
        const option = $(this).find('option:selected');
        $('#consultationSummary').text(option.val() ? option.text() : 'Select a consultation to see its linked patient, doctor, and clinic.');
    });

    $(form).on('submit', function (e) {
        e.preventDefault();
        clearFormErrors();
        const id = $('#prescription_id').val();
        ajaxRequest({
            url: id ? ROUTES.prescriptions.update + id : ROUTES.prescriptions.save,
            method: 'POST',
            data: $(this).serialize(),
            success: function (response) {
                if (response.status) {
                    $('#prescriptionModal').modal('hide');
                    showSuccess(response.msg);
                    reload_page(500);
                    return;
                }
                showError(response.msg);
                bindFormError(form, response.errors);
                if (response.errors) {
                    $.each(response.errors, function (key, message) {
                        const match = key.match(/^items\.\d+\.(.+)$/);
                        if (match) {
                            const index = key.split('.')[1];
                            $('.prescription-item-row[data-index="' + index + '"] .item-error-' + match[1]).text(message);
                        }
                    });
                }
            }
        });
    });

    $('.edit-prescription').on('click', function () {
        const id = $(this).data('id');
        ajaxRequest({
            url: ROUTES.prescriptions.edit + id,
            method: 'GET',
            success: function (response) {
                if (! response.status) { showError(response.msg); return; }
                resetPrescriptionForm();
                const prescription = response.data.prescription;
                $('#prescription_id').val(prescription.id);
                $('#prescription_consultation_id').val(prescription.consultation_id);
                if (window.setSharedDateValue) {
                    setSharedDateValue('#prescription_date', prescription.prescription_date);
                } else {
                    $('#prescription_date').val(prescription.prescription_date);
                }
                $('#prescription_status').val(prescription.status);
                $('#prescription_notes').val(prescription.notes || '');
                $('#prescriptionItemRows').empty();
                response.data.items.forEach(function (item, index) { $('#prescriptionItemRows').append(itemRow(index, item)); });
                itemIndex = response.data.items.length;
                $('#consultationSummary').text($('#prescription_consultation_id option:selected').text());
                $(form + ' .btSelect').selectpicker('refresh');
                $('#prescriptionModal .modal-title').text('Edit Prescription');
                $(form + ' .save').text('Update Prescription');
                $('#prescriptionModal').modal('show');
            }
        });
    });

    $('.delete-prescription').on('click', function () {
        const id = $(this).data('id');
        if (! window.confirm('Delete this prescription?')) return;
        ajaxRequest({
            url: ROUTES.prescriptions.delete + id,
            method: 'POST',
            data: {csrf_rtn: $('input[name="csrf_rtn"]').first().val()},
            success: function (response) {
                if (response.status) { showSuccess(response.msg); reload_page(500); return; }
                showError(response.msg);
            }
        });
    });

});
