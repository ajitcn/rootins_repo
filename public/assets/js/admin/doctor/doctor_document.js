/**
 * -----------------------------------------------------------
 * Open Add Doctor Document Modal
 * -----------------------------------------------------------
 */
$(document).on('click', '.open_doctor_document_modal', function () {

    $('#doctorDocumentModal .modal-title').text('Add Document');
    $('#doctorDocumentModal .save').text('Save Document');

    // Clear ID
    $('[name="id"]').val('');

    // Reset form
    resetForm('#doctorDocumentForm');
    clearFormErrors();

    // Make sure ID is empty after reset
    $('[name="id"]').val('');

    // Clear file input
    $('#doctorDocumentForm [name="document"]').val('');

    $('#doctorDocumentModal').modal('show');
});


/**
 * -----------------------------------------------------------
 * Save / Update Doctor Document
 * -----------------------------------------------------------
 */
$('#doctorDocumentForm').on('submit', function (e) {

    e.preventDefault();

    clearFormErrors();

    const form = this;

    const id = $('[name="id"]').val();

    const url = id
        ? ROUTES.doctorDocument.update
        : ROUTES.doctorDocument.create;

    /*
     * IMPORTANT:
     * We cannot use $(form).serialize()
     * because the form contains a file.
     */
    const formData = new FormData(form);

    ajaxRequest({

        url: url,

        method: 'POST',

        data: formData,

        /*
         * Required for file upload
         */
        processData: false,
        contentType: false,

        success: function (response) {

            hide_loader();

            if (response.status) {

                showSuccess(response.msg);

                $('#doctorDocumentModal').modal('hide');

                reload_page(500);

            } else {

                showError(response.msg);

                if (response.errors) {
                    bindFormError(
                        '#doctorDocumentForm',
                        response.errors
                    );
                }
            }
        }

    });

});


/**
 * -----------------------------------------------------------
 * Open Edit Doctor Document Modal
 * -----------------------------------------------------------
 */
function editDoctorDocumentForm(documentId)
{
    clearFormErrors();

    ajaxRequest({

        url: ROUTES.doctorDocument.edit + documentId,

        method: 'GET',

        success: function (response) {

            if (!response.status) {

                showError(response.msg);

                return;
            }

            const document = response.data;

            /*
             * Reset form first
             */
            resetForm('#doctorDocumentForm');
            clearFormErrors();


            /*
             * Set hidden ID
             *
             * Controller expects:
             * $this->request->getPost('id')
             */
            $('[name="id"]').val(document.id);


            /*
             * Doctor
             */
            $('#doctor_id')
                .val(document.doctor_id)
                .selectpicker('refresh');


            /*
             * Document Type
             */
            $('#document_type')
                .val(document.document_type)
                .selectpicker('refresh');


            /*
             * Document Name
             */
            $('#document_name')
                .val(document.document_name);


            /*
             * Remarks
             */
            $('#remarks')
                .val(document.remarks);


            /*
             * Status
             */
            $('#status')
                .val(document.status)
                .selectpicker('refresh');


            /*
             * File input
             *
             * Browser does not allow us to populate
             * an existing file into <input type="file">.
             *
             * Therefore it remains empty during edit.
             */
            $('#doctorDocumentForm [name="document"]').val('');


            /*
             * Modal text
             */
            $('#doctorDocumentModal .modal-title')
                .text('Edit Document');

            $('#doctorDocumentForm .save')
                .text('Update Document');


            /*
             * Show modal
             */
            $('#doctorDocumentModal').modal('show');

        }

    });
}