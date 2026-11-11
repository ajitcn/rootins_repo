<?php

namespace App\Services\Notification;

use Admin\Models\AdminSettingRecordModel;

final class NotificationSettingsService
{
    private const GROUP = 'notification-settings';
    private const DEFAULTS = [
        'enabled' => '0',
        'email_enabled' => '1',
        'sms_enabled' => '0',
        'whatsapp_enabled' => '0',
        'max_attempts' => '3',
        'retry_delay_seconds' => '300',
    ];

    public function summary(?int $clinicId = null): array
    {
        $values = $this->values($clinicId);
        return [
            'enabled' => ($values['enabled'] ?? '0') === '1',
            'email_enabled' => ($values['email_enabled'] ?? '1') === '1',
            'sms_enabled' => ($values['sms_enabled'] ?? '0') === '1',
            'whatsapp_enabled' => ($values['whatsapp_enabled'] ?? '0') === '1',
            'max_attempts' => (int) ($values['max_attempts'] ?? 3),
            'retry_delay_seconds' => (int) ($values['retry_delay_seconds'] ?? 300),
        ];
    }

    public function save(array $input, ?int $clinicId, int $userId): array
    {
        $maxAttempts = (int) ($input['max_attempts'] ?? 3);
        $retryDelay = (int) ($input['retry_delay_seconds'] ?? 300);
        if ($maxAttempts < 1 || $maxAttempts > 10) throw new \InvalidArgumentException(json_encode(['max_attempts' => 'Maximum attempts must be between 1 and 10.']));
        if ($retryDelay < 30 || $retryDelay > 86400) throw new \InvalidArgumentException(json_encode(['retry_delay_seconds' => 'Retry delay must be between 30 and 86400 seconds.']));

        $data = [
            'enabled' => ! empty($input['enabled']) ? '1' : '0',
            'email_enabled' => ! empty($input['email_enabled']) ? '1' : '0',
            'sms_enabled' => '0',
            'whatsapp_enabled' => '0',
            'max_attempts' => (string) $maxAttempts,
            'retry_delay_seconds' => (string) $retryDelay,
        ];
        $model = new AdminSettingRecordModel();
        foreach ($data as $key => $value) {
            $row = $model->where(['setting_group' => self::GROUP, 'setting_key' => $key, 'clinic_id' => $clinicId])->first();
            $payload = ['setting_group' => self::GROUP, 'setting_key' => $key, 'setting_value' => $value, 'is_secret' => 0, 'clinic_id' => $clinicId, 'created_by' => $userId ?: null, 'updated_at' => date('Y-m-d H:i:s')];
            if ($row) $model->update((int) $row['id'], $payload); else $model->insert($payload);
        }
        return $this->summary($clinicId);
    }

    public function runtime(?int $clinicId = null): array
    {
        return $this->summary($clinicId);
    }

    private function values(?int $clinicId): array
    {
        $model = new AdminSettingRecordModel();
        $query = $model->where('setting_group', self::GROUP);
        if ($clinicId !== null) $query->groupStart()->where('clinic_id', $clinicId)->orWhere('clinic_id', null)->groupEnd(); else $query->where('clinic_id', null);
        $rows = $query->orderBy('clinic_id', 'DESC')->findAll();
        $values = self::DEFAULTS;
        foreach ($rows as $row) $values[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
        return $values;
    }
}
