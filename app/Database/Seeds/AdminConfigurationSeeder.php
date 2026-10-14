<?php

namespace App\Database\Seeds;

use Admin\Models\Master\DoctorSpecializationModel;
use Admin\Models\PrescriptionOptionModel;
use CodeIgniter\Database\Seeder;

final class AdminConfigurationSeeder extends Seeder
{
    private const CATALOG_TABLES = [
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

    public function run()
    {
        $this->seedSpecializations();

        foreach ([
            'departments' => ['Outpatient', 'Pharmacy'],
            'services' => ['General Consultation', 'Tele-consultation'],
            'consultation-fees' => ['Standard Consultation'],
            'commission-templates' => ['Standard Commission'],
            'payment-modes' => ['Cash', 'Online'],
            'tax-rates' => ['No Tax'],
        ] as $type => $names) {
            foreach ($names as $name) {
                $this->seedCatalog($type, $name);
            }
        }

        foreach (PrescriptionOptionModel::defaultOptions() as $type => $options) {
            foreach ($options as $option) {
                $this->seedCatalog($type, $option['name'], $option['code']);
            }
        }

        foreach ([
            'general-settings' => ['site_name' => 'ROOTINS'],
            'clinic-settings' => ['timezone' => 'Asia/Kolkata'],
            'email-settings' => ['provider' => 'sendgrid'],
            'sms-settings' => ['provider' => 'twilio'],
            'whatsapp-settings' => ['provider' => 'twilio'],
            'notification-settings' => ['in_app_enabled' => '1'],
            'invoice-templates' => ['default_template' => 'standard'],
            'prescription-templates' => ['default_template' => 'standard'],
            'backup-restore' => ['readiness_policy' => 'encrypted_daily_backup'],
            'system-logs' => ['viewer' => 'audit_logs'],
        ] as $group => $pairs) {
            foreach ($pairs as $key => $value) {
                $this->seedSetting($group, $key, $value);
            }
        }
    }

    private function seedSpecializations(): void
    {
        $model = new DoctorSpecializationModel();

        foreach (['General Medicine', 'Cardiology'] as $name) {
            $normalized = strtolower($name);

            if ($model->where('normalized_name', $normalized)->orWhere('specialization_name', $name)->first() === null) {
                $model->insert([
                    'specialization_name' => $name,
                    'normalized_name' => $normalized,
                    'status' => 1,
                ]);
            }
        }
    }

    private function seedCatalog(string $type, string $name, ?string $code = null): void
    {
        $table = self::CATALOG_TABLES[$type] ?? null;

        if ($table === null) {
            return;
        }

        $normalizedName = strtolower(trim($name));
        $query = $this->db->table($table)
            ->where('scope_key', 'global')
            ->where('normalized_name', $normalizedName);

        if ($query->countAllResults() > 0) {
            return;
        }

        $normalizedCode = $code === null ? null : strtolower(trim($code));
        $this->db->table($table)->insert([
            'name' => trim($name),
            'normalized_name' => $normalizedName,
            'code' => $code === null ? null : trim($code),
            'normalized_code' => $normalizedCode,
            'status' => 1,
            'scope_key' => 'global',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function seedSetting(string $group, string $key, string $value): void
    {
        $exists = $this->db->table('admin_setting_records')->where([
            'setting_group' => $group,
            'setting_key' => $key,
            'clinic_id' => null,
        ])->countAllResults();

        if ($exists === 0) {
            $this->db->table('admin_setting_records')->insert([
                'setting_group' => $group,
                'setting_key' => $key,
                'setting_value' => $value,
                'is_secret' => 0,
                'clinic_id' => null,
            ]);
        }
    }
}
