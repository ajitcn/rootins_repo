<?php

namespace Tests\Unit;

use Doctor\Controllers\Clinical;
use PHPUnit\Framework\TestCase;
final class DoctorWorkflowTest extends TestCase
{
    public function testDoctorQueueTransitionsFollowExistingAppointmentContract(): void
    {
        $transitions = (new \ReflectionClass(Clinical::class))->getConstant('QUEUE_TRANSITIONS');
        self::assertContains('checked_in', $transitions['confirmed']);
        self::assertContains('in_queue', $transitions['checked_in']);
        self::assertContains('completed', $transitions['in_queue']);
        self::assertNotContains('completed', $transitions['confirmed']);
        self::assertSame(['completed'], $transitions['completed']);
    }
    public function testDoctorPrescriptionSubmissionUsesDraftOrSubmittedStates(): void
    {
        $states = (new \ReflectionClass(Clinical::class))->getConstant('PRESCRIPTION_STATES');
        self::assertContains('draft', $states);
        self::assertContains('submitted', $states);
        self::assertNotContains('approved', $states);
    }
    public function testDoctorWritesAndPatientViewsArePermissionProtected(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Config/Routes.php');
        $layout = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/layout.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php');
        self::assertStringContainsString("permission:appointment.edit", $routes);
        self::assertStringContainsString("permission:consultation.edit", $routes);
        self::assertStringContainsString("permission:prescription.create", $routes);
        self::assertStringContainsString("permission:patient.view", $routes);
        self::assertStringContainsString("base_url('doctor/patients')", $layout);
        self::assertStringContainsString("where('clinic_id', \$context['clinic_id'])", $controller);
        self::assertStringContainsString("where('doctor_id', \$context['doctor_id'])", $controller);
    }
    public function testDoctorCreatedPatientsRemainVisibleThroughTheDoctorClinicScope(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php');
        self::assertStringContainsString("patient_clinic_transfers", $controller);
        self::assertStringContainsString("where('transferred_by', \$context['user_id'])", $controller);
        self::assertStringContainsString("orWhereIn('patients.id', \$registeredPatients)", $controller);
        self::assertStringContainsString("where('patients.clinic_id', \$context['clinic_id'])", $controller);
    }
    public function testDoctorAppointmentPatientSelectorUsesTheSameCreatedPatientScope(): void
    {
        $records = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');
        self::assertStringContainsString("patient_clinic_transfers", $records);
        self::assertStringContainsString("where('transferred_by', \$context['user_id'])", $records);
        self::assertStringContainsString("orWhereIn('patients.id', \$registeredPatients)", $records);
        self::assertStringContainsString("applyDoctorPatientScope(\$patientQuery, \$context)", $records);
    }
    public function testDoctorRequiredMenuDestinationsHaveRoutesAndPermissionAwareLinks(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Config/Routes.php');
        $layout = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/layout.php');
        foreach (['doctor/clinical', 'doctor/patients', 'doctor/appointments', 'doctor/follow-ups', 'doctor/billing', 'doctor/prescriptions', 'doctor/notifications', 'doctor/profile'] as $path) {
            self::assertStringContainsString($path, $routes . $layout);
        }
        self::assertStringContainsString("video/(:num)", $routes);
        self::assertStringContainsString('Video consultations', $layout);
        self::assertStringContainsString('Notifications', $layout);
        self::assertStringContainsString('Profile &amp; settings', $layout);
    }
    public function testDoctorPatientViewAndEditLinksUseScopedRoutesAndPatientForm(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php');
        $list = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/patients.php');
        $detail = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/patient.php');
        self::assertStringContainsString("get('patients/(:num)'", $routes);
        self::assertStringContainsString("post('patients/update/(:num)'", $routes);
        self::assertStringContainsString('permission:patient.view', $routes);
        self::assertStringContainsString('permission:patient.edit', $routes);
        self::assertStringContainsString("base_url('doctor/patients/' . (int) \$patient['id'])", $list);
        self::assertStringContainsString("#edit-doctor-patient", $list);
        self::assertStringContainsString("where('patients.clinic_id', \$context['clinic_id'])", $controller);
        self::assertStringContainsString('scopedPatient($patientId, $context)', $controller);
        self::assertStringContainsString("base_url('permission-denied')", $controller);
        self::assertStringContainsString("where('doctor_id', \$context['doctor_id'])", $controller);
        self::assertStringContainsString("base_url('doctor/patients/update/' . (int) \$patient['id'])", $detail);
        self::assertStringContainsString('DoctorPatientLocation.init', $detail);
        self::assertStringContainsString('csrf_field()', $detail);
    }
    public function testDoctorPatientUpdatePreservesRequiredEmailAndRejectsOutOfScopeRecords(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php');
        self::assertStringContainsString("\$data['email'] = (string) (\$patient['email'] ?? '')", $controller);
        self::assertStringContainsString("\$data['date_of_birth'] = \$patient['date_of_birth'] ?? null", $controller);
        self::assertStringContainsString("\$patient = \$this->scopedPatient(\$patientId, \$context)", $controller);
        self::assertStringContainsString("where('patients.clinic_id', \$context['clinic_id'])", $controller);
        self::assertStringContainsString("log_message('error', 'Doctor patient update failed: '", $controller);
        self::assertStringContainsString("'doctor.patient_updated'", $controller);
    }
    public function testDoctorPatientEditFormIsRenderedInsideThePageSection(): void
    {
        $detail = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/patient.php');
        $modalPosition = strpos($detail, 'id="edit-doctor-patient"');
        $sectionEndPosition = strpos($detail, "<?= \$this->endSection() ?>");
        self::assertNotFalse($modalPosition);
        self::assertNotFalse($sectionEndPosition);
        self::assertLessThan($sectionEndPosition, $modalPosition);
        self::assertStringContainsString('id="doctor-patient-edit-form"', $detail);
        self::assertStringContainsString('name="date_of_birth"', $detail);
        self::assertStringContainsString('name="gender"', $detail);
        self::assertStringContainsString('name="email"', $detail);
        self::assertStringContainsString('modal-dialog-scrollable', $detail);
        self::assertStringContainsString('data-keyboard="true"', $detail);
        self::assertStringContainsString('aria-live="polite"', $detail);
        self::assertStringContainsString('id="doctor-patient-edit-submit"', $detail);
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/style.css');
        self::assertStringContainsString('.ri-scroll-modal .modal-body', $styles);
        self::assertStringContainsString('max-height: calc(100vh - 2rem)', $styles);
        self::assertStringContainsString('overflow-y: auto', $styles);
        self::assertStringContainsString('ri-scroll-modal', $detail);
    }
    public function testDoctorPatientUpdateAllowsSelectedLocationIdsWithoutManualNames(): void
    {
        $locationService = file_get_contents(ROOTPATH . 'app/Services/LocationService.php');
        self::assertStringContainsString("if (\$id > 0) {", $locationService);
        self::assertStringContainsString("if (\$name !== '') {", $locationService);
        self::assertStringContainsString("\$name = \$this->normalize(\$name);", $locationService);
        $locationScript = file_get_contents(ROOTPATH . 'public/assets/js/doctor/patient_location.js');
        self::assertStringContainsString('country-code-manual-toggle', $locationScript);
        self::assertStringContainsString('data-live-search="true"', $locationScript);
    }
    public function testDoctorAppointmentAndRecordActionsRemainScopedAndAudited(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Config/Routes.php');
        $records = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');
        $appointments = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/appointments.php');
        $prescriptions = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/prescriptions.php');
        self::assertStringContainsString("appointments/create", $routes);
        self::assertStringContainsString("appointments/reschedule/(:num)", $routes);
        self::assertStringContainsString("appointments/cancel/(:num)", $routes);
        self::assertStringContainsString("permission:appointment.create", $routes);
        self::assertStringContainsString("permission:appointment.edit", $routes);
        self::assertStringContainsString("doctor.appointment_created", $records);
        self::assertStringContainsString("doctor.appointment_rescheduled", $records);
        self::assertStringContainsString("doctor.appointment_cancelled", $records);
        self::assertStringContainsString("availabilityError", $records);
        self::assertStringContainsString('id="doctor-appointment-create-form"', $appointments);
        self::assertStringContainsString('data-live-search="true"', $appointments);
        self::assertStringContainsString('View full prescription', $prescriptions);
        self::assertStringContainsString('prescription_items', $records);
    }
    public function testDoctorFollowUpCreationIsScopedValidatedAndAudited(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Config/Routes.php');
        $records = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/follow-ups.php');
        self::assertStringContainsString("post('follow-ups/create'", $routes);
        self::assertStringContainsString('permission:consultation.edit', $routes);
        self::assertStringContainsString('public function createFollowUp', $records);
        self::assertStringContainsString("where('doctor_id', \$context['doctor_id'])", $records);
        self::assertStringContainsString("where('clinic_id', \$context['clinic_id'])", $records);
        self::assertStringContainsString("'doctor.follow_up_created'", $records);
        self::assertStringContainsString("=== 'completed' ? 'completed' : \$status", $records);
        self::assertStringContainsString("'status' => \$effectiveStatus", $records);
        self::assertStringContainsString('New Follow-up', $view);
        self::assertStringContainsString('data-live-search="true"', $view);
        self::assertStringContainsString('csrf_field()', $view);
        self::assertStringNotContainsString('.DataTable()', $view);
        self::assertStringContainsString('ri-scroll-modal', $view);
        self::assertStringContainsString('id="doctor-followup-create-submit"', $view);
    }
    public function testDoctorClinicalWorkspaceHasScopedPatientContextAndCsrfProtectedActions(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php');
        self::assertStringContainsString('id="clinical-patient-context"', $view);
        self::assertStringContainsString('data-live-search="true"', $view);
        self::assertStringContainsString("base_url('doctor/patients')", $view);
        self::assertGreaterThanOrEqual(2, substr_count($view, 'csrf_field()'));
        self::assertStringContainsString("base_url('doctor/appointments/queue')", $view);
        self::assertStringContainsString("base_url('doctor/consultations/save')", $view);
        self::assertStringContainsString("base_url('doctor/prescriptions/save')", $view);
        self::assertStringContainsString("\$can('appointment.edit')", $view);
        self::assertStringContainsString("\$can('consultation.edit')", $view);
        self::assertStringContainsString("\$can('prescription.create')", $view);
        self::assertStringContainsString("base_url('doctor/follow-ups')", $view);
        self::assertStringContainsString('historyPagination', $view);
        self::assertStringContainsString('parts/pagination', $view);
        self::assertStringNotContainsString('.DataTable()', $view);
    }
    public function testDoctorSupportDestinationsUseScopedNotificationStorageAndNoLiveProviderCall(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-09-01-120000_CreateDoctorNotifications.php');
        $notification = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Notification.php');
        $video = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/VideoConsultation.php');
        self::assertStringContainsString("createTable('doctor_notifications')", $migration);
        self::assertStringContainsString("where('doctor_id', \$context['doctor_id'])", $notification);
        self::assertStringContainsString("where('clinic_id', \$context['clinic_id'])", $notification);
        self::assertStringContainsString("'configured' => false", $video);
        self::assertStringNotContainsString('TWILIO_AUTH_TOKEN', $video);
    }
    public function testAdminDoctorManagementHasScopedDetailAndPostDeactivateRoutes(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admin/Config/Routes.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/doctor/index.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Doctor/Doctor.php');
        self::assertStringContainsString("get('view/(:num)'", $routes);
        self::assertStringContainsString("post('delete/(:num)'", $routes);
        self::assertStringContainsString("base_url('admin/doctors/view/'", $view);
        self::assertStringContainsString("method=\"post\"", $view);
        self::assertStringContainsString('validateAssignments', $controller);
        self::assertStringContainsString('Doctor could not be created safely.', $controller);
        self::assertStringContainsString('Doctor could not be updated safely.', $controller);
    }
}
