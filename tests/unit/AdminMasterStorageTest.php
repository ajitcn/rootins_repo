<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdminMasterStorageTest extends TestCase
{
    private const TABLES = [
        'admin_departments',
        'admin_services',
        'consultation_fee_templates',
        'commission_templates',
        'payment_modes',
        'tax_rates',
        'prescription_durations',
        'prescription_frequencies',
        'prescription_doses',
        'prescription_intakes',
        'prescription_status_options',
    ];

    public function testMigrationDefinesDedicatedCatalogsAndKeepsLegacyMapping(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-09-11-120000_RefactorAdminMasterRecords.php');

        foreach (self::TABLES as $table) {
            self::assertStringContainsString("'{$table}'", $migration);
        }
        self::assertStringContainsString("'legacy_master_id'", $migration);
        self::assertStringContainsString("'normalized_name'", $migration);
        self::assertStringContainsString("addUniqueKey(['scope_key', 'normalized_name'])", $migration);
        self::assertStringContainsString("addForeignKey('clinic_id', 'clinics'", $migration);
        self::assertStringContainsString("addForeignKey('created_by', 'users'", $migration);
        self::assertStringContainsString('moveAppointmentServiceForeignKey', $migration);
        self::assertStringContainsString('restoreAppointmentServiceForeignKey', $migration);
    }

    public function testDedicatedModelsUseTheirOwnTables(): void
    {
        foreach (['Department', 'Service', 'ConsultationFeeTemplate', 'CommissionTemplate', 'PaymentMode', 'TaxRate', 'PrescriptionDuration', 'PrescriptionFrequency', 'PrescriptionDose', 'PrescriptionIntake', 'PrescriptionStatus'] as $model) {
            $path = ROOTPATH . 'app/Modules/Admin/Models/Configuration/' . $model . 'Model.php';
            self::assertFileExists($path);
            self::assertStringContainsString('extends ScopedCatalogModel', file_get_contents($path));
        }

        $service = file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/Configuration/ServiceModel.php');
        self::assertStringContainsString("'admin_services'", $service);
        self::assertStringContainsString("'status'", file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/Configuration/ScopedCatalogModel.php'));
    }

    public function testPrescriptionOptionsReadDedicatedTables(): void
    {
        $options = file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/PrescriptionOptionModel.php');

        foreach (['prescription_durations', 'prescription_frequencies', 'prescription_doses', 'prescription_intakes', 'prescription_status_options'] as $table) {
            self::assertStringContainsString("'{$table}'", $options);
        }
        self::assertStringNotContainsString("table('admin_master_records')", $options);
    }

    public function testConfigurationAndDoctorConsumersUseDedicatedCatalogs(): void
    {
        $configuration = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Configuration.php');
        $records = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');
        $patient = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Patient.php');
        $seeder = file_get_contents(ROOTPATH . 'app/Database/Seeds/AdminConfigurationSeeder.php');

        self::assertStringContainsString('DoctorSpecializationModel::class', $configuration);
        self::assertStringContainsString('Admin\\Models\\Configuration\\ServiceModel', $records);
        self::assertStringContainsString('Admin\\Models\\Configuration\\ServiceModel', $patient);
        self::assertStringNotContainsString("table('admin_master_records')->", $seeder);
        self::assertStringContainsString("'legacy_master_id'", $configuration . file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-09-11-120000_RefactorAdminMasterRecords.php'));
    }

    public function testMedicineCreateUsesSafeDuplicateCheckAndDedicatedDefaults(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Medicine/Medicine.php');
        $model = file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/MedicineModel.php');

        self::assertStringContainsString('$normalizedName = mb_strtolower($name, \'UTF-8\');', $controller);
        self::assertStringContainsString("'LOWER(TRIM(medicine_name)) = ' . \$db->escape(\$normalizedName)", $controller);
        self::assertStringNotContainsString("where('LOWER(TRIM(medicine_name))', mb_strtolower(\$name, 'UTF-8'), false)", $controller);
        self::assertStringContainsString("'medicine_name' => ['label' => 'Medicine Name', 'rules' => 'required|max_length[255]']", $controller);
        self::assertStringContainsString("'Please correct the highlighted fields.'", $controller);
        self::assertStringContainsString('lookups->resolve', $controller);
        self::assertStringContainsString('resolvePrescriptionDefaults', $controller);
        self::assertStringContainsString('transBegin', $controller);
        self::assertStringContainsString('transRollback', $controller);
        self::assertStringContainsString('admin.medicine_created', $controller);
        $script = file_get_contents(ROOTPATH . 'public/assets/js/admin/medicine/medicine.js');
        self::assertStringContainsString('handleFormAjaxError(form, xhr', $script);
        self::assertStringContainsString('bindFormError(form, response.errors)', $script);

        foreach (['prescription_duration_id', 'prescription_frequency_id', 'prescription_dose_id', 'prescription_intake_id', 'prescription_note'] as $field) {
            self::assertStringContainsString("'{$field}'", $model);
            self::assertStringContainsString("'{$field}'", $controller);
        }
    }

}
