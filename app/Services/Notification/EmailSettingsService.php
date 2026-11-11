<?php

namespace App\Services\Notification;

use Admin\Models\AdminSettingRecordModel;

final class EmailSettingsService
{
    private const GROUP = 'email-settings';
    private const KEYS = ['enabled', 'protocol', 'from_email', 'from_name', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_password', 'smtp_crypto', 'smtp_timeout', 'mail_type'];

    public function summary(?int $clinicId = null): array
    {
        $values = $this->values($clinicId);

        return [
            'enabled' => ($values['enabled'] ?? '0') === '1',
            'protocol' => $values['protocol'] ?? 'smtp',
            'from_email' => $values['from_email'] ?? '',
            'from_name' => $values['from_name'] ?? '',
            'smtp_host' => $values['smtp_host'] ?? '',
            'smtp_port' => (int) ($values['smtp_port'] ?? 587),
            'smtp_user' => $values['smtp_user'] ?? '',
            'has_smtp_password' => ($values['smtp_password'] ?? '') !== '',
            'smtp_crypto' => $values['smtp_crypto'] ?? 'tls',
            'smtp_timeout' => (int) ($values['smtp_timeout'] ?? 10),
            'mail_type' => $values['mail_type'] ?? 'html',
        ];
    }

    public function save(array $input, ?int $clinicId, int $userId): array
    {
        $protocol = strtolower(trim((string) ($input['protocol'] ?? 'smtp')));
        $fromEmail = trim((string) ($input['from_email'] ?? ''));
        $smtpHost = trim((string) ($input['smtp_host'] ?? ''));
        $smtpPort = (int) ($input['smtp_port'] ?? 587);
        $smtpTimeout = (int) ($input['smtp_timeout'] ?? 10);
        $crypto = strtolower(trim((string) ($input['smtp_crypto'] ?? 'tls')));
        $mailType = strtolower(trim((string) ($input['mail_type'] ?? 'html')));
        $errors = [];

        if (! in_array($protocol, ['smtp', 'mail', 'sendmail'], true)) $errors['protocol'] = 'Select a valid mail protocol.';
        if ($fromEmail === '' || ! filter_var($fromEmail, FILTER_VALIDATE_EMAIL) || strlen($fromEmail) > 255) $errors['from_email'] = 'Enter a valid sender email address.';
        if (mb_strlen(trim((string) ($input['from_name'] ?? ''))) > 150) $errors['from_name'] = 'Sender name must be 150 characters or fewer.';
        if ($protocol === 'smtp' && ($smtpHost === '' || strlen($smtpHost) > 255)) $errors['smtp_host'] = 'SMTP host is required for SMTP delivery.';
        if ($smtpPort < 1 || $smtpPort > 65535) $errors['smtp_port'] = 'SMTP port must be between 1 and 65535.';
        if ($smtpTimeout < 1 || $smtpTimeout > 120) $errors['smtp_timeout'] = 'Timeout must be between 1 and 120 seconds.';
        if (! in_array($crypto, ['', 'tls', 'ssl'], true)) $errors['smtp_crypto'] = 'Select a valid SMTP encryption mode.';
        if (! in_array($mailType, ['text', 'html'], true)) $errors['mail_type'] = 'Select a valid message format.';
        if ($errors !== []) throw new \InvalidArgumentException(json_encode($errors));

        $model = new AdminSettingRecordModel();
        $existing = $this->rows($clinicId);
        $password = trim((string) ($input['smtp_password'] ?? ''));
        $data = [
            'enabled' => ! empty($input['enabled']) ? '1' : '0',
            'protocol' => $protocol,
            'from_email' => $fromEmail,
            'from_name' => trim((string) ($input['from_name'] ?? '')),
            'smtp_host' => $smtpHost,
            'smtp_port' => (string) $smtpPort,
            'smtp_user' => trim((string) ($input['smtp_user'] ?? '')),
            'smtp_password' => $password !== '' ? $this->encrypt($password) : ($existing['smtp_password'] ?? ''),
            'smtp_crypto' => $crypto,
            'smtp_timeout' => (string) $smtpTimeout,
            'mail_type' => $mailType,
        ];

        foreach ($data as $key => $value) {
            $row = $this->row($key, $clinicId);
            $payload = ['setting_group' => self::GROUP, 'setting_key' => $key, 'setting_value' => $value, 'is_secret' => $key === 'smtp_password' ? 1 : 0, 'clinic_id' => $clinicId, 'created_by' => $userId ?: null, 'updated_at' => date('Y-m-d H:i:s')];
            if ($row) $model->update((int) $row['id'], $payload); else $model->insert($payload);
        }

        return $this->summary($clinicId);
    }

    public function runtime(?int $clinicId = null): array
    {
        $clinicId ??= $this->currentClinicId();
        $values = $this->values($clinicId);
        return ['enabled' => ($values['enabled'] ?? '0') === '1', 'protocol' => $values['protocol'] ?? 'smtp', 'fromEmail' => $values['from_email'] ?? '', 'fromName' => $values['from_name'] ?? '', 'SMTPHost' => $values['smtp_host'] ?? '', 'SMTPPort' => (int) ($values['smtp_port'] ?? 587), 'SMTPUser' => $values['smtp_user'] ?? '', 'SMTPPass' => $this->decrypt((string) ($values['smtp_password'] ?? '')), 'SMTPCrypto' => $values['smtp_crypto'] ?? 'tls', 'SMTPTimeout' => (int) ($values['smtp_timeout'] ?? 10), 'mailType' => $values['mail_type'] ?? 'html'];
    }

    private function values(?int $clinicId): array
    {
        $rows = $this->rows($clinicId);
        $values = [];
        foreach (self::KEYS as $key) if (array_key_exists($key, $rows)) $values[$key] = $key === 'smtp_password' ? $this->decrypt((string) $rows[$key]) : (string) $rows[$key];
        return $values;
    }

    private function rows(?int $clinicId): array
    {
        $model = new AdminSettingRecordModel();
        $query = $model->where('setting_group', self::GROUP);
        if ($clinicId !== null) $query->groupStart()->where('clinic_id', $clinicId)->orWhere('clinic_id', null)->groupEnd(); else $query->where('clinic_id', null);
        $rows = $query->orderBy('clinic_id', 'DESC')->findAll();
        $result = [];
        foreach ($rows as $row) $result[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
        return $result;
    }

    private function row(string $key, ?int $clinicId): ?array
    {
        $model = new AdminSettingRecordModel();
        $query = $model->where(['setting_group' => self::GROUP, 'setting_key' => $key]);
        if ($clinicId === null) $query->where('clinic_id', null); else $query->where('clinic_id', $clinicId);
        return $query->first();
    }

    private function encrypt(string $value): string { return $value === '' ? '' : base64_encode(service('encrypter')->encrypt($value, (string) config('Encryption')->key)); }
    private function decrypt(string $value): string { try { $decoded = base64_decode($value, true); $plain = $decoded === false ? false : service('encrypter')->decrypt($decoded, (string) config('Encryption')->key); return is_string($plain) ? $plain : ''; } catch (\Throwable) { return ''; } }

    private function currentClinicId(): ?int
    {
        $user = session('user');
        $id = (int) (session('clinic_id') ?? (is_array($user) ? ($user['clinic_id'] ?? 0) : 0));
        return $id > 0 ? $id : null;
    }
}
