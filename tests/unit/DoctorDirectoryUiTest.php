<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class DoctorDirectoryUiTest extends CIUnitTestCase
{
    public function testDoctorsTableUsesGroupedResponsiveColumnsAndGlobalStatuses(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/doctor/index.php');
        $model = file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/Doctor/DoctorModel.php');

        foreach (['Doctor', 'Contact', 'Professional Details', 'Availability', 'Status', 'Actions'] as $heading) {
            self::assertStringContainsString('>' . $heading . '<', $view);
        }
        self::assertStringContainsString('More Actions', $view);
        self::assertStringContainsString("\$can('doctor_fee.view')", $view);
        self::assertStringContainsString("\$can('doctor_commission.view')", $view);
        self::assertStringContainsString("\$can('doctor_document.view')", $view);
        self::assertStringContainsString("\$can('doctor_schedule.view')", $view);
        self::assertStringContainsString("\$can('doctor_leave.view')", $view);
        self::assertStringContainsString("\$can('doctor.delete')", $view);
        self::assertStringNotContainsString('aria-label="View ', $view);
        self::assertStringContainsString('ri-status--<?=', $view);
        self::assertStringContainsString("? 'green' : 'gray'", $view);
        self::assertStringContainsString("? 'orange' : 'green'", $view);
        self::assertStringContainsString('data-label="Professional Details"', $view);
        self::assertStringContainsString('data-label="Availability"', $view);
        self::assertStringContainsString("base_url('admin/doctors/view/'", $view);
        self::assertStringContainsString('consultation_fee', $model);
        self::assertStringContainsString('schedule_summary', $model);
        self::assertStringContainsString('has_upcoming_leave', $model);
    }

    public function testDoctorDirectoryPreservesExistingFilterAndPaginationContracts(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/doctor/index.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Doctor/Doctor.php');

        foreach (['doctorFilterModal', 'doctor-filter-search', 'doctor-filter-status', 'doctor-page-size', 'Doctor pagination', 'doctor-edit-trigger'] as $contract) {
            self::assertStringContainsString($contract, $view . $controller);
        }
        self::assertStringContainsString('getDoctorsPage', $controller);
        self::assertStringNotContainsString('table-striped', $view);
    }

    public function testDoctorDirectoryConstrainsLongValuesAndKeepsActionsOutsideTableOverflow(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/doctor/index.php');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/style.css');

        foreach (['doctor-directory-page', 'doctors-table-wrapper', 'doctors-table', 'doctor-contact-value', 'doctor-tag-list', 'doctor-value-badge', 'ri-actions-wrapper', 'ri-actions-menu'] as $contract) {
            self::assertStringContainsString($contract, $view);
        }
        foreach (['word-break: break-word', 'overflow-wrap: anywhere', 'overflow-x: auto', '.doctors-table', 'min-width: 64rem', 'white-space: normal'] as $contract) {
            self::assertStringContainsString($contract, $styles);
        }
        self::assertStringContainsString("table.classList.remove('admin-responsive-stack')", file_get_contents(ROOTPATH . 'app/Views/templates/theme1.php'));
        self::assertStringNotContainsString("table.classList.toggle('admin-responsive-stack'", file_get_contents(ROOTPATH . 'app/Views/templates/theme1.php'));
        self::assertStringNotContainsString('#doctors-table, #doctors-table tbody, #doctors-table tr, #doctors-table td { display:', file_get_contents(ROOTPATH . 'public/assets/css/rootins-monochrome.css'));
        self::assertStringNotContainsString('.doctors-table, .doctors-table tbody, .doctors-table tr, .doctors-table td { display:', $styles);
        self::assertStringContainsString('data-label="Doctor"', $view);
        self::assertStringContainsString('data-label="Contact"', $view);
        self::assertStringContainsString('title="<?= esc($row[\'email\'] ?? \'\') ?>"', $view);
    }

    public function testDoctorEditModalKeepsBootstrapAndAjaxContracts(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/doctor/index.php');
        $modal = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/doctor/modals/_doctor.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/admin/doctor/doctor.js');

        self::assertStringContainsString('data-doctor-id=', $view);
        self::assertStringContainsString('doctor-edit-trigger', $view);
        self::assertStringContainsString('id="doctorModal"', $modal);
        self::assertSame(1, substr_count($modal, 'id="doctorModal"'));
        self::assertStringContainsString('modal-xl', $modal);
        self::assertStringContainsString('modal-dialog-scrollable', $modal);
        self::assertStringContainsString('id="doctorModal"', $modal);
        self::assertStringContainsString('Cancel', $modal);
        self::assertStringContainsString('modal-body', $modal);
        self::assertStringContainsString('modal-footer', $modal);
        self::assertStringContainsString('container: \'body\'', $script);
        self::assertStringContainsString("$(doctorModal).modal('show')", $script);
        self::assertStringContainsString('ROUTES.doctor.edit', $script);
        self::assertStringContainsString('response.data.doctor', $script);
        self::assertStringContainsString('bindFormError(\'#doctorForm\'', $script);
        self::assertStringContainsString('complete:', $script);
        self::assertStringContainsString('window.editDoctorForm', $script);
        self::assertStringContainsString('20261019.2', $view);
    }

    public function testDoctorPrescriptionDetailsModalUsesSingleScrollableBootstrapShell(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/prescriptions.php');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/responsive-tables.css');
        $script = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/prescriptions.php');

        self::assertStringContainsString('doctor-prescription-detail-modal', $view);
        self::assertStringContainsString('modal-xl modal-dialog-scrollable', $view);
        self::assertStringContainsString('table-responsive doctor-prescription-table-wrap', $view);
        self::assertStringContainsString('data-dismiss="modal"', $view);
        self::assertStringContainsString('date(\'d-m-Y\'', $view);
        self::assertStringContainsString('show.bs.modal.doctorPrescriptionDetails', $script);
        self::assertStringContainsString('hidden.bs.modal.doctorPrescriptionDetails', $script);
        self::assertStringContainsString('data-prescription-id=', $view);
        self::assertStringContainsString('cloneNode(true)', $script);
        self::assertStringContainsString('waitForPrintDocumentAssets', $script);
        self::assertStringContainsString('doctor-prescription-print-frame', $script);
        self::assertStringContainsString('<!doctype html><html', $script);
        self::assertStringContainsString('printDocument.write', $script);
        self::assertStringContainsString('printDocument.close', $script);
        self::assertStringContainsString('frame.contentWindow.print()', $script);
        self::assertStringContainsString('printFrameCreated', $script);
        self::assertStringContainsString('Prescription data is not available for printing.', $script);
        self::assertStringContainsString('.doctor-prescription-detail-modal.show', $styles);
        self::assertStringContainsString('overflow-y: auto !important', $styles);
        self::assertStringContainsString('overflow-x: auto !important', $styles);
        self::assertStringContainsString('z-index: 1060 !important', $styles);
        self::assertStringContainsString('color: #fff !important', $styles);
    }
}
