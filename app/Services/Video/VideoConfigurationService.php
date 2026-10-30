<?php

namespace App\Services\Video;

use Admin\Models\VideoProviderConfigurationModel;
use InvalidArgumentException;
use Throwable;

final class VideoConfigurationService
{
    public function __construct(private readonly ?VideoProviderConfigurationModel $model = null)
    {
    }

    public function safe(): array
    {
        $row = $this->row();

        return [
            'id' => (int) ($row['id'] ?? 0),
            'provider' => (string) ($row['provider'] ?? 'twilio'),
            'status' => (int) ($row['status'] ?? 0),
            'configured' => $row !== null && $this->hasCredentials($row),
            'account_sid_masked' => $row !== null && $row['account_sid_encrypted'] !== '' ? '••••••••' : '',
            'api_key_sid_masked' => $row !== null && $row['api_key_sid_encrypted'] !== '' ? '••••••••' : '',
            'api_key_secret_masked' => $row !== null && $row['api_key_secret_encrypted'] !== '' ? '••••••••' : '',
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    public function save(array $input, int $userId): array
    {
        $accountSid = trim((string) ($input['account_sid'] ?? ''));
        $apiKeySid = trim((string) ($input['api_key_sid'] ?? ''));
        $apiKeySecret = trim((string) ($input['api_key_secret'] ?? ''));
        $existing = $this->row();

        if ($accountSid === '' && $existing !== null) $accountSid = $this->decrypt((string) $existing['account_sid_encrypted']);
        if ($apiKeySid === '' && $existing !== null) $apiKeySid = $this->decrypt((string) $existing['api_key_sid_encrypted']);
        if ($apiKeySecret === '' && $existing !== null) $apiKeySecret = $this->decrypt((string) $existing['api_key_secret_encrypted']);

        if ($accountSid === '' || ! preg_match('/^AC[a-f0-9]{32}$/i', $accountSid)) {
            throw new InvalidArgumentException('Enter a valid Twilio Account SID.');
        }
        if ($apiKeySid === '' || ! preg_match('/^SK[a-f0-9]{32}$/i', $apiKeySid)) {
            throw new InvalidArgumentException('Enter a valid Twilio API Key SID.');
        }
        if (strlen($apiKeySecret) < 16 || strlen($apiKeySecret) > 4096) {
            throw new InvalidArgumentException('Enter a valid Twilio API Key Secret.');
        }

        $model = $this->model ?? new VideoProviderConfigurationModel();
        $data = [
            'provider' => 'twilio',
            'account_sid_encrypted' => $this->encrypt($accountSid),
            'api_key_sid_encrypted' => $this->encrypt($apiKeySid),
            'api_key_secret_encrypted' => $this->encrypt($apiKeySecret),
            'status' => ! empty($input['status']) ? 1 : 0,
            'updated_by' => $userId > 0 ? $userId : null,
        ];

        if ($existing === null) {
            $data['created_by'] = $userId > 0 ? $userId : null;
            $data['created_at'] = date('Y-m-d H:i:s');
            $model->insert($data);
        } elseif (! $model->update((int) $existing['id'], $data)) {
            throw new \RuntimeException('Video provider configuration could not be saved.');
        }

        return $this->safe();
    }

    public function runtime(): array
    {
        $row = $this->row();
        if ($row !== null && (int) $row['status'] === 1) {
            return [
                'provider' => (string) $row['provider'],
                'account_sid' => $this->decrypt((string) $row['account_sid_encrypted']),
                'api_key_sid' => $this->decrypt((string) $row['api_key_sid_encrypted']),
                'api_key_secret' => $this->decrypt((string) $row['api_key_secret_encrypted']),
            ];
        }

        if ($row !== null) {
            return ['provider' => 'twilio', 'account_sid' => '', 'api_key_sid' => '', 'api_key_secret' => ''];
        }

        return [
            'provider' => trim((string) env('VIDEO_PROVIDER', 'provider_boundary')) ?: 'provider_boundary',
            'account_sid' => trim((string) env('TWILIO_ACCOUNT_SID', '')),
            'api_key_sid' => trim((string) env('TWILIO_API_KEY_SID', '')),
            'api_key_secret' => trim((string) env('TWILIO_API_KEY_SECRET', '')),
        ];
    }

    private function row(): ?array
    {
        if (! db_connect()->tableExists('video_provider_configurations')) return null;

        return ($this->model ?? new VideoProviderConfigurationModel())->where('provider', 'twilio')->first();
    }

    private function hasCredentials(array $row): bool
    {
        return $this->decrypt((string) ($row['account_sid_encrypted'] ?? '')) !== ''
            && $this->decrypt((string) ($row['api_key_sid_encrypted'] ?? '')) !== ''
            && $this->decrypt((string) ($row['api_key_secret_encrypted'] ?? '')) !== '';
    }

    private function encrypt(string $value): string
    {
        $key = (string) config('Encryption')->key;
        if ($key === '') throw new \RuntimeException('Application encryption is not configured.');

        return base64_encode(service('encrypter')->encrypt($value, $key));
    }

    private function decrypt(string $value): string
    {
        if ($value === '') return '';
        try {
            $decoded = base64_decode($value, true);
            $plain = $decoded === false ? false : service('encrypter')->decrypt($decoded, (string) config('Encryption')->key);

            return is_string($plain) ? $plain : '';
        } catch (Throwable) {
            return '';
        }
    }
}
