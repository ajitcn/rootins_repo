(function ($) {
    'use strict';

    function refreshSelects() {
        $('#doctorDocumentForm .btSelect').each(function () {
            if ($(this).data('selectpicker')) $(this).selectpicker('refresh');
        });
    }

    function openDocumentModal(replaceFile) {
        $('#doctorDocumentModalLabel').text('Upload document');
        $('#doctorDocumentForm .save').html('<i class="bi bi-check2 mr-1" aria-hidden="true"></i>Save document');
        $('#doctorDocumentForm')[0].reset();
        $('#doctor_document_id').val('');
        $('#currentDocument').prop('hidden', true);
        $('#documentRequired').show();
        clearFormErrors('#doctorDocumentForm');
        refreshSelects();
        $('#doctorDocumentModal').modal('show');
        if (replaceFile) $('#document').trigger('focus');
    }

    $(document).on('click', '.open_doctor_document_modal', function () { openDocumentModal(false); });

    $('#doctorDocumentForm').on('submit', function (event) {
        event.preventDefault();
        clearFormErrors('#doctorDocumentForm');
        const id = $('#doctor_document_id').val();
        ajaxRequest({ url: id ? ROUTES.doctorDocument.update : ROUTES.doctorDocument.create, method: 'POST', data: new FormData(this), processData: false, contentType: false, success: function (response) {
            if (response.status) { showSuccess(response.msg); $('#doctorDocumentModal').modal('hide'); reload_page(500); return; }
            showError(response.msg || 'The document could not be saved.');
            if (response.errors) bindFormError('#doctorDocumentForm', response.errors);
        } });
    });

    function editDoctorDocumentForm(documentId, replaceFile) {
        clearFormErrors('#doctorDocumentForm');
        ajaxRequest({ url: ROUTES.doctorDocument.edit + documentId, method: 'GET', success: function (response) {
            if (!response.status || !response.data) { showError(response.msg || 'Document details could not be loaded.'); return; }
            const document = response.data;
            $('#doctorDocumentForm')[0].reset();
            $('#doctor_document_id').val(document.id);
            $('#doctor_id').val(document.doctor_id);
            $('#document_type').val(document.document_type);
            $('#document_name').val(document.document_name);
            $('#remarks').val(document.remarks || '');
            $('#doctor_document_status').val(String(document.status));
            $('#document').val('');
            $('#currentDocumentName').text(document.file_name || 'Current file available');
            $('#currentDocument').prop('hidden', false);
            $('#documentRequired').hide();
            $('#doctorDocumentModalLabel').text(replaceFile ? 'Replace document file' : 'Edit document details');
            $('#doctorDocumentForm .save').html(replaceFile ? '<i class="bi bi-arrow-repeat mr-1" aria-hidden="true"></i>Replace file' : '<i class="bi bi-check2 mr-1" aria-hidden="true"></i>Update document');
            refreshSelects();
            $('#doctorDocumentModal').modal('show');
            if (replaceFile) $('#document').trigger('focus');
        } });
    };

    $(document).on('click', '.js-edit-doctor-document', function () {
        editDoctorDocumentForm($(this).data('document-id'), $(this).data('replace-file') === true || $(this).data('replace-file') === 'true');
    });

    $(document).on('click', '.doctor-document-actions .dropdown-item[onclick*="editDoctorDocumentForm"]', function (event) {
        const source = String($(this).attr('onclick') || '');
        const match = source.match(/editDoctorDocumentForm\((\d+)(?:,\s*true)?/);
        if (match) {
            event.preventDefault();
            editDoctorDocumentForm(parseInt(match[1], 10), /,\s*true/.test(source));
        }
    });

    $(document).on('submit', '.doctor-document-delete-form', function (event) {
        if (!window.confirm('Delete this document? This action cannot be undone.')) event.preventDefault();
    });

    $('#doctorDocumentModal').on('hidden.bs.modal', function () { clearFormErrors('#doctorDocumentForm'); });
}(jQuery));

document.addEventListener('click', function (event) {
    const toggle = event.target.closest('.doctor-document-actions .dropdown-toggle');
    if (toggle) {
        event.preventDefault();
        event.stopPropagation();
        const menu = toggle.parentElement.querySelector('.dropdown-menu');
        document.querySelectorAll('.doctor-document-actions .dropdown-menu.show').forEach(function (openMenu) {
            if (openMenu !== menu) openMenu.classList.remove('show');
        });
        menu.classList.toggle('show');
        toggle.setAttribute('aria-expanded', menu.classList.contains('show') ? 'true' : 'false');
        return;
    }
    if (!event.target.closest('.doctor-document-actions')) {
        document.querySelectorAll('.doctor-document-actions .dropdown-menu.show').forEach(function (menu) { menu.classList.remove('show'); });
    }
});
