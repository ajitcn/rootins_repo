<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
final class AdminConfigurationSeeder extends Seeder
{
    public function run()
    {
        $masters = ['doctor-specializations' => ['General Medicine', 'Cardiology'], 'departments' => ['Outpatient', 'Pharmacy'], 'services' => ['General Consultation', 'Tele-consultation'], 'consultation-fees' => ['Standard Consultation'], 'commission-templates' => ['Standard Commission'], 'payment-modes' => ['Cash', 'Online'], 'tax-rates' => ['No Tax']];
        foreach ($masters as $type => $names) {
            foreach ($names as $name) {
                $exists = $this->db->table('admin_master_records')->where(['master_type' => $type, 'name' => $name, 'clinic_id' => null])->countAllResults();
                if ($exists === 0) {
                    $this->db->table('admin_master_records')->insert(['master_type' => $type, 'name' => $name, 'status' => 1, 'clinic_id' => null]);
                }
            }
        }
        $settings = ['general-settings' => ['site_name' => 'ROOTINS'], 'clinic-settings' => ['timezone' => 'Asia/Kolkata'], 'email-settings' => ['provider' => 'sendgrid'], 'sms-settings' => ['provider' => 'twilio'], 'whatsapp-settings' => ['provider' => 'twilio'], 'notification-settings' => ['in_app_enabled' => '1'], 'invoice-templates' => ['default_template' => 'standard'], 'prescription-templates' => ['default_template' => 'standard'], 'backup-restore' => ['readiness_policy' => 'encrypted_daily_backup'], 'system-logs' => ['viewer' => 'audit_logs']];
        foreach ($settings as $group => $pairs) {
            foreach ($pairs as $key => $value) {
                $exists = $this->db->table('admin_setting_records')->where(['setting_group' => $group, 'setting_key' => $key, 'clinic_id' => null])->countAllResults();
                if ($exists === 0) {
                    $this->db->table('admin_setting_records')->insert(['setting_group' => $group, 'setting_key' => $key, 'setting_value' => $value, 'is_secret' => 0, 'clinic_id' => null]);
                }
            }
        }
    }
}
