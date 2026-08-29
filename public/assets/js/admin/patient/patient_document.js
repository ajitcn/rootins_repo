$(function () {
    const patientId = window.patientDocumentId;
    const form = '#patientDocumentForm';

    function resetDocumentForm() {
        resetForm(form);
        clearFormErrors();
        $('#patient_document_id').val('');
        $('#patient_document_file').val('');
        $('#patientDocumentRequired').show();
        $('#patientCurrentDocument').hide();
        $('#patientDocumentModal .modal-title').text('Upload Patient Document');
        $(form + ' .save').text('Upload Document');
    }

    $('#openPatientDocumentModal').on('click', function () {
        resetDocumentForm();
        $('#patientDocumentModal').modal('show');
    });

    $(form).on('submit', function (e) {
        e.preventDefault();
        clearFormErrors();

        const documentId = $('#patient_document_id').val();
        const url = documentId
            ? ROUTES.patients.documents.update + patientId + '/documents/update/' + documentId
            : ROUTES.patients.documents.save + patientId + '/documents/save';

        ajaxRequest({
            url: url,
            method: 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.status) {
                    $('#patientDocumentModal').modal('hide');
                    showSuccess(response.msg);
                    reload_page(500);
                    return;
                }

                showError(response.msg);
                bindFormError(form, response.errors);
            }
        });
    });

    $('.edit-patient-document').on('click', function () {
        const documentId = $(this).data('id');

        clearFormErrors();

        ajaxRequest({
            url: ROUTES.patients.documents.edit + patientId + '/documents/edit/' + documentId,
            method: 'GET',
            success: function (response) {
                if (! response.status) {
                    showError(response.msg);
                    return;
                }

                resetDocumentForm();
                $('#patient_document_id').val(response.data.id);
                $('#patient_document_type').val(response.data.document_type);
                $('#patient_document_title').val(response.data.title);
                $('#patientDocumentRequired').hide();
                $('#patientCurrentDocumentName').text(response.data.file_name || 'Available');
                $('#patientCurrentDocument').show();
                $('#patientDocumentModal .modal-title').text('Edit Patient Document');
                $(form + ' .save').text('Update Document');
                $('#patientDocumentModal').modal('show');
            }
        });
    });

    $('.delete-patient-document').on('click', function () {
        const documentId = $(this).data('id');

        if (! window.confirm('Delete this patient document?')) {
            return;
        }

        ajaxRequest({
            url: ROUTES.patients.documents.delete + patientId + '/documents/delete/' + documentId,
            method: 'POST',
            data: {},
            success: function (response) {
                if (response.status) {
                    showSuccess(response.msg);
                    reload_page(500);
                    return;
                }

                showError(response.msg);
            }
        });
    });
});
