<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Moves configuration masters out of the polymorphic legacy catalog.
 *
 * The legacy table is deliberately retained. Its ids are copied into
 * legacy_master_id so existing integrations can be reconciled without data
 * loss, while new reads and writes use the dedicated tables.
 */
class RefactorAdminMasterRecords extends Migration
{
    private const CATALOGS = [
        'departments' => ['description' => true, 'amount' => false, 'code' => true],
        'services' => ['description' => true, 'amount' => true, 'code' => true],
        'consultation-fees' => ['description' => true, 'amount' => true, 'code' => true],
        'commission-templates' => ['description' => true, 'amount' => true, 'code' => true],
        'payment-modes' => ['description' => false, 'amount' => false, 'code' => true],
        'tax-rates' => ['description' => true, 'amount' => true, 'code' => true],
        'prescription-duration' => ['description' => false, 'amount' => false, 'code' => true],
        'prescription-frequency' => ['description' => false, 'amount' => false, 'code' => true],
        'prescription-dose' => ['description' => false, 'amount' => false, 'code' => true],
        'prescription-intake' => ['description' => false, 'amount' => false, 'code' => true],
        'prescription-statuses' => ['description' => false, 'amount' => false, 'code' => true],
    ];

    private const TABLES = [
        'departments' => 'admin_departments',
        'services' => 'admin_services',
        'consultation-fees' => 'consultation_fee_templates',
        'commission-templates' => 'commission_templates',
        'payment-modes' => 'payment_modes',
        'tax-rates' => 'tax_rates',
        'prescription-duration' => 'prescription_durations',
        'prescription-frequency' => 'prescription_frequencies',
        'prescription-dose' => 'prescription_doses',
        'prescription-intake' => 'prescription_intakes',
        'prescription-statuses' => 'prescription_status_options',
    ];

    public function up()
    {
        foreach (self::CATALOGS as $type => $fields) {
            $this->createCatalogTable(self::TABLES[$type]);
        }

        $this->prepareDoctorSpecializations();
        $this->backfillCatalogs();
        $this->seedPrescriptionDefaults();
        $this->moveAppointmentServiceForeignKey();
    }

    public function down()
    {
        $this->restoreAppointmentServiceForeignKey();

        foreach (array_reverse(self::TABLES) as $table) {
            $this->forge->dropTable($table, true);
        }

        if ($this->db->fieldExists('normalized_name', 'doctor_specializations')) {
            $this->db->query('ALTER TABLE doctor_specializations DROP INDEX doctor_specializations_normalized_name');
            $this->forge->dropColumn('doctor_specializations', ['normalized_name', 'legacy_master_id']);
        }
    }

    private function createCatalogTable(string $table): void
    {
        if ($this->db->tableExists($table)) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'normalized_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'code' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'normalized_code' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'description' => ['type' => 'TEXT', 'null' => true],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true],
            'status' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'clinic_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'legacy_master_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'scope_key' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'global'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['status', 'scope_key']);
        $this->forge->addKey(['legacy_master_id']);
        $this->forge->addUniqueKey(['scope_key', 'normalized_name']);
        $this->forge->addUniqueKey(['scope_key', 'normalized_code']);
        $this->forge->addForeignKey('clinic_id', 'clinics', 'id', 'CASCADE', 'SET NULL', $table . '_clinic_fk');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'SET NULL', 'SET NULL', $table . '_created_by_fk');
        $this->forge->createTable($table);
    }

    private function prepareDoctorSpecializations(): void
    {
        if (! $this->db->fieldExists('normalized_name', 'doctor_specializations')) {
            $this->forge->addColumn('doctor_specializations', [
                'normalized_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'specialization_name'],
                'legacy_master_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'normalized_name'],
            ]);
            $this->db->query("UPDATE doctor_specializations SET normalized_name = LOWER(TRIM(specialization_name)) WHERE normalized_name IS NULL");
            $this->db->query('ALTER TABLE doctor_specializations ADD UNIQUE KEY doctor_specializations_normalized_name (normalized_name)');
        }
    }

    private function backfillCatalogs(): void
    {
        foreach (self::TABLES as $type => $table) {
            $rows = $this->db->table('admin_master_records')
                ->where('master_type', $type)
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            foreach ($rows as $row) {
                $this->insertCatalogRow($table, $row);
            }
        }

        $specializations = $this->db->table('admin_master_records')
            ->where('master_type', 'doctor-specializations')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($specializations as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $normalized = strtolower($name);

            if ($name === '' || $this->db->table('doctor_specializations')->where('normalized_name', $normalized)->countAllResults() > 0) {
                continue;
            }
            $this->db->table('doctor_specializations')->insert([
                'specialization_name' => $name,
                'normalized_name' => $normalized,
                'legacy_master_id' => (int) $row['id'],
                'status' => (int) ($row['status'] ?? 1),
                'created_at' => $row['created_at'] ?: date('Y-m-d H:i:s'),
                'updated_at' => $row['updated_at'] ?: date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function insertCatalogRow(string $table, array $row): void
    {
        $name = trim((string) ($row['name'] ?? ''));

        if ($name === '') {
            return;
        }
        $normalizedName = strtolower($name);
        $scopeKey = ($row['clinic_id'] ?? null) === null ? 'global' : (string) (int) $row['clinic_id'];
        $exists = $this->db->table($table)
            ->where('scope_key', $scopeKey)
            ->where('normalized_name', $normalizedName)
            ->countAllResults();

        if ($exists > 0) {
            return;
        }
        $code = trim((string) ($row['code'] ?? '')) ?: null;
        $this->db->table($table)->insert([
            'id' => (int) $row['id'],
            'name' => $name,
            'normalized_name' => $normalizedName,
            'code' => $code,
            'normalized_code' => $code === null ? null : strtolower($code),
            'description' => ($row['description'] ?? null) !== '' ? ($row['description'] ?? null) : null,
            'amount' => $row['amount'] ?? null,
            'status' => (int) ($row['status'] ?? 1),
            'clinic_id' => $row['clinic_id'] ?: null,
            'created_by' => $row['created_by'] ?: null,
            'legacy_master_id' => (int) $row['id'],
            'scope_key' => $scopeKey,
            'created_at' => $row['created_at'] ?: date('Y-m-d H:i:s'),
            'updated_at' => $row['updated_at'] ?: date('Y-m-d H:i:s'),
        ]);
    }

    private function seedPrescriptionDefaults(): void
    {
        $defaults = [
            'prescription_durations' => [['1', '1 day'], ['3', '3 days'], ['5', '5 days'], ['7', '7 days'], ['10', '10 days'], ['14', '14 days'], ['21', '21 days'], ['30', '30 days'], ['60', '60 days'], ['90', '90 days']],
            'prescription_frequencies' => [['once_daily', 'Once daily'], ['twice_daily', 'Twice daily'], ['three_times_daily', 'Three times daily'], ['at_bedtime', 'At bedtime'], ['as_needed', 'As needed']],
            'prescription_doses' => [['1_tablet', '1 tablet'], ['2_tablets', '2 tablets'], ['1_capsule', '1 capsule'], ['5_ml', '5 ml'], ['10_ml', '10 ml']],
            'prescription_intakes' => [['before_food', 'Before food'], ['after_food', 'After food'], ['with_food', 'With food'], ['empty_stomach', 'Empty stomach'], ['at_bedtime', 'At bedtime']],
            'prescription_status_options' => [['draft', 'Draft'], ['submitted', 'Submitted']],
        ];

        foreach ($defaults as $table => $options) {
            foreach ($options as [$code, $name]) {
                $exists = $this->db->table($table)->where('scope_key', 'global')->where('normalized_code', $code)->countAllResults();

                if ($exists === 0) {
                    $this->db->table($table)->insert([
                        'name' => $name,
                        'normalized_name' => strtolower($name),
                        'code' => $code,
                        'normalized_code' => $code,
                        'status' => 1,
                        'scope_key' => 'global',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }

    private function moveAppointmentServiceForeignKey(): void
    {
        if (! $this->db->tableExists('appointment_services')) {
            return;
        }
        $this->db->query('ALTER TABLE appointment_services DROP FOREIGN KEY appointment_services_service_fk');
        $this->db->query('ALTER TABLE appointment_services ADD CONSTRAINT appointment_services_service_fk FOREIGN KEY (service_id) REFERENCES admin_services(id) ON UPDATE CASCADE ON DELETE SET NULL');
    }

    private function restoreAppointmentServiceForeignKey(): void
    {
        if (! $this->db->tableExists('appointment_services')) {
            return;
        }
        $this->db->query('UPDATE appointment_services AS aps LEFT JOIN admin_services AS s ON s.id = aps.service_id SET aps.service_id = NULL WHERE s.id IS NULL');
        $this->db->query('ALTER TABLE appointment_services DROP FOREIGN KEY appointment_services_service_fk');
        $this->db->query('ALTER TABLE appointment_services ADD CONSTRAINT appointment_services_service_fk FOREIGN KEY (service_id) REFERENCES admin_master_records(id) ON UPDATE CASCADE ON DELETE SET NULL');
    }
}
