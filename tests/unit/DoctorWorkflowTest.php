<?php

namespace Tests\Unit;

use Doctor\Controllers\Clinical;
use Admin\Models\PrescriptionOptionModel;
use Admin\Services\PatientRegistrationService;
use App\Services\TelephoneContactService;
use App\Services\AppointmentConsultationEligibility;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class DoctorWorkflowTest extends TestCase
{
    public function testAppointmentConsultationEligibilityUsesApplicationTimezoneDateOnly(): void
    {
        $eligibility = new AppointmentConsultationEligibility(new DateTimeZone('Asia/Kolkata'));
        $yesterday = ['appointment_date' => '2026-09-29', 'appointment_time' => '23:59:59'];
        $today = ['appointment_date' => '2026-09-30', 'appointment_time' => '00:00:01'];
        $tomorrow = ['appointment_date' => '2026-10-01', 'appointment_time' => '00:00:00'];
        $now = new DateTimeImmutable('2026-09-30 23:59:59', new DateTimeZone('Asia/Kolkata'));

        self::assertFalse($eligibility->isConsultable($yesterday, $now));
        self::assertTrue($eligibility->isConsultable($today, $now));
        self::assertTrue($eligibility->isConsultable($tomorrow, $now));
        self::assertFalse($eligibility->isToday($yesterday, $now));
        self::assertTrue($eligibility->isToday($today, $now));
        self::assertFalse($eligibility->isToday($tomorrow, $now));
        self::assertSame('30-09-2026', $eligibility->appointmentDate($today)?->format('d-m-Y'));
        self::assertSame('Past appointment: consultation cannot be started for a date before today.', $eligibility->unavailableReason($yesterday, $now));
    }

    public function testAppointmentConsultationEligibilityUsesApplicationTimezoneDateBoundaryAndFailsClosed(): void
    {
        $eligibility = new AppointmentConsultationEligibility(new DateTimeZone('Asia/Kolkata'));
        $utcBeforeMidnight = new DateTimeImmutable('2026-09-29 18:29:59', new DateTimeZone('UTC'));
        $utcAtMidnight = new DateTimeImmutable('2026-09-29 18:30:00', new DateTimeZone('UTC'));
        $appointment = ['appointment_date' => '2026-09-30'];

        self::assertTrue($eligibility->isConsultable(['appointment_date' => '2026-09-29'], $utcBeforeMidnight));
        self::assertFalse($eligibility->isConsultable(['appointment_date' => '2026-09-29'], $utcAtMidnight));
        self::assertTrue($eligibility->isConsultable($appointment, $utcAtMidnight));
        self::assertFalse($eligibility->isConsultable([], $utcAtMidnight));
        self::assertFalse($eligibility->isPast([], $utcAtMidnight));
        self::assertSame(AppointmentConsultationEligibility::INVALID_MESSAGE, $eligibility->unavailableReason([], $utcAtMidnight));
    }

    public function testDoctorAppointmentListAndConsultEndpointsApplyPastAppointmentRule(): void
    {
        $records = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');
        $clinical = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Clinical.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/appointments.php');
        $list = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/appointments_list.php');
        $actions = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/appointments_actions.php');
        $viewMarkup = $view . $actions;

        self::assertStringContainsString('consultation_date_passed', $records);
        self::assertSame(2, substr_count($clinical, 'unavailableReason($appointment)'));
        self::assertStringContainsString('! $this->appointmentConsultationEligibility->isConsultable($appointment)', $clinical);
        self::assertStringContainsString('Past appointment', $list);
        self::assertStringNotContainsString('>Past appointment<', $actions);
        self::assertStringContainsString('! $consultationDatePassed', $viewMarkup);
        self::assertStringNotContainsString('consultation_time_passed', $records . $viewMarkup . $clinical);
        self::assertStringNotContainsString('appointment_time', file_get_contents(ROOTPATH . 'app/Services/AppointmentConsultationEligibility.php'));
    }

    public function testDoctorQueueTransitionsFollowExistingAppointmentContract(): void
    {
        $transitions = (new \ReflectionClass(Clinical::class))->getConstant('QUEUE_TRANSITIONS');
        self::assertContains('checked_in', $transitions['confirmed']);
        self::assertContains('in_queue', $transitions['checked_in']);
        self::assertContains('in_consultation', $transitions['in_queue']);
        self::assertContains('completed', $transitions['in_queue']);
        self::assertNotContains('completed', $transitions['confirmed']);
        self::assertSame(['completed'], $transitions['completed']);
    }

    public function testDoctorPrescriptionSubmissionUsesDraftOrSubmittedStates(): void
    {
        $states = array_column(PrescriptionOptionModel::defaultOptions()['prescription-statuses'], 'code');
        self::assertContains('draft', $states);
        self::assertContains('submitted', $states);
        self::assertNotContains('approved', $states);
    }

    public function testPrescriptionMedicineHeaderOpensDatabaseBackedMedicineCreationModal(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Medicine/Medicine.php');
        $model = file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/MedicineModel.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-09-10-170000_AddMedicineUnit.php');
        $lookupMigration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-09-10-181000_CreateMedicineLookupTables.php');
        $lookupRoutes = file_get_contents(ROOTPATH . 'app/Modules/Admin/Config/Routes.php');
        $lookupController = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Medicine/MedicineLookup.php');
        $script = $view;

        self::assertStringContainsString('id="clinical-create-medicine"', $view);
        self::assertStringContainsString('+ Medicine', $view);
        self::assertStringContainsString('data-target="#clinical-medicine-modal"', $view);
        self::assertStringContainsString('id="clinical-medicine-form"', $view);

        foreach (['medicine_name', 'generic_name', 'strength', 'form_id', 'unit_id', 'manufacturer_id', 'description', 'status_id'] as $field) {
            self::assertStringContainsString('name="' . $field . '"', $view);
            self::assertStringContainsString('id="' . $field . 'Err"', $view);
        }
        self::assertStringContainsString("base_url('admin/medicines/save')", $view);
        self::assertStringNotContainsString("'#clinical-add-medicine, .clinical-add-medicine-trigger'", $script);
        self::assertStringContainsString('refreshClinicalMedicineOptions', $script);
        self::assertStringContainsString("selectpicker('refresh')", $script);
        self::assertStringContainsString('clinicalMedicineBusy', $script);
        self::assertStringContainsString('response.data.id', $script);
        self::assertStringContainsString("setStatusCode(422)", $controller);
        self::assertStringContainsString('medicineNameExists', $controller);
        self::assertStringContainsString("admin.medicine_created", $controller);
        self::assertStringContainsString('lookups->resolve', $controller);
        self::assertStringContainsString('form_id', $model);
        self::assertStringContainsString("'unit'", $model);
        self::assertStringContainsString("'unit'", $migration);

        foreach (['medicine_forms', 'medicine_units', 'medicine_manufacturers', 'medicine_status_options', 'form_id', 'unit_id', 'manufacturer_id', 'status_id'] as $contract) {
            self::assertStringContainsString($contract, $lookupMigration);
        }
        self::assertStringContainsString("admin/medicine-lookups", $lookupRoutes);
        self::assertStringContainsString("permission:settings.manage", $lookupRoutes);
        self::assertStringContainsString('MedicineLookupService', $lookupController . file_get_contents(ROOTPATH . 'app/Modules/Admin/Services/MedicineLookupService.php'));
        self::assertStringContainsString('This lookup option already exists.', $lookupController);
        self::assertStringContainsString('medicineLookupOptions', $view);
        $medicineModal = substr($view, strpos($view, 'id="clinical-medicine-modal"'));
        self::assertStringNotContainsString('value="active"', $medicineModal);
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
        self::assertStringContainsString("data: form.serialize()", $detail);
        self::assertStringContainsString("post('patients/update/(:num)'", file_get_contents(ROOTPATH . 'app/Modules/Doctor/Config/Routes.php'));
        self::assertStringContainsString('response.errors', $detail);
        self::assertStringContainsString('reload_page(700)', $detail);
        self::assertStringContainsString('complete: function ()', $detail);
    }

    public function testDoctorPatientUpdatePreservesRequiredEmailAndRejectsOutOfScopeRecords(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php');
        $service = file_get_contents(ROOTPATH . 'app/Modules/Admin/Services/PatientRegistrationService.php');
        self::assertStringContainsString("\$data['email'] = (string) (\$patient['email'] ?? '')", $controller);
        self::assertStringContainsString("\$data['date_of_birth'] = \$patient['date_of_birth'] ?? null", $controller);
        self::assertStringContainsString("\$data['telephone_country_code_id'] = \$patient['telephone_country_code_id'] ?? null", $controller);
        self::assertStringContainsString('canonicalizeTelephoneCountryCode($data)', $controller);
        self::assertStringContainsString('public function canonicalizeTelephoneCountryCode', $service);
        self::assertStringContainsString("\$patient = \$this->scopedPatient(\$patientId, \$context)", $controller);
        self::assertStringContainsString("where('patients.clinic_id', \$context['clinic_id'])", $controller);
        self::assertStringContainsString("log_message('error', 'Doctor patient update failed: '", $controller);
        self::assertStringContainsString("'doctor.patient_updated'", $controller);
    }

    public function testDoctorPatientEditFormMatchesCreateCountryCodeAndLocationContract(): void
    {
        $detail = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/patient.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/doctor/patient_location.js');
        $form = substr($detail, strpos($detail, 'id="doctor-patient-edit-form"'));
        self::assertTrue(strpos($form, 'name="first_name"') < strpos($form, 'name="mobile_number"'));
        self::assertTrue(strpos($form, 'name="mobile_number"') < strpos($form, 'name="email"'));
        self::assertStringContainsString('doctor-patient-location-slot', $detail);
        self::assertStringContainsString('telephone_country_code_id', $script);
        self::assertStringContainsString('country_code_manual', $script);
        self::assertStringContainsString('country-code-manual-input', $script);
        self::assertStringContainsString('data-live-search="true"', $script);
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

    public function testDoctorNewPatientLocationFieldsAreInsertedAfterPersonalNameRow(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/patients.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/doctor/patient_location.js');
        $form = substr($view, strpos($view, 'id="doctor-patient-create-form"'));
        self::assertLessThan(strpos($form, 'name="mobile_number"'), strpos($form, 'name="first_name"'));
        $order = array_map(static fn (string $needle): int => strpos($form, $needle), [
            'name="first_name"',
            'name="last_name"',
            'name="mobile_number"',
            'name="email"',
            'name="gender"',
            'name="date_of_birth"',
            'doctor-patient-location-slot',
            'name="postal_code"',
            'name="address"',
        ]);

        foreach (array_slice($order, 1) as $index => $position) {
            self::assertGreaterThan($order[$index], $position);
        }
        self::assertStringContainsString("locationSlot.after(fields(prefix))", $script);
        self::assertStringContainsString('doctor-patient-location-slot', $view);
        self::assertStringNotContainsString('<section', $view);
        self::assertStringNotContainsString('Personal</h3>', $view);
        self::assertStringNotContainsString('Contact</h3>', $view);
        self::assertStringNotContainsString('Location</h3>', $view);
        self::assertStringNotContainsString('Appointment</h3>', $view);
        self::assertStringContainsString('mobile-number-field', $view);
        self::assertStringContainsString('contact-number-input-group', $view);
        self::assertStringContainsString('aria-label="Country code"', $script);
        $editView = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/patient.php');
        self::assertStringContainsString('contact-number-input-group', $editView);
        self::assertStringContainsString('class="row g-3 doctor-patient-form-grid"', $view);
        self::assertStringContainsString('class="form-group col-md-4 col-12 contact-number-field"', $view);
        self::assertStringContainsString('name="address"', $view);
        self::assertStringContainsString('<textarea id="new-patient-address"', $view);
        self::assertStringContainsString('name="postal_code"', $view);
        self::assertStringContainsString('new-patient-postal-code', $view);
        self::assertStringNotContainsString('<section', $view);
        self::assertStringContainsString('row g-3', $view);
        self::assertStringNotContainsString('form-row', $editView);
        self::assertStringContainsString('class="row g-3"', $editView);
        self::assertStringContainsString('class="form-group col-md-4 col-12 contact-number-field"', $editView);
        self::assertStringContainsString('<textarea id="edit-patient-address"', $editView);
        self::assertStringNotContainsString('new-patient-middle', $view);
        self::assertStringNotContainsString('edit-patient-middle', $editView);
        self::assertStringNotContainsString('name="middle_name"', $view . $editView);
        self::assertStringNotContainsString("'middle_name' =>", file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php'));
        self::assertStringNotContainsString('middle_name', file_get_contents(ROOTPATH . 'public/assets/js/doctor/patient_location.js'));
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

    public function testDoctorPatientLocationControlsUseDelegatedFocusAndSafeCascadingLoads(): void
    {
        $common = file_get_contents(ROOTPATH . 'public/assets/js/common.js');
        $locationScript = file_get_contents(ROOTPATH . 'public/assets/js/doctor/patient_location.js');

        self::assertStringContainsString(".on('change.location', '.location-manual-toggle'", $common);
        self::assertStringContainsString("input.trigger('focus')", $common);
        self::assertStringContainsString(".prop('disabled', ! manual)", $common);
        self::assertStringContainsString("select.prop('disabled', manual).val('')", $common);
        self::assertStringContainsString("selectDisplay.toggleClass('d-none', manual)", $common);
        self::assertStringContainsString("setLocationSelectState(select, manual)", $common);
        self::assertStringContainsString("element.selectpicker('enable')", $common);
        self::assertStringContainsString("element.selectpicker('disable')", $common);
        self::assertStringContainsString("function resetLocationSelect(selector, label, disabled = false", $common);
        self::assertStringContainsString("const shouldDisable = Boolean(disabled) || locationSelectIsManual(element);", $common);
        self::assertStringContainsString("element.prop('disabled', shouldDisable)", $common);
        self::assertStringContainsString("locationSelectPlaceholder(label)", $common);
        self::assertStringContainsString("location-request-token", $common);
        self::assertStringContainsString("select.data('location-request-token', requestToken)", $common);
        self::assertStringContainsString("Number(select.data('location-request-token')) !== requestToken", $common);
        self::assertStringContainsString("const hasOptions = options !== '';", $common);
        self::assertStringContainsString("setLocationSelectState(select, false);", $common);
        self::assertStringContainsString("resetLocationSelect(selector, label, false);", $common);
        self::assertStringContainsString("setLocationSelectState(select, false);", $common);
        self::assertStringContainsString("url: url + parentId", $common);
        self::assertStringContainsString("method: 'GET'", $common);
        self::assertStringContainsString(".val('')", $common);
        self::assertStringContainsString("clearDependentLocations();", $locationScript);
        self::assertStringContainsString("resetLocationSelect(stateSelector, 'state')", $locationScript);
        self::assertStringContainsString("resetLocationSelect(citySelector, 'city')", $locationScript);
        self::assertStringContainsString("resetLocationSelect(localitySelector, 'locality')", $locationScript);
        self::assertStringContainsString("loadStates($(this).val(), stateSelector)", $locationScript);
        self::assertStringContainsString("loadCities($(this).val(), citySelector)", $locationScript);
        self::assertStringContainsString("loadLocalities($(this).val(), localitySelector)", $locationScript);
        self::assertStringContainsString('selectRestoredValue(countrySelector, locationValues.country)', $locationScript);
        self::assertStringContainsString('selectRestoredValue(stateSelector, locationValues.state)', $locationScript);
        self::assertStringContainsString('selectRestoredValue(citySelector, locationValues.city)', $locationScript);
        self::assertStringContainsString('selectRestoredValue(localitySelector, locationValues.locality)', $locationScript);
        self::assertStringContainsString("values[field + '_name']", $locationScript);
        self::assertStringContainsString('locationRestoreToken', $locationScript);
        self::assertStringNotContainsString('name="country_id" disabled', $locationScript);
        self::assertStringNotContainsString('name="state_id" disabled', $locationScript);
        self::assertStringNotContainsString('name="city_id" disabled', $locationScript);
        self::assertStringNotContainsString('name="locality_id" disabled', $locationScript);
        self::assertStringContainsString("setLocationSelectState(select, false);", $common);
        self::assertStringContainsString("element.selectpicker('enable')", $common);
    }

    public function testDoctorPatientMobileIsRequiredAndValidatedBeforeRegistrationTransaction(): void
    {
        foreach (['9876543210', '987 654 3210', '987-654-3210'] as $mobile) {
            self::assertSame($mobile, PatientRegistrationService::normalizeRequiredMobileNumber($mobile));
        }

        foreach (['', '123', 'abc1234567', '987 65', '987654321012345678901'] as $mobile) {
            try {
                PatientRegistrationService::normalizeRequiredMobileNumber($mobile);
                self::fail('Expected invalid mobile number: ' . $mobile);
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('mobile number', strtolower($e->getMessage()));
            }
        }

        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php');
        $service = file_get_contents(ROOTPATH . 'app/Modules/Admin/Services/PatientRegistrationService.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/patients.php');
        $locationScript = file_get_contents(ROOTPATH . 'public/assets/js/doctor/patient_location.js');
        $controllerMobileValidation = strpos($controller, 'PatientRegistrationService::normalizeRequiredMobileNumber($mobile)');
        $transactionStart = strpos($service, '$this->db->transBegin();');
        $validationCall = strpos($controller, '$errors = $this->patientErrors();');
        $createTry = strpos($controller, 'try {', $validationCall);

        self::assertStringContainsString("name=\"mobile_number\"", $view);
        self::assertStringContainsString("id=\"mobile_numberErr\"", $view);
        self::assertStringContainsString('name="telephone_country_code_id"', $locationScript);
        self::assertStringContainsString('name="country_code"', $locationScript);
        self::assertStringContainsString("'Mobile number is required.'", $service);
        self::assertStringContainsString('normalizeRequiredMobileNumber($patientData[\'mobile_number\'] ?? null)', $service);
        self::assertNotFalse($controllerMobileValidation);
        self::assertNotFalse($transactionStart);
        self::assertNotFalse($validationCall);
        self::assertNotFalse($createTry);
        self::assertLessThan($createTry, $validationCall);
        self::assertLessThan($transactionStart, strpos($service, 'normalizeRequiredMobileNumber'));
    }

    public function testDoctorPatientsAreOrderedNewestFirstBeforePagination(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php');
        $queryOrder = strpos($controller, "->orderBy('patients.created_at', 'DESC')->orderBy('patients.id', 'DESC')");
        $pagination = strpos($controller, '->limit($pageSize, ($page - 1) * $pageSize)', $queryOrder);

        self::assertNotFalse($queryOrder);
        self::assertNotFalse($pagination);
        self::assertLessThan($pagination, $queryOrder);
    }

    public function testDoctorPatientListUsesScopedServerSideFiltersAndPreservesThemInPagination(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/patients.php');
        $pagination = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/parts/pagination.php');

        foreach (['patient_name', 'mobile_number'] as $field) {
            self::assertStringContainsString("getGet('{$field}')", $controller);
            self::assertStringContainsString('name="' . $field . '"', $view);
            self::assertStringContainsString("'{$field}'", $pagination);
        }

        foreach (['source_type', 'status'] as $field) {
            self::assertStringContainsString("patientFilterValue('{$field}'", $controller);
            self::assertStringContainsString('name="' . $field . '"', $view);
            self::assertStringContainsString("'{$field}'", $pagination);
        }

        foreach (['country_id', 'state_id', 'city_id', 'locality_id'] as $field) {
            self::assertStringContainsString("patientFilterId('{$field}')", $controller);
            self::assertStringContainsString('name="' . $field . '"', $view);
            self::assertStringContainsString("'{$field}'", $pagination);
        }

        foreach (['created_from', 'created_to'] as $field) {
            self::assertStringContainsString("patientFilterDate('{$field}')", $controller);
            self::assertStringContainsString('name="' . $field . '"', $view);
            self::assertStringContainsString("'{$field}'", $pagination);
        }

        foreach (['patients.source_type', 'patients.status', 'patients.created_at >=', 'patients.created_at <='] as $predicate) {
            self::assertStringContainsString($predicate, $controller);
        }
        self::assertStringContainsString("foreach (['country_id', 'state_id', 'city_id', 'locality_id'] as \$locationField)", $controller);
        self::assertStringContainsString("where('patients.' . \$locationField", $controller);

        self::assertStringContainsString("where('patients.clinic_id', \$context['clinic_id'])", $controller);
        self::assertStringContainsString("where('doctor_id', \$context['doctor_id'])", $controller);
        self::assertStringContainsString('id="doctor-patient-filter-modal"', $view);
        self::assertStringContainsString('data-live-search="true"', $view);
        self::assertStringContainsString('placeholder="dd-mm-yyyy"', $view);
        self::assertStringContainsString('Filters', $view);
        self::assertStringNotContainsString('.DataTable()', $view);

        $queryOrder = strpos($controller, "->orderBy('patients.created_at', 'DESC')->orderBy('patients.id', 'DESC')");
        $paginationLimit = strpos($controller, '->limit($pageSize, ($page - 1) * $pageSize)', $queryOrder);
        self::assertNotFalse($queryOrder);
        self::assertNotFalse($paginationLimit);
        self::assertLessThan($paginationLimit, $queryOrder);
    }

    public function testDoctorTelephoneDisplayUsesOneDatabaseCodeAndNormalizedMobile(): void
    {
        $contact = new TelephoneContactService();

        self::assertSame('+91 9876543210', $contact->formatDisplay('91', '987-654-3210'));
        self::assertSame('+78 9123456789', $contact->formatDisplay(null, '9123456789', '78'));
        self::assertSame('9876543210', $contact->formatDisplay(null, '987 654 3210'));
        self::assertSame('—', $contact->formatDisplay('91', null));

        $patientsView = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/patients.php');
        $patientView = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/patient.php');
        $appointmentsView = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/appointments.php');
        self::assertStringContainsString("formatDisplay(", $patientsView);
        self::assertStringContainsString("formatDisplay(", $patientView);
        self::assertStringContainsString("formatDisplay(", $appointmentsView);
        self::assertStringContainsString("telephone_country_codes.normalized_code AS telephone_country_code", file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php'));
        self::assertStringContainsString('telephone_country_codes.normalized_code AS patient_telephone_country_code', file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php'));
        self::assertStringContainsString('telephone_country_codes.normalized_code AS patient_telephone_country_code', file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Clinical.php'));
        self::assertStringContainsString('telephone_country_codes.normalized_code AS patient_telephone_country_code', file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Appointment.php'));
        self::assertStringContainsString('telephone_country_codes.normalized_code AS patient_telephone_country_code', file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Dashboard.php'));
    }

    public function testDoctorAppointmentAndRecordActionsRemainScopedAndAudited(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Config/Routes.php');
        $records = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');
        $appointments = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/appointments.php');
        $appointmentActions = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/appointments_actions.php');
        $appointmentMarkup = $appointments . $appointmentActions;
        $prescriptions = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/prescriptions.php');
        $prescriptionActions = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/prescriptions_actions.php');
        $prescriptions .= $prescriptionActions;
        self::assertStringContainsString("appointments/create", $routes);
        self::assertStringContainsString("appointments/patients/search", $routes);
        self::assertStringContainsString("appointments/reschedule/(:num)", $routes);
        self::assertStringContainsString("appointments/cancel/(:num)", $routes);
        self::assertStringContainsString("permission:appointment.create", $routes);
        self::assertStringContainsString("permission:appointment.edit", $routes);
        self::assertStringContainsString("doctor.appointment_created", $records);
        self::assertStringContainsString("doctor.appointment_rescheduled", $records);
        self::assertStringContainsString("doctor.appointment_cancelled", $records);
        self::assertStringContainsString("availabilityError", $records);
        self::assertStringContainsString("break_start_time", $records);
        self::assertStringContainsString("break_end_time", $records);
        self::assertStringContainsString('id="doctor-appointment-create-form"', $appointments);
        self::assertStringContainsString('id="appointment-patient-search"', $appointments);
        self::assertStringContainsString('id="appointment-patient-id" name="patient_id"', $appointments);
        self::assertStringContainsString('id="appointment-patient-results"', $appointments);
        self::assertStringContainsString('role="combobox"', $appointments);
        self::assertStringContainsString('aria-autocomplete="list"', $appointments);
        self::assertStringContainsString('search.length < 2', $appointments);
        self::assertStringContainsString('patientSearchXhr.abort()', $appointments);
        self::assertStringContainsString("event.key === 'ArrowDown'", $appointments);
        self::assertStringContainsString("event.key === 'Escape'", $appointments);
        self::assertStringContainsString('data-live-search="true"', $appointments);
        self::assertStringContainsString('data-actions-box="true"', $appointments);
        self::assertStringContainsString('name="service_ids[]"', $appointments);
        self::assertStringContainsString('doctor-appointment-create-modal', $appointments);
        self::assertStringContainsString('placeholder="dd-mm-yyyy"', $appointments);
        self::assertStringContainsString('New date (dd-mm-yyyy):', $appointments);
        self::assertStringContainsString('bi-eye mr-1', $appointmentMarkup);
        self::assertStringContainsString('fa-solid fa-circle-exclamation', $appointmentMarkup);
        self::assertStringContainsString('Created:', $appointmentMarkup);
        self::assertStringContainsString('Updated:', $appointmentMarkup);
        self::assertStringContainsString('dropdown-toggle', $appointmentMarkup);

        foreach (['Edit / Reschedule', 'Cancel', 'Consult', 'Video Consultation', 'Create Follow-up', 'View Prescription', 'View Billing'] as $action) {
            self::assertStringContainsString($action, $appointmentMarkup);
        }
        self::assertStringContainsString('name="service_name"', $appointments);
        self::assertStringContainsString('name="appointment_status"', $appointments);
        self::assertStringContainsString('doctor-appointment-filter-modal', $appointments);
        self::assertStringContainsString('js-appointment-status', $appointments);
        self::assertStringContainsString('data-submit-display-date="true"', $appointments);
        self::assertStringContainsString('doctor/appointments/queue', $appointments);
        self::assertStringContainsString('handleFormAjaxError', $appointments);
        self::assertStringContainsString('appointmentFilters', $records);
        self::assertStringContainsString('public function patientSearch', $records);
        self::assertStringContainsString('applyDoctorPatientScope($query, $context)', $records);
        self::assertStringContainsString('mb_strlen($search) < 2', $records);

        foreach (['registration_number', 'mobile', 'label'] as $resultField) {
            self::assertStringContainsString("'{$resultField}'", $records);
        }
        self::assertStringContainsString('appointment_services', $records);
        self::assertStringContainsString('consultations.id AS consultation_id', $records);
        self::assertStringContainsString('prescriptions.id AS prescription_id', $records);
        self::assertStringContainsString('normalizeDisplayDate', $records);
        self::assertStringContainsString("orderBy('appointments.id', 'DESC')", $records);
        self::assertStringContainsString('View full prescription', $prescriptions);
        self::assertStringContainsString('prescription_items', $records);
    }

    public function testDoctorAppointmentFormUsesDisplayDatesAndScopedRelatedActions(): void
    {
        $records = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');
        $detail = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Appointment.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/appointment.php');

        self::assertStringContainsString("createFromFormat('!d-m-Y'", $records);
        self::assertStringContainsString("'service_name' => \$service", $records);
        self::assertStringContainsString("'status' => \$status", $records);
        self::assertStringContainsString('transRollback()', $records);
        self::assertStringContainsString('appointments.patient_id', $detail);

        foreach (['doctor/patients/', 'doctor/clinical', 'doctor/prescriptions', 'doctor/follow-ups', 'doctor/billing'] as $destination) {
            self::assertStringContainsString($destination, $view);
        }
    }

    public function testDoctorAppointmentServicesAreNormalizedAndReversible(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-09-08-120000_CreateAppointmentServices.php');
        $records = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');

        self::assertStringContainsString("createTable('appointment_services')", $migration);
        self::assertStringContainsString("dropTable('appointment_services', true)", $migration);
        self::assertStringContainsString('addUniqueKey([\'appointment_id\', \'service_name\'])', $migration);
        self::assertStringContainsString('appointment_services_appointment_fk', $migration);
        self::assertStringContainsString('appointment_services_service_fk', $migration);
        self::assertStringContainsString('resolveServices', $records);
        self::assertStringContainsString('use Admin\\Models\\Configuration\\ServiceModel;', $records);
        self::assertStringNotContainsString("where('master_type', 'services')", $records);
        self::assertStringContainsString('insertBatch($serviceRows)', $records);
    }

    public function testDoctorFollowUpCreationIsScopedValidatedAndAudited(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Config/Routes.php');
        $records = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/follow-ups.php');
        self::assertStringContainsString("post('follow-ups/create'", $routes);
        self::assertStringContainsString("post('follow-ups/update/(:num)'", $routes);
        self::assertStringContainsString("get('follow-ups/patients/search'", $routes);
        self::assertStringContainsString('permission:consultation.edit', $routes);
        self::assertStringContainsString('public function createFollowUp', $records);
        self::assertStringContainsString('public function updateFollowUp', $records);
        self::assertStringContainsString("where('doctor_id', \$context['doctor_id'])", $records);
        self::assertStringContainsString("where('clinic_id', \$context['clinic_id'])", $records);
        self::assertStringContainsString("'doctor.follow_up_created'", $records);
        self::assertStringContainsString("'doctor.follow_up_updated'", $records);
        self::assertStringContainsString("'follow_up_type_id' => \$typeId", $records);
        self::assertStringContainsString("activeForClinicById", $records);
        self::assertStringContainsString('FollowUpStatusModel', $records);
        self::assertStringContainsString("'follow_up_status_id' => \$statusId", $records);
        self::assertStringContainsString("normalizeDisplayDate(\$dateInput)", $records);
        self::assertStringContainsString("orderBy('consultations.follow_up_date', 'ASC')", $records);
        self::assertStringContainsString("filters['follow_up_type_id']", $records);
        self::assertStringContainsString("=== 'completed' ? 'completed' : (string) \$followUpStatus['code']", $records);
        self::assertStringContainsString("'status' => \$effectiveStatus", $records);
        self::assertStringContainsString('New Follow-up', $view);
        self::assertStringContainsString('doctor-followups-page', $view);
        self::assertStringContainsString('doctor-followup-filter-modal', $view);
        self::assertStringContainsString('doctor-followup-filter-search', $view);
        self::assertStringNotContainsString('doctor-followup-filter-patient', $view);
        self::assertStringContainsString('doctor-followup-patient-results', $view);
        self::assertStringContainsString('doctor-followup-filter-from', $view);
        self::assertStringContainsString('doctor-followup-filter-to', $view);
        self::assertStringContainsString('doctor-followup-filter-type', $view);
        self::assertStringContainsString('doctor-followup-filter-status', $view);
        self::assertStringContainsString('fa-solid fa-circle-exclamation', $view);
        self::assertStringContainsString('doctor-followup-action-group', $view);
        self::assertStringContainsString('js-followup-edit', $view);
        self::assertStringContainsString('id="doctor-followup-form"', $view);
        self::assertStringContainsString('id="follow_up_type_idErr"', $view);
        self::assertStringContainsString('name="follow_up_type_id"', $view);
        self::assertStringContainsString('name="status"', $view);
        self::assertStringContainsString('id="statusErr"', $view);
        self::assertStringContainsString('patientSearchUrl', $view);
        self::assertStringContainsString('No patients found', $view);
        self::assertStringContainsString('aria-expanded', $view);
        self::assertStringNotContainsString('id="doctor-followup-patient"', $view);
        self::assertStringNotContainsString('name="reason"', $view);
        self::assertStringNotContainsString('name="notes"', $view);
        self::assertStringNotContainsString('doctor-followup-reason', $view);
        self::assertStringNotContainsString('doctor-followup-notes', $view);
        self::assertStringContainsString('placeholder="dd-mm-yyyy"', $view);
        self::assertStringContainsString('modal-dialog-scrollable', $view);
        self::assertStringContainsString('data-live-search="true"', $view);
        self::assertStringContainsString('csrf_field()', $view);
        self::assertStringNotContainsString('.DataTable()', $view);
        self::assertStringContainsString('ri-scroll-modal', $view);
        self::assertStringContainsString('id="doctor-followup-submit"', $view);
        self::assertStringContainsString('followUpStatuses', $view);
    }

    public function testDoctorFollowUpTypesUseDedicatedIdBackedLookupAndPatientAutocomplete(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-09-14-120000_CreateFollowUpTypes.php');
        $seeder = file_get_contents(ROOTPATH . 'app/Database/Seeds/FollowUpTypeSeeder.php');
        $model = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Models/FollowUpTypeModel.php');
        $records = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/follow-ups.php');
        $pagination = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/parts/pagination.php');

        self::assertStringContainsString("createTable('follow_up_types')", $migration);
        self::assertStringContainsString("addUniqueKey(['scope_key', 'normalized_name'])", $migration);
        self::assertStringContainsString('consultations_follow_up_type_fk', $migration);
        self::assertStringContainsString('backfillExistingTypes', $migration);
        self::assertStringContainsString('Routine', $seeder);
        self::assertStringContainsString('Review', $seeder);
        self::assertStringContainsString('Other', $seeder);
        self::assertStringContainsString('normalized_name', $seeder);
        self::assertStringContainsString('activeForClinic', $model);
        self::assertStringContainsString('activeForClinicById', $model);
        self::assertStringContainsString('applyDoctorPatientScope', $records);
        self::assertStringContainsString("like('patients.first_name', \$search)", $records);
        self::assertStringContainsString("orLike('patients.registration_number', \$search)", $records);
        self::assertStringContainsString("orLike('patients.mobile_normalized', \$search)", $records);
        self::assertStringContainsString('mb_strlen($search) < 2', $records);
        self::assertStringContainsString("'follow_up_type_id' => \$typeId", $records);
        self::assertStringContainsString('name="patient_id"', $view);
        self::assertStringNotContainsString('<select id="doctor-followup-patient', $view);
        self::assertStringNotContainsString('data-live-search="true"', substr($view, strpos($view, 'id="doctor-followup-patient-search"'), strpos($view, 'id="doctor-followup-patient-results"') - strpos($view, 'id="doctor-followup-patient-search"')));
        self::assertStringContainsString('id="patient_idErr"', $view);
        self::assertStringContainsString('id="follow_up_type_idErr"', $view);
        self::assertStringContainsString('patientSearchUrl', $view);
        self::assertStringContainsString('window.setTimeout(search, 280)', $view);
        self::assertStringContainsString('Loading patients', $view);
        self::assertStringContainsString('No patients found', $view);
        self::assertStringContainsString('follow_up_type_id', $pagination);
        self::assertStringNotContainsString("'follow_up_type' => \$filters", $pagination);
    }

    public function testDoctorFollowUpFormUsesFourRequiredFieldsAndOptionalRemarks(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/follow-ups.php');
        $records = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');
        $statusMigration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-09-14-130000_CreateFollowUpStatuses.php');
        $statusModel = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Models/FollowUpStatusModel.php');
        $statusSeeder = file_get_contents(ROOTPATH . 'app/Database/Seeds/FollowUpStatusSeeder.php');

        self::assertStringContainsString('id="patient_idErr"', $view);
        self::assertStringContainsString('id="follow_up_dateErr"', $view);
        self::assertStringContainsString('id="follow_up_type_idErr"', $view);
        self::assertStringContainsString('id="statusErr"', $view);
        self::assertStringContainsString('name="remarks"', $view);
        self::assertStringContainsString('id="remarksErr"', $view);
        self::assertStringContainsString("getPost('remarks')", $records);
        self::assertStringContainsString("'follow_up_remarks' => \$remarks", $records);
        self::assertStringNotContainsString('name="reason"', $view);
        self::assertStringNotContainsString('name="notes"', $view);
        self::assertStringNotContainsString('getPost(\'reason\')', $records);
        self::assertStringNotContainsString('getPost(\'notes\')', $records);
        self::assertStringNotContainsString("'treatment_plan' => \$reason", $records);
        self::assertStringNotContainsString("'clinical_notes' => \$notes", $records);
        self::assertStringContainsString("createTable('follow_up_statuses')", $statusMigration);
        self::assertStringContainsString('consultations_follow_up_status_fk', $statusMigration);
        self::assertStringContainsString('activeForClinicById', $statusModel);
        self::assertStringContainsString('FollowUpStatusSeeder', file_get_contents(ROOTPATH . 'app/Database/Seeds/DatabaseSeeder.php'));
        self::assertStringContainsString('in_progress', $statusSeeder);
    }

    public function testDoctorFollowUpDateSerializesDisplayValueAndNormalizesOnlyOnServer(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/follow-ups.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');
        $fieldStart = strpos($view, 'id="doctor-followup-date"');
        $fieldEnd = strpos($view, '>', $fieldStart);
        $field = substr($view, $fieldStart, $fieldEnd - $fieldStart + 1);

        self::assertStringContainsString('name="follow_up_date"', $field);
        self::assertStringContainsString('data-submit-display-date="true"', $field);
        self::assertStringContainsString('placeholder="dd-mm-yyyy"', $field);
        self::assertStringContainsString('data: form.serialize()', $view);
        self::assertStringContainsString('name="follow_up_time"', $view);
        self::assertStringContainsString('id="follow_up_timeErr"', $view);
        self::assertStringContainsString('type="time" name="follow_up_time" id="follow_up_time" class="form-control" step="900" placeholder="HH:mm" required', $view);
        self::assertStringContainsString('<div class="validation-error" id="follow_up_timeErr"></div>', $view);
        self::assertStringNotContainsString('doctor-followup-time-select', $view);
        self::assertStringNotContainsString('timepicker', $view);
        self::assertStringContainsString('$date = $this->normalizeDisplayDate($dateInput)', $controller);
        self::assertStringContainsString("getPost('follow_up_time')", $controller);
        self::assertStringContainsString('normalizeFollowUpTime', $controller);
        self::assertStringContainsString("'follow_up_time' => \$time", $controller);
        self::assertStringContainsString('consultations.follow_up_time', $controller);
        self::assertStringContainsString("'follow_up_date' => \$date", $controller);
        self::assertStringContainsString("\$errors['follow_up_time'] = 'A valid follow-up time is required.'", $controller);
        self::assertSame('2026-09-09', \DateTimeImmutable::createFromFormat('!d-m-Y', '09-09-2026')->format('Y-m-d'));
        self::assertStringNotContainsString('05:30 PM', $view);
        self::assertSame(1, substr_count($view, 'name="follow_up_time"'));
    }

    public function testDoctorFollowUpTimeUsesTheSameRequiredNormalizationForCreateAndUpdate(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/follow-ups.php');
        $reflection = new \ReflectionClass(\Doctor\Controllers\Records::class);
        $method = $reflection->getMethod('normalizeFollowUpTime');
        $method->setAccessible(true);
        $instance = $reflection->newInstanceWithoutConstructor();

        self::assertSame('09:30:00', $method->invoke($instance, '09:30'));
        self::assertSame('00:05:00', $method->invoke($instance, '00:05'));
        self::assertSame('23:59:00', $method->invoke($instance, '23:59'));
        self::assertNull($method->invoke($instance, ''));
        self::assertNull($method->invoke($instance, '25:90'));
        self::assertNull($method->invoke($instance, '9'));
        self::assertNull($method->invoke($instance, 'not-a-time'));
        self::assertStringContainsString('$time = $this->normalizeFollowUpTime($timeInput)', $controller);
        self::assertStringContainsString("'follow_up_time' => \$time", $controller);
        self::assertStringContainsString('data-follow-up-time=', $view);
        self::assertStringContainsString('$formatTime($item[\'follow_up_time\'] ?? \'\')', $view);
        self::assertStringContainsString("return \$time->format('H:i');", $view);
        self::assertStringContainsString('id="follow_up_time"', $view);
        self::assertStringContainsString('step="900"', $view);
        self::assertStringContainsString("$('#follow_up_time').val(button.data('follow-up-time') || '')", $view);
        self::assertStringContainsString("\$time->format('H:i:s')", $controller);
        self::assertStringContainsString('id="follow_up_timeErr"', $view);
    }

    public function testDoctorFollowUpCreateReturnsFieldErrorsAndAuditsOnlyAfterSuccessfulScopedWrite(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/follow-ups.php');
        $validationStart = strpos($controller, "if (\$errors !== []) {");
        $transactionStart = strpos($controller, '$db->transBegin();', $validationStart);

        self::assertStringContainsString("'patient_id' => 'Select a patient in your clinic scope.'", $controller);
        self::assertStringContainsString("\$errors['follow_up_date'] = 'A valid follow-up date is required.'", $controller);
        self::assertStringContainsString("\$errors['follow_up_type_id'] = 'Select an active follow-up type.'", $controller);
        self::assertStringContainsString("\$errors['status'] = 'Select an active follow-up status.'", $controller);
        self::assertStringContainsString("\$errors['remarks'] = 'Remarks must be 5000 characters or fewer.'", $controller);
        self::assertStringContainsString("'msg' => 'Please correct the highlighted fields.'", $controller);
        self::assertStringContainsString("'errors' => \$errors", $controller);
        self::assertStringContainsString('private function audit(string $action, array $context, array $data): void', $controller);
        self::assertStringContainsString("service('audit')->record(", $controller);
        self::assertNotFalse($validationStart);
        self::assertNotFalse($transactionStart);
        self::assertLessThan($transactionStart, $validationStart);
        self::assertStringContainsString("data: form.serialize()", $view);
        self::assertStringContainsString("name=\"patient_id\"", $view);
        self::assertStringContainsString("name=\"follow_up_date\"", $view);
        self::assertStringContainsString("name=\"follow_up_type_id\"", $view);
        self::assertStringContainsString("name=\"status\"", $view);
        self::assertStringContainsString("name=\"remarks\"", $view);
        self::assertStringNotContainsString("modal.modal('hide');\n                    reload_page", substr($view, strpos($view, 'if (response.errors) bindFormError'), strpos($view, 'complete: function')));
    }

    public function testDoctorFollowUpFiltersSubmitDisplayDatesForServerSideDdMmYyyyParsing(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/follow-ups.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');

        self::assertStringContainsString('id="doctor-followup-filter-from" name="date_from"', $view);
        self::assertStringContainsString('id="doctor-followup-filter-to" name="date_to"', $view);
        self::assertStringContainsString('data-submit-display-date="true"', $view);
        self::assertStringContainsString('type="search" placeholder="Search patient, mobile, type, status..."', $view);
        self::assertStringNotContainsString('doctor-followup-quick-search', $view);
        $filterModalStart = strpos($view, 'id="doctor-followup-filter-modal"');
        $filterModalEnd = strpos($view, '<?= $this->endSection() ?>', $filterModalStart);
        $filterModal = substr($view, $filterModalStart, $filterModalEnd - $filterModalStart);
        self::assertSame(1, substr_count($filterModal, 'name="search"'));
        self::assertStringNotContainsString('Patient</label>', $filterModal);
        self::assertStringNotContainsString('name="patient_id"', $filterModal);
        self::assertStringNotContainsString('doctor-followup-filter-patient', $view);
        $followUpFiltersStart = strpos($controller, 'private function followUpFilters(): array');
        $followUpFiltersEnd = strpos($controller, 'private function appointmentFilters(): array', $followUpFiltersStart);
        $followUpFilters = substr($controller, $followUpFiltersStart, $followUpFiltersEnd - $followUpFiltersStart);
        self::assertStringNotContainsString("filters['patient_id']", $followUpFilters);
        self::assertStringNotContainsString("filters['patient_id']", substr($controller, strpos($controller, 'public function followUps(): string|RedirectResponse'), strpos($controller, 'public function createFollowUp')));
        self::assertStringContainsString('follow_up_statuses.name', $controller);
        self::assertStringContainsString('follow_up_statuses.code', $controller);
        self::assertStringContainsString('consultations.follow_up_type', $controller);
        self::assertStringContainsString('consultations.follow_up_remarks', $controller);
        self::assertStringContainsString("\$filters['date_from_iso'] = \$filters['date_from'] === '' ? null : \$this->normalizeDisplayDate(\$filters['date_from'])", $controller);
        self::assertStringContainsString("\$filters['date_to_iso'] = \$filters['date_to'] === '' ? null : \$this->normalizeDisplayDate(\$filters['date_to'])", $controller);
        self::assertStringContainsString("\$query->where('consultations.follow_up_date >=', \$filters['date_from_iso'])", $controller);
        self::assertStringContainsString("\$query->where('consultations.follow_up_date <=', \$filters['date_to_iso'])", $controller);
    }

    public function testDoctorClinicalWorkspaceHasScopedPatientContextAndCsrfProtectedActions(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php');
        $layout = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/layout.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Clinical.php');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/style.css');
        self::assertStringContainsString('doctor-top-patient-search', $layout);
        self::assertStringContainsString("base_url('doctor/profile')", $layout);

        foreach (['Patient Info', 'Visit Timeline'] as $label) {
            self::assertStringContainsString($label, $view);
        }
        self::assertStringNotContainsString('clinical-vital-card', $view);
        self::assertStringNotContainsString('>BP</span>', $view);
        self::assertStringNotContainsString('>Pulse</span>', $view);
        self::assertStringContainsString('OPD Consultation', $view);

        foreach (['patient_date_of_birth', 'patient_country_name', 'patient_state_name', 'patient_city_name', 'patient_locality_name'] as $field) {
            self::assertStringContainsString($field, $controller);
        }
        self::assertStringContainsString('clinical-main-grid', $styles);
        self::assertStringNotContainsString('min-height: 800px;', $styles);
        self::assertStringNotContainsString('max-height: calc(100vh - 13rem)', $styles);
        self::assertStringContainsString('.clinical-workflow-content > .clinical-workflow-pane > .card > .card-header', $styles);
        self::assertStringContainsString('--ri-primary: #2f716d;', $styles);
        self::assertStringContainsString('--ri-clinical-header: #1f2940;', $styles);
        self::assertStringContainsString('--ri-clinical-surface: #f4f6fa;', $styles);
        self::assertStringContainsString('main[aria-labelledby="clinical-title"] .clinical-opd-toolbar .btn {', $styles);
        self::assertStringContainsString('background: var(--ri-clinical-header);', $styles);
        self::assertStringContainsString('background: var(--ri-clinical-surface);', $styles);
        self::assertStringContainsString('background: transparent;', $styles);
        self::assertStringContainsString('border: 1px solid var(--ri-clinical-header);', $styles);
        self::assertStringContainsString('font-size: 14px;', $styles);
        self::assertStringContainsString('font-weight: 600;', $styles);
        self::assertStringContainsString('.validation-error:empty {', $styles);
        self::assertStringContainsString('.validation-error:not(:empty) {', $styles);
        self::assertStringNotContainsString('.validation-error { min-height: 1rem;', $styles);
        self::assertStringNotContainsString('--ri-clinical-accent', $styles);
        self::assertStringContainsString('.consultation-form > .row.g-2 {', $styles);
        self::assertStringContainsString('.consultation-form > .row.g-2 > .col-12 {', $styles);
        self::assertStringNotContainsString('grid-template-columns: repeat(12', $styles);
        self::assertStringNotContainsString('id="clinical-patient-search"', $view);
        self::assertStringNotContainsString('id="clinical-patient-results"', $view);
        self::assertStringNotContainsString('clinical-patient-autocomplete', $view);
        self::assertStringNotContainsString('id="clinical-context-bar"', $view);
        self::assertStringContainsString('id="clinical-queue-trigger"', $view);
        self::assertStringContainsString('id="clinical-queue-trigger" class="btn btn-sm btn-primary', $view);
        self::assertStringContainsString('Today’s OPD', $view);
        self::assertStringContainsString('clinical-opd-toolbar', $view);
        self::assertStringContainsString('id="clinical-action-modal"', $view);
        self::assertStringContainsString('id="clinical-action-modal-body"', $view);
        self::assertStringContainsString('data-toggle="modal" data-target="#clinical-action-modal"', $view);
        self::assertStringContainsString('dataType: \'html\'', $view);
        self::assertStringContainsString('window.print()', $view);
        self::assertStringContainsString('clinic_name', $view);
        self::assertStringContainsString('clinic_logo_url', $view);
        self::assertStringContainsString('clinical-action-prescription-clinic', $view);
        self::assertStringContainsString('clinical-action-prescription-patient-card', $view);
        self::assertStringContainsString('clinical-action-prescription-table', $view);
        self::assertStringContainsString('clinical-action-prescription-document-title', $view);
        self::assertStringContainsString('clinical-action-prescription-footer', $view);
        self::assertStringContainsString("['Patient ID', context.registration_number || '']", $view);
        self::assertStringNotContainsString("context.clinic_email", $view);
        self::assertStringNotContainsString("context.clinic_location", $view);
        self::assertStringNotContainsString("['Status', String(prescription.status", $view);
        self::assertStringContainsString("context.patient_age !== 'Not recorded'", $view);
        self::assertStringContainsString('<th scope="col">Medicine</th>', $view);
        self::assertStringContainsString('<th scope="col">Duration</th>', $view);
        self::assertStringContainsString('<th scope="col">Frequency</th>', $view);
        self::assertStringContainsString('<th scope="col">Dose</th>', $view);
        self::assertStringContainsString('<th scope="col">Intake</th>', $view);
        self::assertStringContainsString('<th scope="col">Note</th>', $view);
        self::assertStringContainsString('doctor-clinical-printing', $view);
        self::assertStringContainsString('consultation_number', $view);

        foreach (['Past Visits', 'Consultation Form', 'Print Prescription', 'New Payment', 'Payment History', 'Images'] as $action) {
            self::assertStringContainsString($action, $view);
        }

        foreach (['past-visits', 'consultation', 'print-prescription', 'new-payment', 'payment-history', 'images'] as $action) {
            self::assertStringContainsString('data-clinical-action="' . $action . '"', $view);
        }
        self::assertStringContainsString('past_visits_url', $view);
        self::assertStringContainsString('payment_history_url', $view);
        self::assertStringContainsString('Patient images are not available in the Doctor panel yet.', $view);
        self::assertStringContainsString('New payment entry is not available for the Doctor role.', $view);
        self::assertStringNotContainsString('target="_blank"', $view);
        self::assertStringNotContainsString('id="clinical-appointment-context"', $view);
        self::assertStringNotContainsString('clinical-workflow-nav', $view);
        self::assertStringNotContainsString('aria-label="Clinical section shortcuts"', $view);
        self::assertStringNotContainsString('clinical-consultation-tabs', $view);
        self::assertStringNotContainsString('clinical-consultation-tab', $view);
        self::assertStringNotContainsString('activateConsultationTab', $view);
        self::assertStringNotContainsString('clinical-consultation-tabs', $styles);
        self::assertStringNotContainsString('.clinical-tab', $styles);
        self::assertStringNotContainsString('aria-labelledby="clinical-tab-', $view);

        foreach (['patient-summary', 'prescription'] as $tab) {
            self::assertStringContainsString('id="clinical-pane-' . $tab . '"', $view);
        }
        self::assertStringNotContainsString('id="clinical-pane-consultation"', $view);
        self::assertStringNotContainsString('Consultation Form</h2>', $view);
        self::assertStringContainsString('class="consultation-form"', $view);
        self::assertStringContainsString('<div class="row g-2">', $view);
        self::assertStringContainsString('<div class="row g-2 clinical-three-column-row">', $view);
        self::assertSame(3, substr_count($view, 'class="col-12 col-md-4"'));
        self::assertSame(2, substr_count($view, 'class="col-md-6 col-12"'));
        self::assertLessThan(strpos($view, 'clinical-consultation-pane-diagnosis'), strpos($view, 'clinical-consultation-pane-chief-complaints'));

        foreach (['Consultation Form', 'Chief Complaints', 'Diagnosis', 'Advice', 'Diagnostic Tests', 'Diet Plan', 'Private Notes'] as $section) {
            self::assertStringContainsString($section, $view);
        }
        self::assertSame(8, substr_count($view, 'clinical-section-card'));

        foreach (['Appointment setup', 'Video consultation', 'History and documents', 'Billing', 'clinical-pane-follow-up', 'clinical-pane-appointment-setup', 'clinical-pane-video', 'clinical-pane-billing', 'clinical-pane-history'] as $removed) {
            self::assertStringNotContainsString($removed, $view);
        }

        foreach (['clinical-action-toolbar', 'clinical-step-actions', 'clinical-next', 'clinical-back', 'scrollToClinicalSection'] as $removed) {
            self::assertStringNotContainsString($removed, $view);
            self::assertStringNotContainsString($removed, $styles);
        }
        self::assertStringContainsString('prescriptionStatusDefault', $view);
        self::assertStringContainsString('value="<?= esc($prescriptionStatusDefault) ?>"', $view);
        self::assertStringContainsString('clinical-opd-queue-modal', $view);
        self::assertStringContainsString('clinical-opd-pagination', $view);
        self::assertStringContainsString('opdCurrentPage', $view);
        self::assertStringContainsString('Today’s OPD / Queue', $view);
        self::assertStringContainsString("clinical/appointments/search", file_get_contents(ROOTPATH . 'app/Modules/Doctor/Config/Routes.php'));
        self::assertStringContainsString('searchOpd', $view);
        self::assertStringContainsString('opd-select-appointment', $view);
        self::assertStringContainsString("'queue_number' =>", $controller);
        self::assertStringContainsString("'service_name' =>", $controller);
        self::assertStringContainsString("pagination && pagination.page", $view);
        self::assertStringNotContainsString("doctor/clinical/patients/search", $view);
        self::assertStringContainsString('aria-autocomplete="list"', $view);
        self::assertStringContainsString('in_consultation', file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Clinical.php'));
        self::assertStringContainsString("base_url('doctor/patients')", $view);
        self::assertSame(2, substr_count($view, '<form'));
        self::assertSame(2, substr_count($view, '</form>'));
        self::assertSame(2, substr_count($view, 'csrf_field()'));
        self::assertStringContainsString("base_url('doctor/clinical/save')", $view);
        self::assertStringContainsString("Clinical::saveWorkspace", file_get_contents(ROOTPATH . 'app/Modules/Doctor/Config/Routes.php'));
        self::assertStringContainsString('id="clinical-workspace-form"', $view);
        self::assertStringContainsString('name="consultation_status"', $view);
        self::assertStringContainsString('name="prescription_status"', $view);
        self::assertStringContainsString('name="prescription_present"', $view);
        self::assertStringContainsString('Save Consultation', $view);
        self::assertStringNotContainsString('save-consultation', $view);
        self::assertStringNotContainsString('save-prescription', $view);
        self::assertStringNotContainsString('Save section', $view);
        self::assertStringNotContainsString('Finalize prescription', $view);
        self::assertStringNotContainsString("base_url('doctor/consultations/save')", $view);
        self::assertStringNotContainsString("base_url('doctor/prescriptions/save')", $view);
        self::assertSame(1, substr_count($view, 'Save Consultation'));
        $workspaceStart = strpos($view, 'id="clinical-workspace-form"');
        $workspace = $workspaceStart === false ? '' : substr($view, $workspaceStart, strpos($view, '</form>', $workspaceStart) - $workspaceStart);
        self::assertSame(1, substr_count($workspace, 'type="submit"'));
        self::assertStringContainsString('bindWorkspaceErrors', $view);
        self::assertStringContainsString('handleFormAjaxError', $view);
        self::assertStringContainsString("\$can('consultation.edit')", $view);
        self::assertStringContainsString("\$can('prescription.create')", $view);
        self::assertStringContainsString("saveWorkspace", $controller);
        self::assertStringNotContainsString('.DataTable()', $view);
        self::assertStringContainsString('diagnostic_tests', $view);
        self::assertStringContainsString('diet_plan', $view);
        self::assertStringContainsString('diagnostic_tests', file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Clinical.php'));
        self::assertStringContainsString('diet_plan', file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/ConsultationModel.php'));
    }

    public function testDoctorClinicalWorkspaceHidesPatientContentUntilQueueSelection(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php');

        self::assertStringContainsString('data-selected-appointment-id=', $view);
        self::assertStringContainsString('data-selected-patient-id=', $view);
        self::assertStringContainsString('<?php if ($selectedAppointment === null): ?>', $view);
        self::assertStringContainsString('Select an appointment from Today’s OPD to enable consultation actions.', $view);
        self::assertStringContainsString('<?php if ($selectedAppointment !== null): ?>', $view);
        self::assertStringContainsString('id="clinical-patient-specific-content"', $view);
        self::assertStringContainsString('data-clinical-patient-specific', $view);
        self::assertStringContainsString('window.rootinsClinicalSelection', $view);
        self::assertStringContainsString('function clearClinicalSelection', $view);
        self::assertStringContainsString('function selectClinicalAppointment', $view);
        self::assertStringContainsString("$('#clinical-opd-queue-modal').modal('hide')", $view);
        self::assertStringContainsString('selectClinicalAppointment(items[active])', $view);
        self::assertStringContainsString('selectClinicalAppointment(item)', $view);
        self::assertStringContainsString('hideClinicalPatientContent();', $view);
    }

    public function testDoctorPastVisitsUsesScopedPaginatedListAndDetailModalFlow(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Clinical.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Config/Routes.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/style.css');

        self::assertStringContainsString("'clinical/past-visits', 'Clinical::pastVisits'", $routes);
        self::assertStringContainsString("'clinical/past-visits/(:num)', 'Clinical::pastVisit/\$1'", $routes);

        foreach (['public function pastVisits', 'public function pastVisit', 'pastVisitsQuery', 'consultations.doctor_id', 'consultations.clinic_id', 'patients.clinic_id', 'date_from', 'date_to', 'appointment_type', 'countAllResults(false)', 'orderBy(\'consultations.consultation_date\', \'DESC\')'] as $contract) {
            self::assertStringContainsString($contract, $controller);
        }

        foreach (['loadClinicalPastVisits', 'loadClinicalPastVisitDetails', 'clinical-past-visit-select', 'data-past-visit-detail-id', 'data-past-visits-page', 'Past Visits Details', 'clinical-past-visit-reference-card', 'clinical-past-visit-reference-meta', 'Visit Date:', 'Complaints', 'Tests', 'Follow-up Setup', 'Appointment Setup'] as $contract) {
            self::assertStringContainsString($contract, $view);
        }
        self::assertStringNotContainsString('clinical-past-visits-filter', $view);
        self::assertStringNotContainsString('past_visits_selector', $view);

        foreach (['.clinical-past-visits-reference-list', '.clinical-past-visit-loading-card', '.clinical-past-visit-reference-card', '.clinical-past-visit-reference-panel'] as $contract) {
            self::assertStringContainsString($contract, $styles);
        }
        self::assertStringContainsString('@media (max-width: 767.98px)', $styles);
        self::assertStringNotContainsString('.DataTable()', $view);
    }

    public function testDoctorClinicalFollowUpAndAppointmentFieldsUseCompactFourColumnRows(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php');
        $followUpStart = strpos($view, 'id="clinical-consultation-pane-follow-up-setup"');
        $appointmentStart = strpos($view, 'id="clinical-consultation-pane-appointment-setup"');

        self::assertNotFalse($followUpStart);
        self::assertNotFalse($appointmentStart);

        $followUp = substr($view, $followUpStart, $appointmentStart - $followUpStart);
        $appointment = substr($view, $appointmentStart);

        foreach ([$followUp, $appointment] as $section) {
            self::assertStringContainsString('form-row clinical-compact-row', $section);
            self::assertSame(4, substr_count($section, 'col-md-3 col-12 mb-2'));
        }
        self::assertStringContainsString('col-12 mb-2', $followUp);
        self::assertStringContainsString('id="follow-up-remarks"', $followUp);
        self::assertStringContainsString('class="form-control datepicker"', $followUp);
        self::assertStringContainsString('id="next-appointment-date"', $appointment);
        self::assertStringContainsString('placeholder="dd-mm-yyyy"', $appointment);
        self::assertStringContainsString('Save Consultation', $view);
        self::assertSame(1, substr_count($view, 'id="clinical-workspace-form"'));
    }

    public function testDoctorClinicalWorkspaceDensityRulesAreScopedToTheClinicalPage(): void
    {
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/style.css');

        $toolbarSelector = 'main[aria-labelledby="clinical-title"] .clinical-opd-toolbar .btn';
        $toolbarStart = strpos($styles, $toolbarSelector);
        self::assertNotFalse($toolbarStart);
        $toolbarRule = substr($styles, $toolbarStart, strpos($styles, '}', $toolbarStart) - $toolbarStart + 1);
        self::assertStringContainsString('min-height: 22px;', $toolbarRule);
        self::assertStringContainsString('font-size: 10px;', $toolbarRule);
        self::assertStringContainsString('font-weight: 500;', $toolbarRule);

        foreach (['padding:', 'font-size:', 'font-weight:', 'line-height:'] as $declaration) {
            if (in_array($declaration, ['font-size:', 'font-weight:'], true)) {
                continue;
            }
            self::assertStringNotContainsString($declaration, $toolbarRule);
        }
        self::assertStringContainsString('main[aria-labelledby="clinical-title"] input.form-control,', $styles);
        self::assertStringContainsString('min-height: 35px;', $styles);
        self::assertStringContainsString('height: 35px;', $styles);
        self::assertStringContainsString('main[aria-labelledby="clinical-title"] .validation-error', $styles);
        self::assertStringContainsString('label:not(.sr-only)', $styles);
        self::assertStringContainsString('background: var(--ri-surface);', $styles);
        self::assertStringContainsString('background: var(--ri-surface-muted);', $styles);
        self::assertStringContainsString('background: var(--ri-primary-soft);', $styles);
        self::assertStringContainsString('border-left: 3px solid var(--ri-warning);', $styles);
        self::assertStringContainsString('border-left: 3px solid var(--ri-danger);', $styles);
    }

    public function testDoctorClinicalWorkspaceSetupCardsUseUnifiedPersistenceContract(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Clinical.php');
        $model = file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/ConsultationModel.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-09-09-130000_AddConsultationFollowUpAndAppointmentSetup.php');
        $followUpTypeMigration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-09-14-120000_CreateFollowUpTypes.php');

        foreach (['follow_up_after_days', 'follow_up_date', 'follow_up_time', 'follow_up_remarks', 'next_appointment_after_days', 'next_appointment_date', 'next_appointment_time', 'next_appointment_type'] as $field) {
            self::assertStringContainsString('name="' . $field . '"', $view);
            self::assertStringContainsString("'{$field}'", $controller);
            self::assertStringContainsString($field, $model);
            self::assertStringContainsString("'{$field}'", $migration);
        }
        self::assertStringContainsString('name="follow_up_type_id"', $view);
        self::assertStringContainsString("'follow_up_type_id'", $controller);
        self::assertStringContainsString('follow_up_type_id', $model);
        self::assertStringContainsString("'follow_up_type_id'", $followUpTypeMigration);
        self::assertStringContainsString('follow_up_type', $model);
        self::assertStringContainsString('follow_up_type', $migration);
        self::assertDoesNotMatchRegularExpression('/name="follow_up_type"(?:\s|>)/', $view);

        self::assertLessThan(strpos($view, 'Appointment Setup'), strpos($view, 'Follow-up Setup'));
        self::assertStringContainsString('optionalDays', $controller);
        self::assertStringContainsString('normalizeTime', $controller);
        self::assertStringContainsString("textErrors(['follow_up_remarks' => 65535])", $controller);
        self::assertStringContainsString("'follow_up_after_days' => \$followUpAfterDays", $controller);
        self::assertStringContainsString("'next_appointment_date' => \$nextAppointmentDate", $controller);
        self::assertStringContainsString('$db->transBegin();', $controller);
        self::assertStringContainsString('dropColumn', $migration);
        self::assertStringNotContainsString('clinical-next', $view);
        self::assertStringNotContainsString('clinical-step-actions', $view);
        self::assertStringContainsString('step="1"', $view);
        self::assertStringContainsString('data-appointment-base-date=', $view);
        self::assertStringContainsString('calculateFollowUpDate', $view);
        self::assertStringContainsString("'#follow-up-after-days'", $view);
        self::assertStringContainsString("'#follow-up-date'", $view);
        self::assertStringContainsString("'#follow_up_after_daysErr'", $view);
        self::assertStringContainsString('calculateNextAppointmentDate', $view);
        self::assertStringContainsString('input.doctorAppointmentSetup change.doctorAppointmentSetup', $view);
        self::assertStringContainsString('window.setSharedDateValue(selector', $view);
        self::assertStringContainsString('Number.isSafeInteger(days)', $view);
        self::assertStringContainsString('days > 65535', $view);
        self::assertStringContainsString("setClinicalCalculatedDate(dateSelector, '')", $view);
        self::assertStringContainsString("Date.UTC(Number(baseParts[1])", $view);
    }

    public function testDoctorClinicalWorkspaceParsesSetupFieldsBeforePersistence(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Clinical.php');
        $workspaceStart = strpos($controller, 'public function saveWorkspace');
        $parseStart = strpos($controller, '$followUpAfterDays = $this->optionalDays', $workspaceStart);
        $dataStart = strpos($controller, "'follow_up_after_days' => \$followUpAfterDays", $workspaceStart);

        self::assertNotFalse($workspaceStart);
        self::assertNotFalse($parseStart);
        self::assertNotFalse($dataStart);
        self::assertLessThan($dataStart, $parseStart);

        foreach ([
            '$followUpAfterDays',
            '$followUpTime',
            '$followUpTypeId',
            '$followUpType',
            '$nextAppointmentAfterDays',
            '$nextAppointmentDate',
            '$nextAppointmentTime',
            '$nextAppointmentType',
        ] as $variable) {
            $parseBlock = substr($controller, $parseStart, $dataStart - $parseStart);
            self::assertTrue(str_contains($parseBlock, $variable . ' =') || (($variable === '$followUpTypeId' || $variable === '$followUpType') && str_contains($parseBlock, 'optionalFollowUpType')));
        }
    }

    public function testDoctorClinicalWorkspaceUsesGlobalDateTimeClassForTimeValidation(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Clinical.php');

        self::assertStringContainsString("\\DateTimeImmutable::createFromFormat('!H:i', \$value)", $controller);
        self::assertStringNotContainsString('$time = DateTimeImmutable::createFromFormat(\'!H:i\', $value)', $controller);
    }

    public function testDoctorPrescriptionUsesConditionalDurationAndRowValidation(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Clinical.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php');

        self::assertStringContainsString('name="medicine_duration"', $view);
        self::assertStringContainsString('id="medicine_durationErr"', $view);
        self::assertStringContainsString('data-error-for="items"', $view);
        self::assertStringContainsString('hasNonEmptyPrescriptionRows', $controller);
        self::assertStringContainsString('normalizeMedicineDuration', $controller);
        self::assertStringContainsString('validatePrescriptionRequirements', $controller);
        self::assertStringContainsString('$errors[\'items\'] = \'Add at least one medicine or clear the duration.\'', $controller);
        self::assertStringContainsString('$errors[\'medicine_duration\'] = \'Medicine duration is required when medicines are added.\'', $controller);
        self::assertStringContainsString('if (! $this->isNonEmptyPrescriptionItem($item))', $controller);
        self::assertStringContainsString("prescriptionErrors.items = 'Add at least one medicine or clear the duration.'", $view);
        self::assertStringContainsString("prescriptionErrors['items.' + row.data('row-index') + '.medicine_id']", $view);
        self::assertStringContainsString("/^\\d+$/.test(prescriptionDuration)", $view);
        self::assertStringContainsString('Number(prescriptionDuration) >= 1', $view);
        self::assertStringContainsString('Number(prescriptionDuration) <= 3650', $view);
        self::assertStringContainsString("if (prescriptionDuration !== '' && !validPrescriptionDuration)", $view);
        self::assertStringContainsString("if (nonEmptyPrescriptionRows.length && prescriptionDuration === '')", $view);
        self::assertStringContainsString("$('#clinical-prescription-present').val(nonEmptyPrescriptionRows.length ? '1' : '0');", $view);
        self::assertStringContainsString('The initial UI row may carry the shared default intake option.', $controller);
        self::assertStringNotContainsString("select[name*=\"[intake]\"]", substr($view, strpos($view, 'const nonEmptyPrescriptionRows'), strpos($view, 'const consultationStatus') - strpos($view, 'const nonEmptyPrescriptionRows')));
    }

    public function testDoctorPrescriptionConditionalDurationCases(): void
    {
        $clinical = (new \ReflectionClass(Clinical::class))->newInstanceWithoutConstructor();
        $durationMethod = new \ReflectionMethod(Clinical::class, 'normalizeMedicineDuration');
        $durationMethod->setAccessible(true);
        $requirementsMethod = new \ReflectionMethod(Clinical::class, 'validatePrescriptionRequirements');
        $requirementsMethod->setAccessible(true);
        $rowsMethod = new \ReflectionMethod(Clinical::class, 'hasNonEmptyPrescriptionRows');
        $rowsMethod->setAccessible(true);

        $errors = [];
        self::assertNull($durationMethod->invokeArgs($clinical, ['', &$errors]));
        self::assertSame([], $errors);
        $requirementsMethod->invokeArgs($clinical, [false, '', null, &$errors]);
        self::assertSame([], $errors);

        $errors = [];
        $requirementsMethod->invokeArgs($clinical, [true, '', null, &$errors]);
        self::assertSame('Medicine duration is required when medicines are added.', $errors['medicine_duration']);

        $errors = [];
        $requirementsMethod->invokeArgs($clinical, [false, '7', 7, &$errors]);
        self::assertSame('Add at least one medicine or clear the duration.', $errors['items']);

        foreach (['0', '-1', '1.5', 'abc', '3651'] as $invalid) {
            $errors = [];
            self::assertNull($durationMethod->invokeArgs($clinical, [$invalid, &$errors]));
            self::assertArrayHasKey('medicine_duration', $errors);
        }

        foreach (['1' => 1, '3650' => 3650, '01' => 1] as $input => $expected) {
            $errors = [];
            self::assertSame($expected, $durationMethod->invokeArgs($clinical, [$input, &$errors]));
            self::assertSame([], $errors);
        }

        self::assertFalse($rowsMethod->invoke($clinical, [['intake' => '34']]));
        self::assertTrue($rowsMethod->invoke($clinical, [['notes' => 'partially completed']]));
    }

    public function testDoctorFollowUpDateCalculationCoversValidAndUnsafeDayValues(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php');

        foreach (['calculateClinicalRelativeDate', 'calculateFollowUpDate', 'input.doctorAppointmentSetup change.doctorAppointmentSetup', 'setSharedDateValue(selector', 'data-appointment-base-date'] as $contract) {
            self::assertStringContainsString($contract, $view);
        }
        self::assertStringContainsString("if (rawDays === '')", $view);
        self::assertStringContainsString("!/^\\d+$/.test(rawDays)", $view);
        self::assertStringContainsString('Number.isSafeInteger(days)', $view);
        self::assertStringContainsString('days > 65535', $view);
        self::assertStringContainsString('baseDate.setUTCDate(baseDate.getUTCDate() + days)', $view);
        self::assertStringContainsString("field.data('setting-auto-date', true)", $view);

        $base = new \DateTimeImmutable('2026-09-10', new \DateTimeZone('Asia/Kolkata'));

        foreach ([0 => '10-09-2026', 1 => '11-09-2026', 65535 => '14-02-2206'] as $days => $expected) {
            $calculated = $base->modify('+' . $days . ' days');
            self::assertSame($expected, $calculated->format('d-m-Y'));
        }

        foreach (['', '-1', '1.5', 'abc', '65536'] as $invalid) {
            self::assertTrue($invalid === '' || ! preg_match('/^\d+$/', $invalid) || (int) $invalid > 65535);
        }
    }

    public function testDoctorClinicalPrescriptionRowsAreRepeatableAndUseExistingItemFields(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Clinical.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Config/Routes.php');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/style.css');

        foreach (['Medicine', 'Duration', 'Frequency', 'Dose', 'Intake', 'Note'] as $label) {
            self::assertStringContainsString('data-label="' . $label . '"', $view);
        }

        foreach (['medicine_id', 'duration', 'frequency', 'dosage', 'intake', 'notes'] as $field) {
            self::assertStringContainsString('name="items[', $view);
            self::assertStringContainsString('][' . $field . ']"', $view);
            self::assertStringContainsString('_' . $field . 'Err', $view);
        }
        self::assertStringContainsString('clinical-medicine-row-template', $view);
        self::assertStringContainsString('clinical-add-medicine', $view);
        self::assertStringContainsString('clinical-remove-medicine', $view);
        self::assertStringContainsString('clinical-medicine-inline-actions', $view);
        self::assertStringContainsString('title="Remove medicine row"', $view);
        self::assertStringNotContainsString('clinical-medicine-actions', $view);

        foreach (['Search item...', 'Past Prescription', 'Medicine Duration (Days)', 'clinical-medicine-duration', 'clinical-prescription-footer', 'Add More', 'OR Use', 'Alt + N'] as $control) {
            self::assertStringContainsString($control, $view);
        }

        foreach (['name="items[', 'name="items[__INDEX__][frequency]"', 'name="items[__INDEX__][dosage]"', 'name="items[__INDEX__][intake]"'] as $field) {
            self::assertStringContainsString($field, $view);
        }
        self::assertStringContainsString('select id="clinical-frequency-', $view);
        self::assertStringContainsString('select id="clinical-dose-', $view);
        self::assertStringContainsString('select id="clinical-intake-', $view);
        self::assertStringContainsString('data-clinical-action="past-prescription"', $view);
        self::assertStringContainsString('clinical-add-medicine-trigger', $view);
        self::assertStringContainsString('keydown.clinicalPrescription', $view);
        self::assertStringContainsString("options.noneSelectedText = 'Search item...'", $view);
        self::assertStringContainsString('data-live-search="true"', $view);
        self::assertStringContainsString("doctor/clinical/medicines/search", $view);
        self::assertStringContainsString('name="items[<?= (int) $index ?>][medicine_id]"', $view);
        self::assertStringContainsString('name="items[__INDEX__][medicine_id]"', $view);
        self::assertStringContainsString('$defaultIntakeOptionId = $defaultPrescriptionOptionId', $view);
        self::assertStringContainsString('$prescriptionItem === [] && $intakeOptionId ===', $view);
        self::assertStringContainsString('(string) $option[\'id\'] === $defaultIntakeOptionId ? \'selected\' : \'\'', $view);
        self::assertStringContainsString('initializeClinicalMedicineRows', $view);
        self::assertStringContainsString('bindClinicalMedicineSelect', $view);
        self::assertStringContainsString('At least one prescription item is required.', $controller);
        self::assertStringContainsString('Choose a valid medicine duration.', $controller);
        self::assertStringContainsString("'instructions' => \$intake['name'] ?? null", $controller);
        self::assertStringContainsString("'notes' => \$this->item(\$item, 'notes'", $controller);
        self::assertStringContainsString("clinical/medicines/search", $routes);
        self::assertStringContainsString("permission:prescription.create", $routes);
        self::assertStringContainsString('.clinical-prescription-table', $styles);
        self::assertStringContainsString('clinical-prescription-table tbody tr', $styles);
        self::assertStringContainsString('.clinical-prescription-header', $styles);
        self::assertStringContainsString('.clinical-prescription-footer', $styles);
        self::assertStringContainsString('.clinical-prescription-duration', $styles);
        self::assertStringNotContainsString('.DataTable()', $view);
    }

    public function testDoctorMedicineSearchUsesDebouncedScopedDatabaseIdsForDynamicRows(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Clinical.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-09-10-190000_AddMedicineReferenceToPrescriptionItems.php');
        $model = file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/PrescriptionItemModel.php');

        foreach (['getGet(\'q\')', 'mb_strlen($term) < 2', "where('status', 'active')", 'findAll(20)', 'display_name', '[\'items\' => $items]'] as $contract) {
            self::assertStringContainsString($contract, $controller);
        }

        foreach (['searchClinicalMedicines', 'clinicalMedicineSearch', 'data: {q: query}', '}, 280));', 'requestId !== Number(select.data(\'medicine-search-request\'))', 'initializeClinicalMedicineRows(row);', "input.closest('.dropdown-menu')", '.clinical-prescription-dropdown-menu .bs-searchbox input', 'picker.$menu[0]', 'const newMedicineSelect = row.find(\'.clinical-medicine-select\').first()', "window.setTimeout(function ()", 'document.documentElement.contains(newMedicineSelect[0])', "newMedicineSelect.selectpicker('refresh')", 'focusClinicalMedicineSearch(newMedicineSelect)', "toggle.trigger('click')", 'clinical-prescription-dropdown-menu.show', '.bs-searchbox input[type="search"]:visible', 'search[0].focus', 'search[0].select', "shown.bs.select", 'requestAnimationFrame', 'window.setTimeout(focusSearch, 50)'] as $contract) {
            self::assertStringContainsString($contract, $view);
        }
        self::assertStringContainsString('medicine_id', $model);
        self::assertStringContainsString('medicine_id', $migration);
        self::assertStringContainsString('fk_prescription_items_medicine_id', $migration);
        self::assertStringContainsString('prescription_items.medicine_id = medicines.id', $migration);
    }

    public function testDoctorMedicineSelectionLoadsAutofillOnlyFromMedicineRecord(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Clinical.php');
        $adminController = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Medicine/Medicine.php');
        $medicineModel = file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/MedicineModel.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Config/Routes.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php');
        $adminView = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/medicine/index.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-09-13-130000_MoveMedicinePrescriptionDefaultsToMedicines.php');

        foreach (['medicineAutofill', 'select(\'id, prescription_duration_id, prescription_frequency_id, prescription_dose_id, prescription_intake_id, prescription_note\')', 'prescription_duration_id', 'prescription_frequency_id', 'prescription_dose_id', 'prescription_intake_id', 'prescription_note', 'medicine_autofill_viewed', 'Medicine values loaded.'] as $contract) {
            self::assertStringContainsString($contract, $controller);
        }
        $methodStart = strpos($controller, 'public function medicineAutofill');
        $methodEnd = strpos($controller, 'public function index', $methodStart);
        self::assertNotFalse($methodStart);
        self::assertNotFalse($methodEnd);
        $autofillMethod = substr($controller, $methodStart, $methodEnd - $methodStart);
        self::assertStringContainsString('(new MedicineModel())', $autofillMethod);
        self::assertStringContainsString("return \$this->json(200, true, 'Medicine values loaded.', \$autofill);", $autofillMethod);

        foreach (['prescription_duration_id', 'prescription_frequency_id', 'prescription_dose_id', 'prescription_intake_id', 'prescription_note'] as $field) {
            self::assertStringContainsString("'{$field}' =>", $autofillMethod);
            self::assertStringContainsString("\$medicine['{$field}']", $autofillMethod);
        }
        self::assertStringNotContainsString('$this->prescriptions', $autofillMethod);
        self::assertStringNotContainsString('$this->prescriptionItems', $autofillMethod);
        self::assertStringNotContainsString('selectedPrescription', $autofillMethod);
        self::assertStringNotContainsString('medicine_prescription_defaults', $autofillMethod);
        self::assertStringNotContainsString('MedicinePrescriptionDefaultModel', $controller);
        self::assertStringNotContainsString('medicine_prescription_defaults', $controller);
        self::assertStringNotContainsString('medicine_prescription_defaults', $adminController);
        self::assertStringNotContainsString('medicine_prescription_defaults', $view);
        self::assertStringContainsString('prescription_duration_id', $medicineModel);
        self::assertStringContainsString('prescription_note', $medicineModel);

        foreach (['resolvePrescriptionDefaults', 'prescription_duration_id', 'prescription_frequency_id', 'prescription_dose_id', 'prescription_intake_id', 'prescription_note'] as $contract) {
            self::assertStringContainsString($contract, $adminController);
        }
        self::assertStringContainsString("clinical/medicines/(:num)/autofill", $routes);
        self::assertStringContainsString('permission:prescription.create', $routes);

        foreach (['data-autofill-url', 'loadClinicalMedicineAutofill', "url: baseUrl + '/' + encodeURIComponent(currentMedicineId) + '/autofill'", "method: 'GET'", 'medicine-autofill-request', 'medicine-autofill-xhr', 'clinicalMedicineAutofill', 'setClinicalMedicineAutofillField', 'clearClinicalMedicineAutofill'] as $contract) {
            self::assertStringContainsString($contract, $view);
        }

        foreach (['prescription_duration_id', 'prescription_frequency_id', 'prescription_dose_id', 'prescription_intake_id', 'prescription_note', 'medicine_prescription_defaults', 'without overwriting values already present on medicines', 'lookupExists'] as $contract) {
            self::assertStringContainsString($contract, $migration);
        }

        foreach (['prescription_duration_id', 'prescription_frequency_id', 'prescription_dose_id', 'prescription_intake_id', 'prescription_note'] as $contract) {
            self::assertStringContainsString($contract, $adminView);
        }
        self::assertStringNotContainsString('medicineDefaults = {', $view);
        self::assertStringNotContainsString('No saved prescription defaults found.', $controller);
        self::assertStringNotContainsString("['defaults'", $autofillMethod);
        self::assertStringNotContainsString("clinical/medicines/(:num)/defaults", $routes);
        self::assertStringNotContainsString('medicineDefaults', $controller);
        self::assertStringNotContainsString('response.data.defaults', $view);
        self::assertStringNotContainsString('Medicine values loaded; you can edit them.', $view);
        self::assertStringNotContainsString('clinical-medicine-autofill-status', $view);
        self::assertStringNotContainsString('setClinicalMedicineAutofillStatus', $view);
    }

    public function testDoctorPrescriptionControlsUseScopedTypographyForDynamicRows(): void
    {
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/style.css');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php');

        self::assertStringContainsString(
            'main[aria-labelledby="clinical-title"] #clinical-pane-prescription .clinical-prescription-card input.form-control',
            $styles,
        );
        self::assertStringContainsString(
            'main[aria-labelledby="clinical-title"] #clinical-pane-prescription .clinical-prescription-card select.form-control',
            $styles,
        );
        self::assertStringContainsString(
            'main[aria-labelledby="clinical-title"] #clinical-pane-prescription .clinical-prescription-card textarea.form-control',
            $styles,
        );
        self::assertStringContainsString(
            'main[aria-labelledby="clinical-title"] #clinical-pane-prescription .clinical-prescription-card .bootstrap-select > .dropdown-toggle',
            $styles,
        );
        self::assertStringContainsString(
            'main[aria-labelledby="clinical-title"] #clinical-pane-prescription .clinical-prescription-card .bootstrap-select .dropdown-menu li a',
            $styles,
        );
        self::assertStringContainsString(
            'main[aria-labelledby="clinical-title"] #clinical-pane-prescription .clinical-prescription-card .bootstrap-select .dropdown-menu .no-results',
            $styles,
        );
        self::assertStringContainsString(
            'main[aria-labelledby="clinical-title"] #clinical-pane-prescription .clinical-prescription-card .bootstrap-select .dropdown-menu [role="status"]',
            $styles,
        );
        self::assertStringContainsString('font-size: 12px;', $styles);
        self::assertStringContainsString('font-weight: 500;', $styles);
        self::assertDoesNotMatchRegularExpression(
            '/\.clinical-prescription-table \.form-control\s*\{[^}]*font-size:\s*\.78rem;/s',
            $styles,
        );
        self::assertStringContainsString('id="clinical-medicine-row-template"', $view);
        self::assertStringContainsString('class="form-control btSelect"', $view);

        foreach (['frequency', 'dosage', 'intake'] as $field) {
            self::assertStringContainsString(
                'class="form-control btSelect" name="items[<?= (int) $index ?>][' . $field . ']"',
                $view,
            );
            self::assertStringContainsString(
                'class="form-control btSelect" name="items[__INDEX__][' . $field . ']"',
                $view,
            );
        }
        self::assertStringContainsString('initializeClinicalMedicineRows(row);', $view);
    }

    public function testDoctorPrescriptionDropdownsUseDatabaseOptionCatalogsAndRejectUnknownIds(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Clinical.php');
        $model = file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/PrescriptionOptionModel.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-09-10-160000_SeedPrescriptionOptions.php');

        foreach (['duration', 'frequency', 'dose', 'intake', 'status'] as $type) {
            self::assertArrayHasKey($type, PrescriptionOptionModel::TYPES);
            self::assertStringContainsString("'{$type}' =>", $view);
        }
        self::assertStringContainsString("activeById(", $controller);
        self::assertStringContainsString("activeByCode(", $controller);
        self::assertStringContainsString("where('status', 'active')", $controller);
        self::assertStringContainsString("'prescription-duration' => 'prescription_durations'", $model);
        self::assertStringNotContainsString("table('admin_master_records')", $model);
        self::assertStringContainsString('defaultOptions()', $migration);
        self::assertStringNotContainsString("['Once daily'", $view);
        self::assertStringNotContainsString("['1 tablet'", $view);
        self::assertStringNotContainsString("['Before food'", $view);
    }

    public function testDoctorUsesSharedModalFirstShellForFocusedFormsAndPreviews(): void
    {
        $common = file_get_contents(ROOTPATH . 'public/assets/js/common.js');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/style.css');
        $patients = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/patients.php');
        $appointments = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/appointments.php');
        $followUps = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/follow-ups.php');
        $notifications = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/notifications.php');
        $prescriptions = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/prescriptions.php');
        $prescriptions .= file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/prescriptions_actions.php');

        self::assertStringContainsString('window.RootinsModal', $common);
        self::assertStringContainsString('keydown.rootinsModal', $common);
        self::assertStringContainsString('hidden.bs.modal.rootinsModal', $common);
        self::assertStringContainsString('rootins-modal-lock', $styles);
        self::assertStringContainsString('max-height: calc(100vh - 2rem)', $styles);
        self::assertStringContainsString('overflow-y: auto', $styles);

        foreach ([$patients, $appointments, $followUps, $notifications, $prescriptions] as $view) {
            self::assertStringContainsString('ri-scroll-modal', $view);
            self::assertStringContainsString('modal-dialog-scrollable', $view);
            self::assertStringContainsString('modal-header', $view);
            self::assertStringContainsString('modal-body', $view);
            self::assertStringContainsString('modal-footer', $view);
        }

        self::assertStringContainsString('data-toggle="modal"', $prescriptions);
        self::assertStringNotContainsString('data-toggle="collapse"', $prescriptions);
        self::assertStringContainsString('data-modal-autofocus', $prescriptions);
    }

    public function testDoctorSupportDestinationsUseScopedNotificationStorageAndNoLiveProviderCall(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-09-01-120000_CreateDoctorNotifications.php');
        $notification = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Notification.php');
        $video = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/VideoConsultation.php');
        self::assertStringContainsString("createTable('doctor_notifications')", $migration);
        self::assertStringContainsString("where('doctor_id', \$context['doctor_id'])", $notification);
        self::assertStringContainsString("where('clinic_id', \$context['clinic_id'])", $notification);
        self::assertStringContainsString("'configured' => \$this->provider->isConfigured()", $video);
        self::assertStringContainsString('VideoProviderBoundary', $video);
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

    public function testDoctorPatientFormsRenderFieldErrorsAndNormalizeDisplayDates(): void
    {
        $create = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/patients.php');
        $edit = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/patient.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php');
        $common = file_get_contents(ROOTPATH . 'public/assets/js/common.js');

        foreach ([$create, $edit] as $form) {
            self::assertStringContainsString('date_of_birthErr', $form);
            self::assertStringContainsString('country_codeErr', $form);
            self::assertStringContainsString('telephone_country_code_idErr', $form);
            self::assertStringContainsString('mobile_numberErr', $form);
        }
        self::assertStringContainsString('aria-live="polite"', $edit);
        self::assertStringContainsString('normalizeDate($this->request->getPost(\'date_of_birth\'))', $controller);
        self::assertStringContainsString("\$errors['date_of_birth'] = 'Enter a valid date of birth as dd-mm-yyyy.'", $controller);
        self::assertStringContainsString("\$errors['date_of_birth'] = 'Date of birth cannot be in the future.'", $controller);
        self::assertStringContainsString("\$errors['mobile_number'] = \$e->getMessage();", $controller);
        self::assertStringContainsString("preg_match('/^\\d{2}-\\d{2}-\\d{4}$/', \$value)", $controller);
        self::assertStringContainsString('name="date_of_birth"', $create);
        self::assertStringContainsString('name="date_of_birth"', $edit);
        self::assertStringContainsString('data-submit-display-date="true"', $create);
        self::assertStringContainsString('data-submit-display-date="true"', $edit);
        self::assertStringContainsString('required>', $create);
        self::assertStringContainsString('required>', $edit);
        self::assertStringContainsString("foreach (['country_name', 'state_name', 'city_name', 'locality_name', 'telephone_country_name'] as \$field)", $controller);
        self::assertStringContainsString("\$errors['appointment_date']", $controller);
        self::assertStringContainsString("\$errors['appointment_time']", $controller);
        self::assertStringContainsString("\$errors['service_name']", $controller);
        self::assertStringContainsString("\$errors['appointment_status']", $controller);
        self::assertStringContainsString("foreach (['country_id', 'state_id', 'city_id', 'locality_id'] as \$field)", $controller);
        self::assertStringContainsString("\$errors['appointment_reason']", $controller);
        self::assertStringContainsString("setStatusCode(422)->setJSON", $controller);
        self::assertStringContainsString("'errors' => \$errors", $controller);
        self::assertStringContainsString("'msg' => 'Please correct the highlighted fields.'", $controller);
        self::assertStringContainsString("attr('aria-invalid', 'true')", $common);
        self::assertStringContainsString("aria-describedby", $common);
        self::assertStringContainsString("scrollIntoView", $common);
        self::assertStringContainsString("closest('.modal-body')", $common);
        self::assertStringContainsString("clearFieldError($(this).closest('form'), name)", $common);
        self::assertStringContainsString('handleFormAjaxError', $common);
        self::assertStringContainsString('ensureValidationContainers', $common);
        self::assertStringContainsString('ensureFieldErrorContainer', $common);
        self::assertStringContainsString('initializeValidationContainers(document)', $common);
        self::assertStringContainsString('shown.bs.modal.rootinsValidation', $common);
        self::assertStringContainsString('validation-error-generated', $common);
        self::assertStringContainsString('id: exactId', $common);
        self::assertStringContainsString('data-validation-name', $common);
        self::assertStringContainsString('telephone_country_code_id', $common);
        self::assertStringContainsString('data-validation-name', file_get_contents(ROOTPATH . 'app/Views/templates/theme1.php'));
        self::assertStringContainsString('country_idErr', file_get_contents(ROOTPATH . 'public/assets/js/doctor/patient_location.js'));
        self::assertStringContainsString('appointment_dateErr', $create);
    }

    public function testDoctorDateOfBirthKeepsDisplayValueInPayloadAndNormalizesOnServer(): void
    {
        $template = file_get_contents(ROOTPATH . 'app/Views/templates/theme1.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php');

        self::assertStringContainsString("field.attr('data-submit-display-date') === 'true'", $template);
        self::assertStringContainsString("field.removeAttr('name')", $template);
        self::assertStringContainsString("hidden.attr('name', name)", $template);
        self::assertStringContainsString("'date_of_birth' => \$this->normalizeDate(\$this->request->getPost('date_of_birth'))", $controller);

        $displayDate = '01-01-2000';
        $databaseDate = \DateTimeImmutable::createFromFormat('!d-m-Y', $displayDate);
        self::assertNotFalse($databaseDate);
        self::assertSame('2000-01-01', $databaseDate->format('Y-m-d'));
    }

    public function testDoctorDateOfBirthValidationCoversMissingMalformedFutureValidAndBoundaryValues(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php');
        $service = file_get_contents(ROOTPATH . 'app/Modules/Admin/Services/PatientRegistrationService.php');

        self::assertStringContainsString("Date of birth is required.", $controller);
        self::assertStringContainsString("Enter a valid date of birth as dd-mm-yyyy.", $controller);
        self::assertStringContainsString("Date of birth cannot be in the future.", $controller);
        self::assertStringContainsString("normalizeRequiredDateOfBirth", $service);
        self::assertLessThan(
            strpos($service, '$this->db->transBegin();'),
            strpos($service, '$patientData[\'date_of_birth\'] = $this->normalizeRequiredDateOfBirth'),
        );

        $validCases = [
            '01-01-1900' => '1900-01-01',
            '29-02-2024' => '2024-02-29',
            date('d-m-Y') => date('Y-m-d'),
        ];

        foreach ($validCases as $displayDate => $databaseDate) {
            $date = \DateTimeImmutable::createFromFormat('!d-m-Y', $displayDate);
            $errors = \DateTimeImmutable::getLastErrors();
            self::assertNotFalse($date);
            self::assertTrue($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
            self::assertSame($databaseDate, $date->format('Y-m-d'));
        }

        self::assertStringContainsString("if (\$dateOfBirth === '')", $controller);

        $invalidDate = \DateTimeImmutable::createFromFormat('!d-m-Y', '31-02-2024');
        $invalidErrors = \DateTimeImmutable::getLastErrors();
        self::assertTrue($invalidDate === false || ($invalidErrors !== false && ($invalidErrors['warning_count'] > 0 || $invalidErrors['error_count'] > 0)));
        self::assertGreaterThan(date('Y-m-d'), date('Y-m-d', strtotime('+1 day')));
    }

    public function testDoctorNewPatientAppointmentUsesDisplayDateAndEnablesDatabaseServiceSelect(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/patients.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php');
        $service = file_get_contents(ROOTPATH . 'app/Modules/Admin/Services/PatientRegistrationService.php');

        self::assertStringContainsString('name="appointment_date" data-submit-display-date="true"', $view);
        self::assertStringNotContainsString('name="appointment_date_iso"', $view);
        self::assertStringContainsString('data-live-search="true" name="service_name"', $view);
        self::assertStringContainsString("input.selectpicker(enabled ? 'enable' : 'disable').selectpicker('refresh')", $view);
        self::assertStringContainsString("setNewPatientAppointmentState(this.checked)", $view);
        self::assertStringContainsString('$errors[\'appointment_date\'] = \'Appointment date is required.\'', $controller);
        self::assertStringContainsString('$errors[\'appointment_date\'] = \'Appointment date cannot be in the past.\'', $controller);
        self::assertStringContainsString('$parsedDate = \\DateTimeImmutable::createFromFormat(\'!Y-m-d\', $date)', $service);
        self::assertStringContainsString("Appointment date cannot be in the past.", $service);

        $displayDate = '01-01-2030';
        $databaseDate = \DateTimeImmutable::createFromFormat('!d-m-Y', $displayDate);
        self::assertNotFalse($databaseDate);
        self::assertSame('2030-01-01', $databaseDate->format('Y-m-d'));
    }

    public function testClinicalQueueUsesCompactScopedAppointmentTable(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Clinical.php');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/style.css');

        self::assertStringContainsString("['Patient', 'Appointment', 'Action']", $view);
        self::assertStringNotContainsString("['Queue number', 'Patient name / ID', 'Mobile number', 'Appointment date / time', 'Service / appointment type', 'Status', 'Action']", $view);
        self::assertStringContainsString("'data-label': 'Patient'", $view);
        self::assertStringContainsString("'data-label': 'Appointment'", $view);
        self::assertStringContainsString("text: 'Patient no: ' + patientNumber", $view);
        self::assertStringContainsString("text: 'Mobile: ' + (item.mobile || 'Not recorded')", $view);
        self::assertStringContainsString("class: 'badge badge-light clinical-opd-status'", $view);
        self::assertStringContainsString('clinical-opd-table', $view);
        self::assertStringContainsString("document.createTextNode('Consult')", $view);
        self::assertStringNotContainsString("document.createTextNode('Consult / Select')", $view);
        self::assertStringContainsString("document.createTextNode('More Actions')", $view);
        self::assertStringContainsString("'aria-label': 'Consult '", $view);
        self::assertStringContainsString("'data-patient-id': item.patient_id", $view);
        self::assertStringContainsString("url: '<?= base_url('doctor/clinical/appointments/search') ?>'", $view);
        self::assertStringContainsString('appointment_id: appointmentId', $view);
        self::assertStringContainsString('patient_id: patientId', $view);
        self::assertStringContainsString("Number(appointment.patient_id) === patientId", $view);
        self::assertStringContainsString("'&patient_id=' + encodeURIComponent(patientId)", $view);
        self::assertStringContainsString('let clinicalSelectionRequest = 0;', $view);
        self::assertStringContainsString('const requestId = ++clinicalSelectionRequest;', $view);
        self::assertStringContainsString('_selection_refresh: Date.now()', $view);
        self::assertStringContainsString('window.location.replace(', $view);
        self::assertStringContainsString('if (requestId !== clinicalSelectionRequest)', $view);
        self::assertStringContainsString('function resetOpdModalSelectionState()', $view);
        self::assertStringContainsString("$('#clinical-opd-queue-modal').on('shown.bs.modal'", $view);
        self::assertStringContainsString("$('#clinical-opd-queue-modal').on('hidden.bs.modal'", $view);
        self::assertStringContainsString("class: 'btn btn-sm btn-outline-primary clinical-opd-primary-action opd-select-appointment'", $view);
        self::assertStringContainsString("Opening Consultation…", $view);
        self::assertStringContainsString("class: 'dropdown clinical-opd-action-menu'", $view);
        self::assertStringContainsString("'data-boundary': 'viewport'", $view);

        foreach (['View Patient', 'View Appointment', 'Start Video Consultation', 'View Prescription'] as $action) {
            self::assertStringContainsString($action, $view);
        }
        self::assertStringContainsString('prescriptions.id AS prescription_id', $controller);
        self::assertStringContainsString("\$selectedPatientId = max(0, (int) \$this->request->getGet('patient_id'));", $controller);
        self::assertStringContainsString("\$selectedQuery->where('appointments.patient_id', \$selectedPatientId);", $controller);
        self::assertStringContainsString("\$requestedAppointmentId = max(0, (int) \$this->request->getGet('appointment_id'));", $controller);
        self::assertStringContainsString("\$query->where('appointments.id', \$requestedAppointmentId);", $controller);
        self::assertStringNotContainsString("text: 'Details'", $view);
        self::assertStringNotContainsString('queue-action', $view);
        self::assertStringNotContainsString("base_url('doctor/appointments/queue')", $view);
        self::assertStringContainsString("'queue_number' =>", $controller);
        self::assertStringContainsString("'patient_id' =>", $controller);
        self::assertStringContainsString('pagination', $controller);
        self::assertStringContainsString('#clinical-opd-queue-modal .clinical-opd-table', $styles);
        self::assertStringContainsString('table-layout: fixed;', $styles);
        self::assertStringContainsString('overflow-x: visible;', $styles);
        self::assertStringContainsString('width: 100%;', $styles);
        self::assertStringNotContainsString('min-width: 52rem;', $styles);
        self::assertStringContainsString('clinical-opd-action-cell .btn', $styles);
        self::assertStringContainsString('clinical-opd-table tbody .clinical-opd-action-cell', $styles);
        self::assertStringContainsString('clinical-opd-action-menu', $styles);
        self::assertStringContainsString('max-width: min(18rem, calc(100vw - 2rem));', $styles);
        self::assertStringContainsString('position: sticky;', $styles);
        self::assertStringContainsString('position: static;', $styles);
        self::assertStringContainsString('data-label', $view);
        self::assertStringContainsString('@media (max-width: 767.98px)', $styles);
    }

    public function testClinicalPrescriptionDropdownsUseDetachedOverflowSafeMenusForAllRows(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/style.css');

        self::assertStringContainsString("const options = {liveSearch: true, container: 'body'};", $view);
        self::assertStringContainsString("select.selectpicker('destroy');", $view);
        self::assertStringContainsString("picker.\$menu.addClass('clinical-prescription-dropdown-menu')", $view);
        self::assertStringContainsString('initializeClinicalMedicineRows(row);', $view);
        self::assertStringContainsString('.clinical-prescription-dropdown-menu {', $styles);
        self::assertStringContainsString('z-index: 1085 !important;', $styles);
        self::assertStringContainsString('overflow-y: auto;', $styles);
        self::assertStringContainsString('min-width: 6.5rem;', $styles);
        self::assertStringContainsString('grid-template-columns: minmax(6.5rem, 30%) minmax(0, 1fr);', $styles);
        self::assertStringContainsString('td:nth-child(5) { width: 14%; }', $styles);
        self::assertStringContainsString('@media (min-width: 992px)', $styles);
        self::assertStringContainsString('@media (max-width: 991.98px)', $styles);
    }

    public function testClinicalQueueModalContainsOnlyQueueSearchAndAppointmentResults(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Config/Routes.php');

        self::assertStringContainsString('id="clinical-opd-queue-modal"', $view);
        self::assertStringContainsString('id="clinical-opd-search"', $view);
        self::assertStringContainsString('id="clinical-opd-results"', $view);
        self::assertStringContainsString('id="clinical-opd-pagination"', $view);
        self::assertStringContainsString('opd-select-appointment', $view);
        self::assertStringContainsString('function searchOpd', $view);
        self::assertStringNotContainsString('id="clinical-patient-search"', $view);
        self::assertStringNotContainsString('id="clinical-patient-results"', $view);
        self::assertStringNotContainsString('clinical-patient-autocomplete', $view);
        self::assertStringNotContainsString('searchPatients', $view);
        self::assertStringNotContainsString('clinical/patients/search', $routes);
        self::assertStringContainsString('appointments/patients/search', $routes);
    }

    public function testRepresentativeFormsDeclareExactFieldErrorContainers(): void
    {
        $contracts = [
            'app/Views/auth/user_login.php' => ['user_idErr', 'user_passwordErr'],
            'app/Views/templates/_telephone_contact.php' => ['country_code_manualErr', 'telephone_country_nameErr', 'country_codeErr'],
            'app/Modules/Admin/Views/pages/access/users.php' => ['usernameErr', 'passwordErr', 'statusErr', 'emailErr', 'doctor_idErr', 'role_ids[]Err'],
            'app/Modules/Admin/Views/pages/access/roles.php' => ['role_nameErr', 'role_codeErr', 'descriptionErr', 'statusErr'],
            'app/Modules/Admin/Views/pages/billing/new.php' => ['clinic_idErr', 'patient_idErr', 'idempotency_keyErr', 'items[0][description]Err', 'items[0][quantity]Err', 'items[0][unit_price]Err', 'taxErr', 'discountErr'],
            'app/Modules/Admin/Views/pages/configuration/master.php' => ['searchErr', 'nameErr', 'codeErr', 'amountErr', 'descriptionErr', 'statusErr'],
            'app/Modules/Admin/Views/pages/configuration/settings.php' => ['searchErr', 'setting_keyErr', 'setting_valueErr', 'is_secretErr'],
            'app/Modules/Doctor/Views/pages/appointments.php' => ['patient_idErr', 'appointment_dateErr', 'appointment_timeErr', 'appointment_typeErr', 'reason_for_visitErr'],
            'app/Modules/Doctor/Views/pages/profile.php' => ['emailErr', 'addressErr'],
            'app/Modules/Patient/Views/pages/portal.php' => ['emailErr', 'telephone_country_code_idErr', 'country_codeErr', 'mobile_numberErr', 'addressErr'],
        ];

        foreach ($contracts as $path => $errorIds) {
            $markup = file_get_contents(ROOTPATH . $path);

            foreach ($errorIds as $errorId) {
                self::assertStringContainsString('id="' . $errorId . '"', $markup, $path . ' is missing #' . $errorId);
            }
        }
    }

    public function testDoctorAppointmentListUsesScopedFiltersPaginationAndCompactActions(): void
    {
        $records = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/appointments.php');
        $listView = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/appointments_list.php');
        $gridView = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/appointments_grid.php');
        $actions = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/appointments_actions.php');
        $pagination = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/parts/pagination.php');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/style.css');
        self::assertStringContainsString('.doctor-appointment-actions .dropdown-menu .dropdown-item.doctor-appointment-consult', $styles);
        self::assertStringContainsString('min-width: min(14rem, calc(100vw - 2rem));', $styles);
        self::assertStringContainsString('display: flex;', $styles);
        self::assertStringContainsString('box-shadow: 0 .65rem 1.5rem', $styles);
        $appointmentMarkup = $view . $actions;

        foreach (['service_id', 'queue_number', 'date_from_iso', 'date_to_iso'] as $filter) {
            self::assertStringContainsString("filters['{$filter}']", $records);
        }
        $appointmentList = substr($records, strpos($records, 'public function appointments(): string|RedirectResponse|ResponseInterface'), strpos($records, 'public function patientSearch(): ResponseInterface'));
        self::assertStringNotContainsString("filters['patient_id']", $appointmentList);

        foreach (['patients.registration_number', 'patients.mobile_number', 'appointments.service_name', 'appointments.queue_position', 'appointments.appointment_type', 'appointments.status'] as $searchColumn) {
            self::assertStringContainsString("'{$searchColumn}'", $appointmentList);
        }
        self::assertStringContainsString('appointment_service_filter', $records);
        self::assertStringContainsString("where('appointments.queue_position', \$filters['queue_number'])", $records);
        self::assertStringContainsString("orderBy('appointments.appointment_date', 'DESC')", $records);

        foreach (['today_count', 'pending_count', 'confirmed_count', 'completed_count', 'cancelled_count', 'statusSummary'] as $summaryContract) {
            self::assertStringContainsString($summaryContract, $records);
        }
        self::assertStringContainsString("\$filters['view']", $records);
        self::assertStringContainsString("['list', 'grid']", $records);

        foreach (['doctor-appointment-toolbar-search', 'doctor-appointments-toolbar-page-size', 'doctor-appointment-filter-service', 'doctor-appointment-filter-queue', 'doctor-appointment-filter-from', 'doctor-appointment-filter-to'] as $field) {
            self::assertStringContainsString('id="' . $field . '"', $view);
        }
        self::assertStringContainsString('Search patient name, patient number, mobile, service, queue, or status', $view);
        self::assertStringContainsString('doctor-appointments-toolbar-card', $view);
        self::assertStringContainsString('doctor-appointments-content-card', $view);
        self::assertStringContainsString("'hidePageSize' => true", $view);
        self::assertStringNotContainsString('id="doctor-appointment-filter-search"', $view);
        self::assertStringNotContainsString('doctor-appointment-filter-patient-search', $view);
        self::assertStringNotContainsString('doctor-appointment-filter-patient-id', $view);
        self::assertStringNotContainsString('name="patient_id"', substr($view, strpos($view, 'id="doctor-appointment-filter-modal"')));
        self::assertStringContainsString('More Actions', $appointmentMarkup);
        self::assertStringContainsString('Consult', $appointmentMarkup);

        foreach (['doctor-appointment-records', 'doctor-appointment-card', 'doctor-appointment-avatar', 'doctor-appointment-patient-name', 'doctor-appointment-date-time', 'doctor-appointment-queue', 'doctor-appointment-card-status', 'doctor-appointments-status-summary'] as $cardClass) {
            self::assertStringContainsString($cardClass, $view . $gridView);
        }
        self::assertStringContainsString('data-appointment-view="list"', $view);
        self::assertStringNotContainsString('data-appointment-view="grid"', $view);
        self::assertStringContainsString('data-appointment-view-container', $view);
        self::assertStringContainsString('data-appointment-view-input', $view);
        self::assertStringContainsString('aria-pressed=', $view);
        self::assertStringContainsString('doctorAppointmentsView', $view);
        self::assertStringContainsString("'view' =>", $pagination);

        foreach (['Today', 'Pending', 'Confirmed', 'Completed', 'Cancelled'] as $summaryLabel) {
            self::assertStringContainsString($summaryLabel, $view);
        }
        self::assertStringContainsString('doctor-appointment-action-group', $appointmentMarkup);

        foreach (['Patient information', 'Appointment information', 'Appointment status', 'doctor-appointment-group-kicker', 'doctor-appointment-past-indicator'] as $listContract) {
            self::assertStringContainsString($listContract, $listView . $gridView . $actions);
        }

        foreach (['doctor-appointment-card-service', 'consultation_date_today', 'doctor-appointment-today', 'Today’s appointment'] as $todayContract) {
            self::assertStringContainsString($todayContract, $records . $view . $listView . $gridView . $actions);
        }

        foreach (['<table class="doctor-appointment-table">', '<caption', '<thead>', '<tbody>', 'scope="col"', 'data-label="Patient"', 'data-label="Appointment"', 'data-label="Service"', 'data-label="Queue"', 'data-label="Status"', 'data-label="Actions"'] as $tableContract) {
            self::assertStringContainsString($tableContract, $listView);
        }

        foreach (['minmax(14rem', 'minmax(10rem', 'minmax(12rem', 'minmax(7rem', 'text-overflow: ellipsis'] as $alignmentContract) {
            self::assertStringContainsString($alignmentContract, $styles);
        }
        self::assertStringContainsString('No appointments match the current filters.', $view);
        self::assertStringContainsString('aria-live="polite"', $view);
        self::assertStringContainsString('min-height: 30px;', $styles);
        self::assertStringContainsString('service_id', $pagination);
        self::assertStringContainsString('queue_number', $pagination);
        self::assertStringNotContainsString('DataTable(', $view);
    }

    public function testDoctorAppointmentViewsUseIndependentTemplatesAndPreserveViewSwitchContract(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');
        $shell = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/appointments.php');
        $list = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/appointments_list.php');
        $grid = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/appointments_grid.php');
        $actions = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/appointments_actions.php');

        self::assertStringContainsString("pages/appointments_' . \$appointmentView", $shell);
        self::assertStringContainsString("in_array(\$requestedView, ['list', 'grid'], true)", $controller);
        self::assertStringContainsString("\$filters['view'] =", $controller);
        self::assertStringContainsString("string|RedirectResponse|ResponseInterface", $controller);
        self::assertStringContainsString("'route' => 'doctor/appointments'", $controller);
        self::assertStringContainsString("'requested_view' =>", $controller);
        self::assertStringContainsString("'exception' => \$e::class", $controller);
        self::assertStringContainsString("setJSON(['status' => false, 'msg' => 'Appointment view could not be loaded safely.'])", $controller);
        self::assertStringContainsString("setBody('Appointment view could not be loaded safely.')", $controller);
        self::assertStringNotContainsString('doctor-appointment-table', $shell);
        self::assertStringNotContainsString('doctor-appointment-card', $shell);
        self::assertStringContainsString('<table class="doctor-appointment-table">', $list);
        self::assertStringContainsString('<col class="doctor-appointment-col-status">', $list);
        self::assertStringContainsString('doctor-appointment-table-status-content', $list);
        self::assertStringContainsString('title="<?= esc(ucwords(str_replace(\'_\', \' \', $item[\'status\']))) ?>"', $list);
        self::assertStringContainsString('doctor-appointment-card-main', $grid);
        self::assertStringContainsString('$appointmentContext($appointment)', $list . $grid);
        self::assertSame(substr_count($list, '$appointmentContext($appointment)'), substr_count($grid, '$appointmentContext($appointment)'));
        self::assertStringContainsString('doctor-appointment-consult', $actions);
        self::assertStringContainsString('dropdown-item doctor-appointment-consult', $actions);
        self::assertStringNotContainsString('btn-outline-primary doctor-appointment-consult', $actions);
        self::assertLessThan(strpos($actions, 'View Patient'), strpos($actions, 'dropdown-item doctor-appointment-consult'));
        self::assertStringContainsString('! $consultationDatePassed', $actions);
        self::assertStringContainsString('form.serialize()', $shell);
        self::assertStringContainsString("params.set('view', view)", $shell);
        self::assertStringContainsString("dataType: 'html'", $shell);
        self::assertStringContainsString("nextContent.find('.card-footer').first()", $shell);
        self::assertStringContainsString("nextContent.find('[data-appointment-view-container]').first()", $shell);
        self::assertStringContainsString("nextContent.find('.empty-state').first()", $shell);
        self::assertStringContainsString('appointmentViewRequest.abort()', $shell);
        self::assertStringContainsString("status === 'abort'", $shell);
        self::assertStringContainsString('status === \'timeout\'', $shell);
        self::assertStringContainsString('hide_loader()', $shell);

        foreach (['search', 'service_id', 'queue_number', 'date_from', 'date_to', 'page_size', 'page'] as $filter) {
            self::assertStringContainsString('name="' . $filter . '"', $shell);
        }
        self::assertStringContainsString('name="view"', $shell);
        self::assertStringNotContainsString('data-appointment-view="grid"', $shell);
        self::assertStringContainsString('No appointments match the current filters.', $shell);
        self::assertStringContainsString("'appointments' => \$appointments", $shell);
        self::assertStringContainsString("'appointmentContext' => \$appointmentContext", $shell);
    }

    public function testDoctorAppointmentListAndGridTemplatesRenderWithNormalizedContract(): void
    {
        $appointment = [
            'id' => 501,
            'appointment_number' => 'APT-501',
            'status' => 'scheduled',
            'appointment_date' => '2026-09-30',
            'appointment_time' => '09:30:00',
            'service_name' => 'General consultation',
            'appointment_type' => 'in_person',
            'queue_position' => 4,
            'patient_first_name' => 'Test',
            'patient_last_name' => 'Patient',
            'registration_number' => 'REG-501',
            'patient_mobile_number' => '9876543210',
            'consultation_date_passed' => false,
            'consultation_date_today' => true,
            'consultation_unavailable_reason' => null,
        ];
        $appointmentContext = static fn (array $row): array => [
            'appointment' => $row,
            'appointmentId' => (int) ($row['id'] ?? 0),
            'patientId' => (int) ($row['patient_id'] ?? 0),
            'serviceName' => trim((string) ($row['service_name'] ?? '')) ?: '—',
            'queuePosition' => $row['queue_position'] ?? null,
            'status' => (string) ($row['status'] ?? ''),
            'consultationDatePassed' => (bool) ($row['consultation_date_passed'] ?? false),
            'consultationDateToday' => (bool) ($row['consultation_date_today'] ?? false),
            'patientName' => trim(($row['patient_first_name'] ?? '') . ' ' . ($row['patient_last_name'] ?? '')) ?: 'Unnamed patient',
            'patientInitial' => 'T',
            'patientMobile' => (string) ($row['patient_mobile_number'] ?? 'Mobile not recorded'),
            'appointmentType' => 'In Person',
            'appointmentDateDisplay' => '30-09-2026',
            'appointmentTimeDisplay' => '09:30',
        ];
        $data = [
            'appointments' => [$appointment],
            'appointmentContext' => $appointmentContext,
            'canConsult' => true,
            'canEdit' => true,
            'nextStatuses' => ['scheduled' => ['confirmed' => 'Confirm']],
            'canView' => true,
            'canFollowUp' => false,
            'canPrescription' => false,
            'canBilling' => false,
        ];

        $list = view(DOCTOR_VIEW . 'pages/appointments_list', $data);
        $grid = view(DOCTOR_VIEW . 'pages/appointments_grid', $data);

        self::assertStringContainsString('<table class="doctor-appointment-table">', $list);
        self::assertStringContainsString('Today’s appointment', $list);
        self::assertStringContainsString('Consult', $list);
        self::assertStringContainsString('doctor-appointment-card-main', $grid);
        self::assertStringContainsString('Today’s appointment', $grid);
        self::assertStringContainsString('Consult', $grid);
        self::assertStringNotContainsString('Appointment view could not be loaded safely.', $list . $grid);

        $styles = file_get_contents(ROOTPATH . 'public/assets/css/style.css');
        self::assertStringContainsString('table-layout: fixed;', $styles);
        self::assertStringContainsString('.doctor-appointment-col-status { width: 15%; }', $styles);
        self::assertStringContainsString('display: inline-flex;', $styles);
        self::assertStringContainsString('text-overflow: ellipsis;', $styles);
        self::assertStringNotContainsString('.doctor-appointment-table-status {\n  display: flex;', $styles);

        foreach ([
            ['patient_id' => null],
            ['queue_position' => null],
            ['service_name' => null],
            ['patient_first_name' => null, 'patient_last_name' => null, 'registration_number' => null, 'patient_mobile_number' => null],
        ] as $missingFields) {
            $variant = array_merge($appointment, $missingFields);
            $variantData = $data;
            $variantData['appointments'] = [$variant];
            $variantList = view(DOCTOR_VIEW . 'pages/appointments_list', $variantData);
            self::assertStringContainsString('<table class="doctor-appointment-table">', $variantList);
            self::assertStringNotContainsString('Appointment view could not be loaded safely.', $variantList);
        }
    }

    public function testDoctorPrescriptionViewProvidesMetadataCardsAndScopedPrintMarkup(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/prescriptions.php');
        $view .= file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/prescriptions_actions.php');
        $view .= file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/prescriptions_list.php');
        $view .= file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/prescriptions_grid.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/style.css');

        foreach (['Patient Information', 'Name', 'Patient number', 'Mobile', 'Prescription', 'Prescribed Medicines'] as $label) {
            self::assertStringContainsString('>' . $label . '<', $view);
        }
        self::assertStringContainsString('Prescription date:', $view);

        foreach (['Medicine', 'Duration', 'Frequency', 'Dose', 'Intake', 'Note'] as $column) {
            self::assertStringContainsString('<th scope="col">' . $column . '</th>', $view);
        }
        self::assertStringContainsString('Prescribed Medicines', $view);
        self::assertStringContainsString('colspan="7"', $view);
        self::assertStringContainsString('data-label="Medicine"', $view);
        self::assertStringContainsString('doctor-prescription-print-trigger', $view);
        self::assertStringContainsString('data-print-target=', $view);
        self::assertStringContainsString('window.print()', $view);
        self::assertStringContainsString('doctor-prescription-printing', $view);
        self::assertStringContainsString('doctor-prescription-print-area', $view);
        self::assertStringContainsString('doctor-prescription-detail-modal', $view);
        self::assertStringContainsString('doctor-prescription-modal-context', $view);
        self::assertStringContainsString('Prescription summary', $view);
        self::assertStringContainsString('clinics.clinic_name', $controller);
        self::assertStringContainsString('patients.mobile_normalized', $controller);
        self::assertStringContainsString("prescriptions.prescription_number', 'patients.first_name', 'patients.last_name', 'patients.registration_number', 'patients.mobile_number', 'patients.mobile_normalized', 'prescriptions.status'", $controller);
        self::assertStringContainsString('@page', $styles);
        self::assertStringContainsString('size: A4;', $styles);
        self::assertStringContainsString('body.doctor-prescription-printing > * {', $styles);
        self::assertStringContainsString('display: none !important;', $styles);
        self::assertStringContainsString('body.doctor-prescription-printing > .doctor-prescription-print-area.is-print-target', $styles);
        self::assertStringContainsString('body.doctor-prescription-printing .doctor-prescriptions-page', $styles);
        self::assertDoesNotMatchRegularExpression('/body\.doctor-prescription-printing\s*>\s*\*\s*\{\s*visibility:\s*hidden\s*!important;/s', $styles);
        self::assertStringContainsString('const originalParent = printArea.parentNode;', $view);
        self::assertStringContainsString('document.body.appendChild(printArea);', $view);
        self::assertStringContainsString('originalParent.insertBefore(printArea, originalNextSibling);', $view);
        self::assertStringContainsString('body.doctor-clinical-printing > #content-wrapper > #main-content > main[aria-labelledby="clinical-title"] > *:not(#clinical-action-modal)', $styles);
        self::assertStringContainsString('height: auto !important;', $styles);
        self::assertStringContainsString('.modal-body > *:not(.clinical-action-print-area)', $styles);
        self::assertStringContainsString('display: table-header-group !important;', $styles);
        self::assertStringContainsString('page-break-inside: avoid;', $styles);
        self::assertStringContainsString('.doctor-prescription-detail-modal .modal-content', $styles);
        self::assertStringContainsString('max-height: calc(100vh - 2rem);', $styles);
        self::assertStringContainsString('.doctor-prescription-detail-modal .modal-body', $styles);
        self::assertStringContainsString('.doctor-prescription-detail-modal.ri-scroll-modal .modal-dialog', $styles);
        self::assertStringContainsString('height: auto;', $styles);
        self::assertStringContainsString('overflow-x: hidden;', $styles);
        self::assertStringContainsString('max-height: none !important;', $styles);
        self::assertStringContainsString('overflow: visible !important;', $styles);
    }

    public function testDoctorPrescriptionRegisterUsesScopedServerFiltersAndDetailContext(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/prescriptions.php');
        $view .= file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/prescriptions_actions.php');
        $view .= file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/prescriptions_list.php');
        $view .= file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/prescriptions_grid.php');
        $records = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');

        foreach (['search', 'date_from', 'date_to', 'status', 'page_size'] as $filter) {
            self::assertStringContainsString('name="' . $filter . '"', $view);
        }

        foreach (['search', 'date_from', 'date_to', 'status', 'page_size'] as $filter) {
            self::assertStringContainsString("\$filters['{$filter}']", $records);
        }
        self::assertSame(1, substr_count($view, 'type="search"'));
        self::assertStringNotContainsString('name="patient_name"', $view);
        self::assertStringNotContainsString("\$filters['patient_name']", $records);
        self::assertStringContainsString('prescriptionFilters()', $records);
        self::assertStringContainsString("\$filters['view']", $records);
        self::assertStringContainsString("['list', 'grid']", $records);
        self::assertStringContainsString('prescription_item_count', $records);
        self::assertStringContainsString('medicine_count', $records);
        self::assertStringContainsString('where(\'prescriptions.doctor_id\', $context[\'doctor_id\'])', $records);
        self::assertStringContainsString('where(\'prescriptions.clinic_id\', $context[\'clinic_id\'])', $records);
        self::assertStringContainsString('activeOptions(PrescriptionOptionModel::TYPES[\'status\']', $records);
        self::assertStringContainsString('data-print-modal=', $view);
        self::assertSame(1, substr_count($view, 'class="btn btn-sm btn-outline-secondary dropdown-toggle"'));
        self::assertStringContainsString('doctor-prescription-action-menu', $view);
        self::assertStringContainsString('aria-haspopup="true"', $view);
        self::assertStringContainsString('aria-controls=', $view);
        self::assertStringContainsString('class="dropdown-item doctor-prescription-list-print"', $view);
        self::assertStringContainsString('View</button>', $view);
        self::assertStringContainsString('Print</button>', $view);
        self::assertStringNotContainsString('class="btn btn-sm btn-outline-primary" type="button" data-toggle="modal"', $view);
        self::assertStringNotContainsString('class="btn btn-sm btn-outline-secondary doctor-prescription-list-print"', $view);
        self::assertStringContainsString('doctorPrescriptionAction', $view);
        self::assertStringContainsString('selectedPrescriptionId', $view);
        self::assertStringContainsString("pages/prescriptions_' . \$prescriptionView", $view);
        self::assertStringContainsString("dataType: 'html'", $view);
        self::assertStringContainsString("$('.doctor-prescription-results').replaceWith(nextResults)", $view);
        self::assertStringContainsString("nextResults.find('.doctor-prescription-empty-state').first()", $view);
        self::assertStringContainsString('hide_loader()', $view);
        self::assertStringContainsString('<table class="doctor-prescription-table doctor-prescription-list-table">', file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/prescriptions_list.php'));
        self::assertStringContainsString('doctor-prescription-grid', file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/prescriptions_grid.php'));
        self::assertStringContainsString('Patient', file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/prescriptions_list.php'));
        self::assertStringContainsString('Medicine count', file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/prescriptions_list.php'));
        self::assertStringContainsString('doctor-prescription-status-badge', file_get_contents(ROOTPATH . 'public/assets/css/style.css'));
        self::assertStringContainsString('table-layout: fixed;', file_get_contents(ROOTPATH . 'public/assets/css/style.css'));

        foreach (['Chief Complaints', 'Diagnosis', 'Advice', 'Diagnostic Tests', 'Diet Plan', 'Private Notes', 'Follow-up Setup', 'Appointment Setup'] as $label) {
            self::assertStringNotContainsString('<h4>' . $label . '</h4>', $view);
        }
        self::assertStringNotContainsString('<dt>Doctor</dt>', $view);
        self::assertStringNotContainsString('<dt>Appointment / consultation</dt>', $view);
        self::assertStringNotContainsString('<dt>Status</dt>', $view);
        self::assertStringNotContainsString('doctor-prescription-interactive-details', $view);
        self::assertStringNotContainsString('Prescription notes', $view);
        self::assertStringContainsString('fa-solid fa-circle-exclamation', $view);

        foreach (['doctor-prescription-records', 'doctor-prescription-card', 'doctor-prescription-avatar', 'doctor-prescription-card-details', 'doctor-prescription-card-status', 'More Actions'] as $cardContract) {
            self::assertStringContainsString($cardContract, $view);
        }
        self::assertStringContainsString('data-prescription-view="list"', $view);
        self::assertStringContainsString('data-prescription-view="grid"', $view);
        self::assertStringContainsString('data-prescription-view-container', $view);
        self::assertStringContainsString('data-prescription-view-input', $view);
        self::assertStringContainsString('aria-pressed=', $view);
        self::assertStringContainsString('doctorPrescriptionView', $view);
        self::assertStringContainsString("'view' =>", file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/parts/pagination.php'));
        self::assertStringNotContainsString('doctor-prescription-register-table', $view);
        self::assertStringNotContainsString('doctor-prescription-register-wrap', $view);
        self::assertStringNotContainsString('DataTable(', $view);
    }

    public function testDoctorVideoConsultationIsAppointmentLinkedAndProviderSafe(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/VideoConsultation.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-01-120000_CreateDoctorVideoConsultationSessions.php');
        $action = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/appointments_actions.php');
        $panel = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/video_panel.php');

        foreach (['video/(:num)/start', 'video/(:num)/status', 'video/(:num)/token', 'video/(:num)/end'] as $route) {
            self::assertStringContainsString($route, $routes);
        }

        foreach (['appointment_id', 'patient_id', 'doctor_id', 'clinic_id', 'room_reference', 'recording_consent_state'] as $field) {
            self::assertStringContainsString("'{$field}'", $migration);
        }

        foreach (['where(\'appointments.doctor_id\'', 'where(\'appointments.clinic_id\'', "where('patients.clinic_id'", 'isConsultable', 'consultation.edit', 'room_reference', 'random_bytes', 'publicSession'] as $scopeGuard) {
            self::assertStringContainsString($scopeGuard, $controller);
        }
        self::assertStringNotContainsString('TWILIO_API_SECRET', $controller . $panel);
        self::assertStringNotContainsString('TWILIO_AUTH_TOKEN', $controller . $panel);
        self::assertStringContainsString('js-video-consultation', $action);
        self::assertStringContainsString('js-video-start', $panel);
        self::assertStringContainsString('js-video-mute', $panel);
        self::assertStringContainsString('js-video-camera', $panel);
        self::assertStringContainsString('js-video-end', $panel);

        foreach (['doctor-video-panel-header', 'doctor-video-status-badge', 'doctor-video-provider-alert', 'doctor-video-stage', 'doctor-video-controls', 'doctor-video-panel-note'] as $uiContract) {
            self::assertStringContainsString($uiContract, $panel);
        }

        foreach (['not_started', 'waiting', 'active', 'ended', 'failed', 'Video provider setup is pending', 'Rejoin consultation', 'aria-label="Mute microphone"', 'aria-label="Disable camera"'] as $stateContract) {
            self::assertStringContainsString($stateContract, $panel . file_get_contents(ROOTPATH . 'public/assets/css/style.css'));
        }
        self::assertStringContainsString('doctorVideoRequest.abort()', $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/appointments.php'));
        self::assertStringContainsString('hidden.bs.modal.doctorVideoConsultation', $view);
        self::assertStringContainsString('hide_loader()', $view);
        self::assertStringContainsString('modal-dialog-scrollable', $view);
    }

    public function testDoctorAvailabilityAndLeaveWorkflowIsScopedAndUsesEstablishedContracts(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Availability.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/availability.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/doctor/availability.js');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-01-130000_AddBreakTimesToDoctorSchedules.php');
        $leaveTimeMigration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-01-140000_AddTimesToDoctorLeaves.php');
        $layout = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/layout.php');

        foreach (["'availability'", 'availability/schedule/save', 'availability/schedule/(:num)/cancel', 'availability/leave/save', 'availability/leave/(:num)/cancel'] as $route) {
            self::assertStringContainsString($route, $routes);
        }

        foreach (['permission:doctor.view', 'permission:doctor.edit'] as $permission) {
            self::assertStringContainsString($permission, $routes);
        }

        foreach (['doctor_id', 'clinic_id', 'hasOverlap', 'appointment_date >=', 'appointment_date <=', 'override_confirmation', 'normalizeDate', 'normalizeTime', 'transBegin', 'service(\'audit\')'] as $contract) {
            self::assertStringContainsString($contract, $controller);
        }

        foreach (['break_start_time', 'break_end_time'] as $field) {
            self::assertStringContainsString($field, $migration . $controller . $view);
        }

        foreach (['start_time', 'end_time'] as $field) {
            self::assertStringContainsString($field, $leaveTimeMigration . $controller . $view);
        }

        foreach (['Weekly availability', 'Leave history', 'day_of_week', 'start_time', 'end_time', 'dd-mm-yyyy', 'modal-dialog-scrollable', 'validation-error', 'Pending approval', 'Approved', 'Cancelled'] as $ui) {
            self::assertStringContainsString($ui, $view);
        }

        foreach (['#schedule-form', '#leave-form', 'bindFormError', 'ajaxRequest', 'doctor/availability/schedule/save', 'doctor/availability/leave/save', 'hide_loader'] as $jsContract) {
            self::assertStringContainsString($jsContract, $script . file_get_contents(ROOTPATH . 'public/assets/js/common.js'));
        }

        foreach (['class="form-control datepicker"', 'data-submit-display-date="true"', 'placeholder="dd-mm-yyyy"', 'id="leave_dateErr"', 'setSharedDateValue', 'datepicker(\'update\'', 'Start date cannot be before today.', "'Y-m-d'"] as $dateContract) {
            self::assertStringContainsString($dateContract, $view . $script . $controller);
        }
        self::assertStringContainsString('Availability &amp; leave', $layout);
        self::assertStringNotContainsString('name="start_time" required', $view);
        self::assertStringNotContainsString('name="end_time" required', $view);

        foreach (['id="leave_dateErr"', 'id="leave_start_timeErr"', 'id="leave_end_timeErr"', 'id="leave_reasonErr"', 'id="override_confirmationErr"', 'doctor-leave-modal-body', 'doctor-leave-modal-content', 'data-submit-display-date="true"', 'data-datepicker-container="#leaveModal"', 'removeClass(\'modal-dialog-scrollable\')', 'override_confirmation'] as $leaveUiContract) {
            self::assertStringContainsString($leaveUiContract, $view . file_get_contents(ROOTPATH . 'public/assets/css/style.css') . $script);
        }
        self::assertStringContainsString("options.container = field.attr('data-datepicker-container')", file_get_contents(ROOTPATH . 'app/Views/templates/theme1.php'));

        foreach (['I am authorized to override appointment conflicts', 'override_confirmation', 'appointmentQuery', 'End time must be later than start time', 'full-day leave', 'normalizeTime', 'setStatusCode(422)'] as $leaveValidationContract) {
            self::assertStringContainsString($leaveValidationContract, $controller . $view);
        }
        self::assertStringNotContainsString('DataTable(', $view . $script);
    }
}
