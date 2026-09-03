<?php

namespace Tests\Unit;

use Patient\Controllers\Portal;
use PHPUnit\Framework\TestCase;
final class PatientWorkflowTest extends TestCase
{
    public function testPatientAppointmentTypesAreTheSupportedConsultationModes(): void
    {
        $types = (new \ReflectionClass(Portal::class))->getConstant('APPOINTMENT_TYPES');
        self::assertSame(['in_person', 'video', 'phone'], $types);
    }
    public function testPatientCanOnlyEditOpenAppointments(): void
    {
        $statuses = (new \ReflectionClass(Portal::class))->getConstant('PATIENT_EDITABLE_APPOINTMENT_STATUSES');
        self::assertSame(['scheduled', 'confirmed'], $statuses);
        self::assertNotContains('completed', $statuses);
        self::assertNotContains('cancelled', $statuses);
    }
    public function testPatientDashboardReadsInvoicesWithinPatientAndClinicScope(): void
    {
        $source = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Portal.php');
        self::assertStringContainsString("->where('patient_id', \$patientId)", $source);
        self::assertStringContainsString("->where('clinic_id', \$clinicId)", $source);
        self::assertStringContainsString("'invoices' => \$invoices", $source);
    }
    public function testPatientRegistrationNormalizesOptionalEmailForStrictSchemas(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Admin/Services/PatientRegistrationService.php');
        self::assertMatchesRegularExpression('/if\s*\(!\s*array_key_exists\(\x27email\x27, \$insertData\).*\$insertData\[\x27email\x27\]\s*===\s*null\)/', $service);
        self::assertStringContainsString("\$insertData['email'] = '';", $service);
    }
    public function testDoctorPatientCreationKeepsPermissionScopeAndSafeValidationContract(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php');
        self::assertStringContainsString("patients/create", $routes);
        self::assertStringContainsString("permission:patient.create", $routes);
        self::assertStringContainsString("'Patient could not be created safely.'", $controller);
        self::assertStringContainsString("where('clinic_id', \$context['clinic_id'])", $controller);
        self::assertMatchesRegularExpression('/if\s*\(\$errors\s*!==\s*\[\]\)\s*\{?\s*return\s*\$this->validation\(\$errors\)/', $controller);
    }
    public function testAdminPatientManagementHasScopedStatusRouteAndNoDataTablesInitializer(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Admin/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Patient/Patient.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/admin/patient/patient.js');
        self::assertStringContainsString("'Patient::status/\$1'", $routes);
        self::assertStringContainsString("'filter' => 'permission:patient.edit'", $routes);
        self::assertStringContainsString('clinicScope', $controller);
        self::assertStringContainsString('admin.patient_status_changed', $controller);
        self::assertStringNotContainsString('.DataTable()', $script);
    }
    public function testPatientNotificationsHaveScopedReadRouteAndPaginationContract(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Patient/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Notification.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-09-01-100000_CreatePatientNotifications.php');
        self::assertStringContainsString("notifications', 'Notification::index", $routes);
        self::assertStringContainsString("notifications/(:num)/read", $routes);
        self::assertStringContainsString("where('patient_id', \$context['id'])", $controller);
        self::assertStringContainsString("where('clinic_id', \$context['clinic_id'])", $controller);
        self::assertStringContainsString("'status' => 'read'", $controller);
        self::assertStringContainsString("createTable('patient_notifications')", $migration);
    }
    public function testPatientLocationContractUsesHierarchicalNormalizedLookups(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-09-01-140000_AddPatientLocationLocalities.php');
        $service = file_get_contents(ROOTPATH . 'app/Services/LocationService.php');
        $adminForm = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/patient/modals/form.php');
        $registration = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/patient/registration.php');
        self::assertStringContainsString("createTable('localities')", $migration);
        self::assertStringContainsString("patients_locality_fk", $migration);
        self::assertStringContainsString("'normalized_name'", $migration);
        self::assertStringContainsString('resolvePatientLocation', $service);
        self::assertStringContainsString('name="locality_id"', $adminForm);
        self::assertStringContainsString('name="locality_id"', $registration);
        self::assertStringContainsString('name="country_name"', $adminForm);
        self::assertStringContainsString('name="state_name"', $adminForm);
        self::assertStringContainsString('name="city_name"', $adminForm);
        self::assertSame(4, substr_count($adminForm, 'location-manual-toggle'));
        self::assertStringContainsString('bindLocationManualToggles', file_get_contents(ROOTPATH . 'public/assets/js/common.js'));
        self::assertStringContainsString('country_code_id', file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-09-01-160000_AddNormalizedMobileReferences.php'));
        self::assertStringContainsString('mobile_normalized', file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php'));
        self::assertStringContainsString("location/localities/", file_get_contents(ROOTPATH . 'app/Config/Routes.php'));
    }
    public function testSharedRegistrationSupportsSourceMetadataAndAtomicAppointmentContract(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Admin/Services/PatientRegistrationService.php');
        $model = file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/PatientModel.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-09-02-110000_AddPatientSourceAndAppointmentService.php');
        self::assertStringContainsString("['admin', 'doctor', 'api', 'patient', 'import']", $service);
        self::assertStringContainsString("source_type", $model);
        self::assertStringContainsString("createAppointmentInTransaction", $service);
        self::assertStringContainsString("\$patientData['_appointment']", $service);
        self::assertStringContainsString("addColumn('patients'", $migration);
        self::assertStringContainsString("addColumn('appointments'", $migration);
    }
    public function testExternalPatientEndpointUsesExplicitTokenBoundaryAndRateLimit(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Api/Patients.php');
        self::assertStringContainsString("api/v1/patients", $routes);
        self::assertStringContainsString("ROOTINS_API_TOKEN", $controller);
        self::assertStringContainsString("hash_equals", $controller);
        self::assertStringContainsString("Too many requests.", $controller);
        self::assertStringContainsString("'source_type' => 'api'", $controller);
    }
    public function testDoctorPatientServiceLookupUsesAutoloadableMasterRecordModel(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php');
        self::assertStringContainsString('use Admin\\Models\\AdminMasterRecordModel;', $controller);
        self::assertStringContainsString("where('master_type', 'services')", $controller);
        self::assertStringNotContainsString('use Admin\\Models\\Configuration\\ServiceModel;', $controller);
    }
    public function testDoctorCountryCodeAndMobileFieldsShareResponsiveAlignmentContract(): void
    {
        $script = file_get_contents(ROOTPATH . 'public/assets/js/doctor/patient_location.js');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/style.css');
        self::assertStringContainsString('country-code-field', $script);
        self::assertStringContainsString('mobile-number-field', $script);
        self::assertStringContainsString('contact-number-row', $script);
        self::assertStringContainsString('col-12 col-md-6 contact-number-row-wrapper', $script);
        self::assertStringContainsString('contact-number-row > .country-code-field', $styles);
        self::assertStringContainsString('max-width: 100%', $styles);
        self::assertStringContainsString('country-code-field .validation-error', $styles);
    }
}
