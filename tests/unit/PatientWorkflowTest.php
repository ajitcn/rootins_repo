<?php

namespace Tests\Unit;

use Patient\Controllers\Portal;
use Admin\Models\Location\TelephoneCountryCode;
use PHPUnit\Framework\TestCase;

final class PatientWorkflowTest extends TestCase
{
    public function testPatientPanelHasDedicatedAuthenticationAndScopedRoutes(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Patient/Config/Routes.php');
        $filter = file_get_contents(ROOTPATH . 'app/Filters/PatientAuth.php');
        $auth = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Auth.php');

        self::assertStringContainsString("patient/login", $routes);
        self::assertStringContainsString("filter' => 'patientauth'", $routes);
        self::assertStringContainsString("patient/logout", file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/layout.php'));
        self::assertStringContainsString('patient_authenticated', $filter);
        self::assertStringContainsString('session()->regenerate(true)', $auth);
        self::assertStringContainsString("'roles' => ['Patient']", $auth);
    }

    public function testPatientAuthenticationUsesPatientCredentialsNotStaffUsers(): void
    {
        $auth = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Auth.php');
        $model = file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/PatientModel.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-04-120000_AddPatientAuthenticationFields.php');

        self::assertStringContainsString('password_hash', $auth);
        self::assertStringNotContainsString('use Admin\\Models\\User;', $auth);
        self::assertStringContainsString('password_hash', $model);
        self::assertStringContainsString("addColumn('patients'", $migration);
        self::assertStringContainsString('failed_login_attempts', $migration);
        self::assertStringContainsString('locked_until', $migration);
    }

    public function testPatientPasswordHashColumnHasAnIdempotentFollowUpMigration(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-05-100000_EnsurePatientPasswordHash.php');

        self::assertStringContainsString("fieldExists('password_hash', 'patients')", $migration);
        self::assertStringContainsString("'type' => 'VARCHAR'", $migration);
        self::assertStringContainsString("'constraint' => 255", $migration);
        self::assertStringContainsString("'null' => true", $migration);
        self::assertStringContainsString("dropColumn('patients', 'password_hash')", $migration);
    }

    public function testPatientPasswordSetupUsesOnlyTheStoredHashAndSafeDatabaseFailure(): void
    {
        $auth = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Auth.php');

        self::assertStringContainsString("'password_hash' => password_hash(\$password, PASSWORD_DEFAULT)", $auth);
        self::assertStringContainsString('catch (\\Throwable $e)', $auth);
        self::assertStringContainsString('Password could not be created safely.', $auth);
        self::assertStringNotContainsString("'password' => \$password", $auth);
    }

    public function testPatientPasswordSetupChecksSchemaAndLogsSafeContextOnly(): void
    {
        $auth = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Auth.php');

        self::assertStringContainsString("fieldExists(\$field, 'patients')", $auth);
        self::assertStringContainsString('route=patient/login/setup', $auth);
        self::assertStringContainsString('lookup=unique', $auth);
        self::assertStringContainsString('field_keys={field_keys}', $auth);
        self::assertStringContainsString('exception={exception}', $auth);
        self::assertStringContainsString('file={file}', $auth);
        self::assertStringContainsString('line={line}', $auth);
        self::assertStringNotContainsString('$e->getMessage()', $auth);
        self::assertStringNotContainsString('patient_id={patient_id}', $auth);
    }

    public function testPatientPasswordSetupUsesTransactionForHashUpdate(): void
    {
        $auth = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Auth.php');

        self::assertStringContainsString('$db->transBegin()', $auth);
        self::assertStringContainsString('$db->transCommit()', $auth);
        self::assertStringContainsString('$db->transRollback()', $auth);
        self::assertStringContainsString('password_hash', $auth);
    }

    public function testPatientLoginRendersFailedLoginFlashMessage(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/auth/login.php');

        self::assertStringContainsString("getFlashdata('error')", $view);
        self::assertStringContainsString('alert-danger', $view);
        self::assertStringContainsString('esc($loginError)', $view);
    }

    public function testPatientFirstLoginCanCreateASeparatePatientPassword(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Patient/Config/Routes.php');
        $auth = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Auth.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/auth/login.php');

        self::assertStringContainsString('patient/login/setup', $routes);
        self::assertStringContainsString('setupPassword', $auth);
        self::assertStringContainsString('mobile_number', $auth);
        self::assertStringContainsString('telephone_country_code_id', $auth);
        self::assertStringContainsString('password_hash($password, PASSWORD_DEFAULT)', $auth);
        self::assertStringContainsString('Create patient password', $view);
        self::assertStringContainsString('name="mobile_number"', $view);
        self::assertStringNotContainsString('name="setup_email"', $view);
        self::assertStringNotContainsString('name="registration_number"', $view);
        self::assertStringNotContainsString('name="date_of_birth"', $view);
    }

    public function testPatientFirstLoginUsesOnlyNormalizedMobileAndCountryCodeFields(): void
    {
        $auth = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Auth.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/auth/login.php');

        self::assertStringContainsString('normalizedMobile', $auth);
        self::assertStringContainsString('TelephoneCountryCode::normalizeCode', $auth);
        self::assertStringContainsString('findAll()', $auth);
        self::assertStringContainsString('The supplied mobile details match more than one patient account.', $auth);
        self::assertStringContainsString('id="country_codeErr"', $view);
        self::assertStringContainsString('id="mobile_numberErr"', $view);
        self::assertStringContainsString('id="passwordErr"', $view);
        self::assertStringContainsString('id="password_confirmationErr"', $view);
        self::assertStringContainsString('name="telephone_country_code_id"', $view);
        self::assertStringContainsString('name="country_code"', $view);
        self::assertStringNotContainsString('patient_id', $auth);
    }

    public function testPatientFirstLoginDoesNotExposeAnOtpBecauseNoOtpIsImplemented(): void
    {
        $auth = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Auth.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/auth/login.php');

        self::assertStringNotContainsString('otp', strtolower($auth . $view));
    }

    public function testPatientLoginAndSetupFormsUseMutuallyExclusiveTabs(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/auth/login.php');

        self::assertStringContainsString('role="tablist"', $view);
        self::assertStringContainsString('role="tabpanel"', $view);
        self::assertStringContainsString('data-patient-auth-tab="login"', $view);
        self::assertStringContainsString('data-patient-auth-tab="setup"', $view);
        self::assertStringContainsString('loginPanel.hidden = showSetup', $view);
        self::assertStringContainsString('setupPanel.hidden = !showSetup', $view);
        self::assertStringContainsString('name="login_identifier"', $view);
        self::assertStringContainsString('Email or mobile number', $view);
        self::assertStringContainsString('login_identifierErr', $view);
        self::assertStringContainsString('control.disabled = showSetup', $view);
        self::assertStringContainsString('control.disabled = !showSetup', $view);
        self::assertSame(2, substr_count($view, 'csrf_field()'));
    }

    public function testPatientLoginResolvesEmailAndNormalizedInternationalMobile(): void
    {
        $auth = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Auth.php');

        self::assertStringContainsString("getPost('login_identifier')", $auth);
        self::assertStringContainsString("where('patients.email'", $auth);
        self::assertStringContainsString("where('patients.mobile_normalized'", $auth);
        self::assertStringContainsString('TelephoneCountryCode()', $auth);
        self::assertStringContainsString('resolveMobileIdentifier', $auth);
        self::assertStringContainsString('count($patients) === 1', $auth);
        self::assertStringContainsString('Invalid email/mobile number or password.', $auth);
    }

    public function testPatientPanelExposesDedicatedDestinations(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Patient/Config/Routes.php');

        foreach (['profile', 'appointments', 'prescriptions', 'orders', 'invoices', 'notifications', 'visits', 'documents', 'support'] as $path) {
            self::assertStringContainsString("get('{$path}'", $routes);
        }

        self::assertStringContainsString('class Pages', file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Pages.php'));
        self::assertStringContainsString('where(\'patients.id\', $patientId)', file_get_contents(ROOTPATH . 'app/Services/PatientScope.php'));
    }

    public function testPatientPagesRenderRealScopedRecordContracts(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Pages.php');
        $recordsView = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/records.php');
        $sectionView = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/section.php');

        foreach (['appointments', 'prescriptions', 'medicine_orders', 'invoices', 'consultations', 'patient_documents'] as $table) {
            self::assertStringContainsString($table, $controller);
        }

        foreach (['appointment_number', 'prescription_number', 'order_number', 'invoice_number', 'consultation_number', 'document_type'] as $field) {
            self::assertStringContainsString($field, $recordsView);
        }

        self::assertStringContainsString("name=\"search\"", $sectionView);
        self::assertStringContainsString("name=\"page_size\"", $sectionView);
        self::assertStringContainsString('validation-error', $sectionView);
        self::assertStringContainsString('patient/profile', $sectionView);
    }

    public function testStaffAuthRejectsPatientSession(): void
    {
        $authFilter = file_get_contents(ROOTPATH . 'app/Filters/Auth.php');

        self::assertStringContainsString('patient_authenticated', $authFilter);
        self::assertStringContainsString('Patient accounts cannot access staff workflows.', $authFilter);
        self::assertStringContainsString("setStatusCode(403)", $authFilter);
    }

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

    public function testPatientDashboardUsesScopedSummaryDataAndResponsiveSections(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Dashboard.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/dashboard.php');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/style.css');

        foreach (['appointments', 'prescriptions', 'medicine_orders', 'invoices', 'patient_notifications'] as $table) {
            self::assertStringContainsString($table, $controller);
        }

        foreach (["where('appointments.patient_id', \$patientId)", "where('prescriptions.patient_id', \$patientId)", "where('orders.patient_id', \$patientId)", "where('patient_id', \$patientId)"] as $scopeRule) {
            self::assertStringContainsString($scopeRule, $controller);
        }

        foreach (['patient-dashboard-hero', 'patient-kpi-grid', 'Upcoming appointments', 'Recent prescriptions', 'Recent medicine orders', 'Recent invoices', 'Notifications', 'patient-mark-read'] as $contract) {
            self::assertStringContainsString($contract, $view);
        }

        self::assertStringContainsString('patient-dashboard-grid', $styles);
        self::assertStringContainsString('@media (max-width: 767.98px)', $styles);
    }

    public function testPatientDashboardUsesSupportedBuilderResultMethods(): void
    {
        $dashboard = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Dashboard.php');

        self::assertStringNotContainsString('->first()', $dashboard);
        self::assertStringContainsString('->get(1)', $dashboard);
        self::assertStringContainsString('->getRowArray()', $dashboard);
    }

    public function testPatientProfileUsesScopedEditableContractAndExactFieldErrors(): void
    {
        $pages = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Pages.php');
        $portal = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Portal.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/profile.php');

        self::assertStringContainsString("where('patients.id', \$context['patient_id'])", $pages);
        self::assertStringContainsString("where('patients.clinic_id', \$context['clinic_id'])", $pages);
        self::assertStringContainsString('normalizeProfileDate', $portal);
        self::assertStringContainsString("format('Y-m-d')", $portal);
        self::assertStringContainsString('resolvePatientLocation', $portal);
        self::assertStringContainsString('patient-profile-form', $view);
        self::assertStringContainsString('placeholder="dd-mm-yyyy"', $view);
        self::assertStringContainsString('name="date_of_birth" data-submit-display-date="true"', $view);

        foreach (['first_name', 'last_name', 'country_code', 'mobile_number', 'email', 'gender', 'date_of_birth', 'postal_code', 'address'] as $field) {
            self::assertStringContainsString('id="' . $field . 'Err"', $view);
        }

        self::assertStringContainsString('id="<?= esc($field) ?>Err"', $view);

        self::assertStringContainsString('bindLocationManualToggles(form)', $view);
        self::assertStringContainsString('restoreValue(country, saved.country)', $view);
        self::assertStringContainsString('restoreValue(state, saved.state)', $view);
        self::assertStringContainsString('restoreValue(city, saved.city)', $view);
        self::assertStringContainsString('restoreValue(locality, saved.locality)', $view);
        self::assertStringContainsString('function restoreSavedLocation(level)', $view);
        self::assertStringContainsString("change.patientProfileManual", $view);
        self::assertStringContainsString("saved = {country: value, state: '', city: '', locality: ''}", $view);
        self::assertStringContainsString('previous.country', $view);
        self::assertStringContainsString('loadStates', $view);
        self::assertStringContainsString('loadCities', $view);
        self::assertStringContainsString('loadLocalities', $view);
        self::assertStringContainsString("method: 'POST'", $view);
    }

    public function testPatientRegistrationNormalizesOptionalEmailForStrictSchemas(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Admin/Services/PatientRegistrationService.php');
        self::assertMatchesRegularExpression('/if\s*\(!\s*array_key_exists\(\x27email\x27, \$insertData\).*\$insertData\[\x27email\x27\]\s*===\s*null\)/', $service);
        self::assertStringContainsString("\$insertData['email'] = '';", $service);
    }

    public function testPatientAppointmentsUseScopedListAndGridContracts(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Patient/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Pages.php');
        $list = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/appointments_list.php');
        $grid = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/appointments_grid.php');
        $page = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/appointments.php');

        self::assertStringContainsString('$routes->get(\'appointments\', \'Pages::appointments\');', $routes);
        self::assertStringContainsString("->where('appointments.patient_id', \$context['patient_id'])", $controller);
        self::assertStringContainsString("->where('appointments.clinic_id', \$context['clinic_id'])", $controller);
        self::assertStringContainsString("in_array(\$this->request->getGet('view'), ['list', 'grid'], true)", $controller);
        self::assertStringContainsString('appointments_list', $page);
        self::assertStringContainsString('appointments_grid', $page);
        self::assertStringContainsString('<caption class="sr-only">Your appointments</caption>', $list);
        self::assertStringContainsString('patient-appointment-view', $list);
        self::assertStringContainsString('patient-appointment-view', $grid);
        self::assertStringContainsString('patient-appointment-reschedule', $list);
        self::assertStringContainsString('patient-appointment-cancel', $list);
        self::assertStringContainsString('appointment_date_display', $controller);
    }

    public function testPatientAppointmentDetailsModalUsesPatientSafeScrollableStructure(): void
    {
        $page = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/appointments.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Pages.php');
        $style = file_get_contents(ROOTPATH . 'public/assets/css/style.css');

        self::assertStringContainsString('patient-appointment-detail-header', $page);
        self::assertStringContainsString('patient-appointment-detail-body', $page);
        self::assertStringContainsString('patient-appointment-detail-actions', $page);
        self::assertStringContainsString('patient-modal-section', $page);
        self::assertStringContainsString('reason_for_visit', $page);
        self::assertStringNotContainsString('clinical_notes', $page);
        self::assertStringNotContainsString('inventory', $page);
        self::assertStringContainsString('modal-body', $style);
        self::assertStringContainsString('overflow-y: auto', $style);
        self::assertStringContainsString('appointments.reason_for_visit', $controller);
        self::assertStringContainsString('patient_can_edit', $controller);
    }

    public function testPatientAppointmentMutationsKeepDateAndReasonValidationServerSide(): void
    {
        $portal = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Portal.php');

        self::assertStringContainsString('normalizeAppointmentDate', $portal);
        self::assertStringContainsString("Past appointments cannot be rescheduled.", $portal);
        self::assertStringContainsString("Past appointments cannot be cancelled.", $portal);
        self::assertStringContainsString("\$errors['cancellation_reason']", $portal);
        self::assertStringContainsString("'cancellation_reason' => \$reason", $portal);
        self::assertStringContainsString("where('patient_id', \$context['patient']['id'])", $portal);
    }

    public function testPatientPrescriptionsUseScopedListGridAndSafeDetailContracts(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Pages.php');
        $page = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/prescriptions.php');
        $list = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/prescriptions_list.php');
        $grid = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/prescriptions_grid.php');
        $style = file_get_contents(ROOTPATH . 'public/assets/css/style.css');

        self::assertStringContainsString('public function prescriptions()', $controller);
        self::assertStringContainsString("->where('prescriptions.patient_id', \$context['patient_id'])", $controller);
        self::assertStringContainsString("->where('prescriptions.clinic_id', \$context['clinic_id'])", $controller);
        self::assertStringContainsString('prescription_items', $controller);
        self::assertStringContainsString('prescriptions_list', $page);
        self::assertStringContainsString('prescriptions_grid', $page);
        self::assertStringContainsString('patient-prescription-detail-modal', $page);
        self::assertStringContainsString('patient-prescription-print', $page);
        self::assertStringContainsString('<caption class="sr-only">Your prescriptions</caption>', $list);
        self::assertStringContainsString('patient-prescription-view', $grid);
        self::assertStringNotContainsString('clinical_notes', $page);
        self::assertStringNotContainsString('inventory', $page);
        self::assertStringContainsString('patient-prescription-medicine-table', $style);
        self::assertStringContainsString('overflow-y: auto', $style);
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
        self::assertStringContainsString("where('patient_id', \$context['patient_id'])", $controller);
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
        self::assertStringContainsString('resolveCountryCode($patientData)', $service);
        self::assertStringContainsString('TelephoneCountryCode', $service);
        self::assertStringContainsString('resolveOrCreate($value, null, $countryName)', $service);
        self::assertStringContainsString("\$patientData['country_code_id'] = \$country['country_id'] !== null", $service);
        self::assertStringContainsString("\$patientData['telephone_country_code_id'] = (int) \$country['id']", $service);
        self::assertStringContainsString("\$patientData['country_code'] = (string) \$country['normalized_code']", $service);
        self::assertStringContainsString('telephone_country_codes', file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-09-06-120000_CreateTelephoneCountryCodes.php'));
        self::assertStringContainsString("addColumn('patients'", $migration);
        self::assertStringContainsString("addColumn('appointments'", $migration);
    }

    public function testDoctorCountryCodeModesAndReportedSafeErrorAreCovered(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php');
        $service = file_get_contents(ROOTPATH . 'app/Modules/Admin/Services/PatientRegistrationService.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/doctor/patient_location.js');
        $common = file_get_contents(ROOTPATH . 'public/assets/js/common.js');

        self::assertStringContainsString('name="country_code_manual" value="1"', $script);
        self::assertStringContainsString("value=\"' + Number(item.id)", $script);
        self::assertStringContainsString("select.prop('disabled', manual)", $common);
        self::assertStringContainsString("select.prop('disabled', manual)", $common);
        self::assertStringContainsString("if (manual) select.val('')", $common);
        self::assertStringContainsString(".toggleClass('d-none', ! manual)", $common);
        self::assertStringContainsString(".prop('disabled', ! manual)", $common);
        self::assertStringContainsString('normalizeCountryCode(value)', $common);
        self::assertStringContainsString("if (\$manual)", $controller);
        self::assertStringContainsString("if (\$manualCountryCode)", $controller);
        self::assertStringContainsString("preg_replace('/^\\+/', '', \$countryCode)", $controller);
        self::assertStringContainsString("preg_match('/^[1-9][0-9]{0,3}$/', \$normalizedCountryCode)", $controller);
        self::assertStringContainsString("trim((string) \$this->request->getPost('telephone_country_code_id')) !== ''", $controller);
        self::assertStringContainsString("where('normalized_code', \$normalizedCode)", $controller);
        self::assertStringContainsString("'Select a valid telephone country code.'", $controller);
        self::assertStringContainsString("'A valid country code is required.'", $service);
        self::assertStringContainsString("\$patientData['country_code_id'] = \$country['country_id'] !== null", $service);
        self::assertStringContainsString("'country_code_manual' => (string) \$this->request->getPost('country_code_manual') === '1'", $controller);
    }

    public function testManualCountryCodeAcceptsOneToFourDigitsAndRejectsOtherValues(): void
    {
        $rule = '/^\+?[0-9][0-9]{0,3}$/';

        foreach (['+91', '91', '+78', '78', '1', '1234'] as $value) {
            self::assertMatchesRegularExpression($rule, $value);
        }

        foreach (['', ' 91', '9 1', 'abc', '+abcd', '12345'] as $value) {
            self::assertDoesNotMatchRegularExpression($rule, $value);
        }

        $locationScript = file_get_contents(ROOTPATH . 'public/assets/js/doctor/patient_location.js');
        $common = file_get_contents(ROOTPATH . 'public/assets/js/common.js');
        $service = file_get_contents(ROOTPATH . 'app/Modules/Admin/Services/PatientRegistrationService.php');
        $codeModel = file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/Location/TelephoneCountryCode.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-09-06-120000_CreateTelephoneCountryCodes.php');
        self::assertStringContainsString('pattern="\\\\+?[0-9][0-9]{0,3}"', $locationScript);
        self::assertStringContainsString('function isValidCountryCode(value)', $common);
        self::assertStringContainsString("preg_replace('/^\\\\+/', '', trim(\$value))", $codeModel);
        self::assertStringContainsString("preg_match('/^[1-9][0-9]{0,3}$/', \$normalized)", $codeModel);
        self::assertStringContainsString('resolveOrCreate', $codeModel);
        self::assertStringContainsString("addUniqueKey('normalized_code')", $migration);
        self::assertStringContainsString('telephone_country_code_id', $migration);
    }

    public function testManualCountryNameAndCodeUseTheSharedLookupContract(): void
    {
        self::assertSame('78', TelephoneCountryCode::normalizeCode('+78'));
        self::assertSame('78', TelephoneCountryCode::normalizeCode('78'));
        self::assertSame('Example Country', TelephoneCountryCode::normalizeCountryName("  Example\t Country "));
        self::assertNull(TelephoneCountryCode::normalizeCode('12345'));
        self::assertNull(TelephoneCountryCode::normalizeCode('9 1'));
        self::assertNull(TelephoneCountryCode::normalizeCode('0'));
        self::assertNull(TelephoneCountryCode::normalizeCode(''));
        self::assertNull(TelephoneCountryCode::normalizeCode('abc'));

        $model = file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/Location/TelephoneCountryCode.php');
        $service = file_get_contents(ROOTPATH . 'app/Modules/Admin/Services/PatientRegistrationService.php');
        $doctorScript = file_get_contents(ROOTPATH . 'public/assets/js/doctor/patient_location.js');
        $common = file_get_contents(ROOTPATH . 'public/assets/js/common.js');
        $adminForm = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/patient/modals/form.php');
        $adminRegistration = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/patient/registration.php');
        $patientPortal = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/portal.php');
        $telephoneContact = file_get_contents(ROOTPATH . 'app/Views/templates/_telephone_contact.php');
        $clinicForm = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/clinic/modals/_clinic.php');
        $doctorForm = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/doctor/modals/_doctor.php');
        $userForm = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/access/users.php');
        $doctorProfile = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/profile.php');
        $theme = file_get_contents(ROOTPATH . 'app/Views/templates/theme1.php');

        self::assertStringContainsString('resolveOrCreate(string $code, ?int $countryId = null, ?string $countryName = null)', $model);
        self::assertStringContainsString('countryIdByName', $model);
        self::assertStringContainsString("->where('normalized_name', strtolower(\$countryName))", $model);
        self::assertStringNotContainsString("where('LOWER(TRIM(name))'", $model);
        self::assertStringContainsString('telephone_country_name', $service);
        self::assertStringContainsString('name="telephone_country_name"', $doctorScript);
        self::assertStringContainsString('data-name-input', $doctorScript);
        self::assertStringContainsString('name="telephone_country_name"', $adminForm);
        self::assertStringContainsString('name="telephone_country_name"', $adminRegistration);
        self::assertStringContainsString('name="telephone_country_name"', $patientPortal);
        self::assertStringContainsString("input.trigger('focus')", $common);
        self::assertStringContainsString('name="telephone_country_name"', $telephoneContact);
        self::assertStringContainsString('name="telephone_country_code_id"', $telephoneContact);
        self::assertStringContainsString('_telephone_contact', $clinicForm);
        self::assertStringContainsString('_telephone_contact', $doctorForm);
        self::assertStringContainsString('_telephone_contact', $userForm);
        self::assertStringContainsString('_telephone_contact', $doctorProfile);
        self::assertStringContainsString('bindCountryCodeToggles(document)', $theme);
        self::assertStringContainsString('AddTelephoneCountryCodeReferences', file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-09-07-120000_AddTelephoneCountryCodeReferences.php'));
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

    public function testDoctorPatientServiceLookupUsesDedicatedServiceModel(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php');
        self::assertStringContainsString('use Admin\\Models\\Configuration\\ServiceModel;', $controller);
        self::assertStringContainsString('new ServiceModel()', $controller);
        self::assertStringNotContainsString('use Admin\\Models\\AdminMasterRecordModel;', $controller);
    }

    public function testDoctorCountryCodeAndMobileFieldsShareResponsiveAlignmentContract(): void
    {
        $script = file_get_contents(ROOTPATH . 'public/assets/js/doctor/patient_location.js');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/style.css');
        self::assertStringContainsString('country-code-field', $script);
        self::assertStringContainsString('mobile-number-field', $script);
        self::assertStringContainsString('contact-number-row', $script);
        self::assertStringContainsString('form-row contact-number-row', $script);
        self::assertStringContainsString("personalRow.after(fields(prefix))", $script);
        self::assertStringContainsString('contact-number-row > .country-code-field', $styles);
        self::assertStringContainsString('max-width: 100%', $styles);
        self::assertStringContainsString('.validation-error:not(:empty)', $styles);
        self::assertStringContainsString('contact-number-input-group', $script);
        self::assertStringContainsString('input-group-prepend country-code-field', $script);
        self::assertStringContainsString('col-md-4 col-12', $script);
        self::assertStringContainsString('.doctor-patient-form-modal .modal-body', $styles);
        self::assertStringContainsString('row-gap: .6rem', $styles);
        self::assertStringContainsString('padding: .75rem 1rem', $styles);
        self::assertStringContainsString('flex: 0 0 60px', $styles);
        self::assertStringContainsString('max-width: 60px', $styles);
        self::assertStringContainsString('flex-wrap: nowrap', $styles);
        self::assertStringContainsString('width: 1%', $styles);
        self::assertStringContainsString('border-radius: 6px 0 0 6px', $styles);
        self::assertStringContainsString('border-radius: 0 6px 6px 0', $styles);
        self::assertStringNotContainsString('Select a country or enter a valid code.', $script);
        $common = file_get_contents(ROOTPATH . 'public/assets/js/common.js');
        self::assertStringContainsString("$(document).off('change.countryCode', '.country-code-manual-toggle')", $common);
        self::assertStringContainsString("selectDisplay.toggleClass('d-none', manual)", $common);
        self::assertStringContainsString("input.toggleClass('d-none', ! manual)", $common);
        self::assertStringContainsString("if (selectDisplay.hasClass('bootstrap-select'))", $common);
        self::assertStringContainsString('country-code-manual-input d-none', $script);
        self::assertStringContainsString('country-code-manual-toggle', $script);
        self::assertStringContainsString('name="country_code_manual" value="1"', $script);
        self::assertStringContainsString('country-code-manual-input', $styles);
        self::assertStringContainsString('.contact-number-meta .country-code-manual-input', $styles);
        self::assertStringContainsString('width: 100%', $styles);
        $doctorController = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php');
        self::assertStringContainsString("preg_replace('/^\\+/', '', \$countryCode)", $doctorController);
        self::assertStringContainsString('preg_match(\'/^[1-9][0-9]{0,3}$/\', $normalizedCountryCode)', $doctorController);
        self::assertStringContainsString('$manualCountryCode = (string) $this->request->getPost(\'country_code_manual\') === \'1\'', $doctorController);
        self::assertStringContainsString('$manual = (string) $this->request->getPost(\'country_code_manual\') === \'1\'', $doctorController);
        self::assertStringContainsString("where('normalized_code', \$normalizedCode)", $doctorController);
        self::assertStringContainsString('telephone_country_name', $script);
        self::assertStringContainsString('data-name-input', $script);
        self::assertStringContainsString("manualFields.toggleClass('d-none', ! manual)", $common);
        self::assertStringContainsString("input.trigger('focus')", $common);
    }

    public function testPatientOrdersUseScopedListGridAndPatientSafeOrderContract(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Pages.php');
        $page = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/orders.php');
        $list = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/orders_list.php');
        $grid = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/orders_grid.php');

        self::assertStringContainsString("where('orders.patient_id', \$context['patient_id'])", $controller);
        self::assertStringContainsString("where('orders.clinic_id', \$context['clinic_id'])", $controller);
        self::assertStringContainsString("like('orders.order_number', \$search)", $controller);
        self::assertStringContainsString('search_items.medicine_name', $controller);
        self::assertStringContainsString("in_array(\$this->request->getGet('view'), ['list', 'grid']", $controller);
        self::assertStringContainsString('medicine_name, quantity', $controller);
        self::assertStringNotContainsString('inventory_batch_id', $controller);
        self::assertStringNotContainsString('batch_number', $page);
        self::assertStringNotContainsString('audit', $page);
        self::assertStringContainsString('pages/orders_list', $page);
        self::assertStringContainsString('pages/orders_grid', $page);
        self::assertStringContainsString('patient-order-detail-modal', $page);
        self::assertStringContainsString('patient-orders-table', $list);
        self::assertStringContainsString('More Actions', $list);
        self::assertStringContainsString('patient-orders-grid', $grid);
        self::assertStringContainsString('More Actions', $grid);
    }

    public function testPatientInvoicesUseScopedListGridAndReadOnlyPaymentContract(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Pages.php');
        $page = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/invoices.php');
        $list = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/invoices_list.php');
        $grid = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/invoices_grid.php');

        self::assertStringContainsString("where('invoices.patient_id', \$context['patient_id'])", $controller);
        self::assertStringContainsString("where('invoices.clinic_id', \$context['clinic_id'])", $controller);
        self::assertStringContainsString("like('invoices.invoice_number', \$search)", $controller);
        self::assertStringContainsString('search_items.description', $controller);
        self::assertStringContainsString("in_array(\$this->request->getGet('view'), ['list', 'grid']", $controller);
        self::assertStringContainsString('description, quantity, unit_price, line_total', $controller);
        self::assertStringNotContainsString('billing.payment.update', $page);
        self::assertStringNotContainsString('billing.refund', $page);
        self::assertStringNotContainsString('inventory_batch_id', $page);
        self::assertStringContainsString('pages/invoices_list', $page);
        self::assertStringContainsString('pages/invoices_grid', $page);
        self::assertStringContainsString('patient-invoice-detail-modal', $page);
        self::assertStringContainsString('Print invoice', $list);
        self::assertStringContainsString('More Actions', $list);
        self::assertStringContainsString('patient-invoices-grid', $grid);
        self::assertStringContainsString('More Actions', $grid);
    }

    public function testPatientNotificationsUseScopedInboxAndRelatedRecordAuthorization(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Notification.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Patient/Config/Routes.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/notifications.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-01-190000_AddCrossModuleOrderIntegrationFields.php');

        self::assertStringContainsString("where('patient_id', \$context['patient_id'])", $controller);
        self::assertStringContainsString("where('clinic_id', \$context['clinic_id'])", $controller);
        self::assertStringContainsString('related_orders.order_number', $controller);
        self::assertStringContainsString('related_invoices.invoice_number', $controller);
        self::assertStringContainsString('related_prescriptions.prescription_number', $controller);
        self::assertStringContainsString('function unreadCount()', $controller);
        self::assertStringContainsString('function readAll()', $controller);
        self::assertStringContainsString('function relatedUrl(', $controller);
        self::assertStringContainsString("notifications/unread-count", $routes);
        self::assertStringContainsString("notifications/read-all", $routes);
        self::assertStringContainsString('patient-notification-detail-modal', $view);
        self::assertStringContainsString('patient-notification-read', $view);
        self::assertStringContainsString('patient-mark-all-read', $view);
        self::assertStringContainsString('csrf_token()', $view);
        self::assertStringContainsString('event_key', $migration);
        self::assertStringContainsString('patient_notifications_event_key_unique', $migration);
        self::assertStringNotContainsString('pharmacy_notifications', $view);
        self::assertStringNotContainsString('inventory_batch_id', $view);
    }

    public function testPatientVisitsUseScopedListGridAndPatientSafeClinicalContract(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Pages.php');
        $page = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/visits.php');
        $list = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/visits_list.php');
        $grid = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/visits_grid.php');

        self::assertStringContainsString("where('consultations.patient_id', \$context['patient_id'])", $controller);
        self::assertStringContainsString("where('consultations.clinic_id', \$context['clinic_id'])", $controller);
        self::assertStringContainsString("like('consultations.consultation_number', \$search)", $controller);
        self::assertStringContainsString("orLike('consultations.diagnosis', \$search)", $controller);
        self::assertStringContainsString('follow_up_date_display', $controller);
        self::assertStringContainsString('diagnosis_summary', $controller);
        self::assertStringNotContainsString('clinical_notes', $page);
        self::assertStringNotContainsString('treatment_plan', $page);
        self::assertStringNotContainsString('inventory_batch_id', $page);
        self::assertStringContainsString('pages/visits_list', $page);
        self::assertStringContainsString('pages/visits_grid', $page);
        self::assertStringContainsString('patient-visit-detail-modal', $page);
        self::assertStringContainsString('patient-visits-table', $list);
        self::assertStringContainsString('More Actions', $list);
        self::assertStringContainsString('patient-visits-grid', $grid);
        self::assertStringContainsString('More Actions', $grid);
    }

    public function testPatientDashboardUsesScopedSafeSectionsAndVisitSummary(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Patient/Controllers/Dashboard.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/dashboard.php');

        self::assertStringContainsString("where('appointments.patient_id', \$patientId)", $controller);
        self::assertStringContainsString("where('prescriptions.patient_id', \$patientId)", $controller);
        self::assertStringContainsString("where('orders.patient_id', \$patientId)", $controller);
        self::assertStringContainsString("where('invoices.patient_id', \$patientId)", $controller);
        self::assertStringContainsString("where('visits.patient_id', \$patientId)", $controller);
        self::assertStringContainsString("where('patient_id', \$patientId)", $controller);
        self::assertStringContainsString("'visits' => \$visits", $controller);
        self::assertStringContainsString("'visits' => \$visitCount", $controller);
        self::assertStringContainsString('patient-visits-title', $view);
        self::assertStringContainsString('Upcoming appointments', $view);
        self::assertStringContainsString('Recent prescriptions', $view);
        self::assertStringContainsString('Recent medicine orders', $view);
        self::assertStringContainsString('Recent invoices', $view);
        self::assertStringContainsString('Notifications', $view);
        self::assertStringNotContainsString('clinical_notes', $view);
        self::assertStringNotContainsString('inventory_batch_id', $view);
        self::assertStringNotContainsString('audit_logs', $view);
        self::assertStringNotContainsString('->first(', $controller);
    }
}
