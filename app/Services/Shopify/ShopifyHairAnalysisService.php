<?php

namespace App\Services\Shopify;

use App\Modules\Shopify\Models\ShopifyHairAnalysisConfigurationModel;
use App\Modules\Shopify\Models\ShopifyHairAnalysisImageModel;
use App\Modules\Shopify\Models\ShopifyHairAnalysisLeadModel;
use App\Services\Common\R2StorageLibrary;
use Admin\Services\PatientRegistrationService;
use CodeIgniter\HTTP\Files\UploadedFile;
use InvalidArgumentException;
use RuntimeException;

final class ShopifyHairAnalysisService
{
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];
    private const ANGLES = ['front', 'top', 'crown', 'left', 'right', 'back'];
    private const MAX_FILES = 5;
    private const MAX_FILE_BYTES = 10_485_760;

    public function __construct(
        private readonly ?ShopifyHairAnalysisLeadModel $leads = null,
        private readonly ?ShopifyHairAnalysisImageModel $images = null,
        private readonly ?ShopifyHairAnalysisConfigurationModel $configurations = null,
        private readonly ?R2StorageLibrary $storage = null,
    ) {}

    /** @param array<string,mixed> $fields @param array<int,UploadedFile> $files */
    public function submit(array $context, array $fields, array $files, string $idempotencyKey): array
    {
        $store = trim((string) ($context['store_domain'] ?? ''));
        $customer = trim((string) ($context['customer_id'] ?? ''));
        if ($store === '' || $customer === '') throw new RuntimeException('SHOPIFY_PROXY_CONTEXT_MISSING');
        if (! preg_match('/^[A-Za-z0-9._:-]{8,191}$/', $idempotencyKey)) throw new InvalidArgumentException('A valid Idempotency-Key header is required.');

        $model = $this->leads ?? new ShopifyHairAnalysisLeadModel();
        $existing = $model->where(['store_domain' => $store, 'shopify_customer_id' => $customer, 'idempotency_key' => $idempotencyKey])->first();
        if ($existing !== null) return $this->publicLead($existing);

        $firstName = trim((string) ($fields['first_name'] ?? ''));
        if ($firstName === '' || mb_strlen($firstName) > 100) throw new InvalidArgumentException('First name is required and must be at most 100 characters.');
        $age = trim((string) ($fields['age'] ?? ''));
        if ($age !== '' && (! ctype_digit($age) || (int) $age < 1 || (int) $age > 120)) throw new InvalidArgumentException('Age must be between 1 and 120.');
        $gender = trim((string) ($fields['gender'] ?? ''));
        if ($gender !== '' && ! in_array(strtolower($gender), ['male', 'female', 'other'], true)) throw new InvalidArgumentException('Gender is invalid.');
        if ($files === [] || count($files) > self::MAX_FILES) throw new InvalidArgumentException('Upload between 1 and 5 scalp images.');

        $config = ($this->configurations ?? new ShopifyHairAnalysisConfigurationModel())->where('is_active', 1)->orderBy('version', 'DESC')->first();
        if ($config === null) throw new RuntimeException('SHOPIFY_HAIR_ANALYSIS_CONFIGURATION_MISSING');
        $schema = json_decode((string) $config['schema_json'], true);
        if (! is_array($schema)) throw new RuntimeException('SHOPIFY_HAIR_ANALYSIS_CONFIGURATION_INVALID');

        $leadModel = $model;
        $now = date('Y-m-d H:i:s');
        $leadReference = 'HAI-' . strtoupper(bin2hex(random_bytes(6)));
        $questionnaire = [
            'first_name' => $firstName,
            'last_name' => trim((string) ($fields['last_name'] ?? '')) ?: null,
            'age' => $age !== '' ? (int) $age : null,
            'gender' => $gender !== '' ? strtolower($gender) : null,
            'email' => trim((string) ($fields['email'] ?? '')) ?: null,
            'mobile_number' => trim((string) ($fields['mobile_number'] ?? '')) ?: null,
            'country_code' => trim((string) ($fields['country_code'] ?? '')) ?: null,
            'date_of_birth' => trim((string) ($fields['date_of_birth'] ?? '')) ?: null,
            'concern' => trim((string) ($fields['concern'] ?? '')) ?: null,
            'health_list' => trim((string) ($fields['health_list'] ?? '')) ?: null,
        ];
        $leadData = [
            'lead_reference' => $leadReference,
            'store_domain' => $store,
            'shopify_customer_id' => $customer,
            'idempotency_key' => $idempotencyKey,
            'first_name' => $firstName,
            'last_name' => $questionnaire['last_name'],
            'age' => $questionnaire['age'],
            'gender' => $questionnaire['gender'],
            'email' => $questionnaire['email'],
            'mobile_number' => $questionnaire['mobile_number'],
            'country_code' => $questionnaire['country_code'],
            'date_of_birth' => $questionnaire['date_of_birth'],
            'concern' => $questionnaire['concern'],
            'health_list' => $questionnaire['health_list'],
            'questionnaire_json' => json_encode($questionnaire, JSON_UNESCAPED_SLASHES),
            'status' => 'processing',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        try {
            $leadId = $leadModel->insert($leadData, true);
        } catch (\Throwable $exception) {
            $replayed = $leadModel->where(['store_domain' => $store, 'shopify_customer_id' => $customer, 'idempotency_key' => $idempotencyKey])->first();
            if ($replayed !== null) return $this->publicLead($replayed);
            throw $exception;
        }
        if (! $leadId) throw new RuntimeException('SHOPIFY_HAIR_ANALYSIS_LEAD_CREATE_FAILED');

        $imageRows = [];
        $uploadedKeys = [];
        $imagesPersisted = false;
        try {
            foreach (array_values($files) as $index => $file) {
                $this->validateFile($file);
                $angle = $this->angle($fields, $index);
                $extension = strtolower((string) pathinfo((string) $file->getName(), PATHINFO_EXTENSION));
                $key = 'shopify/hair-analysis/' . $leadReference . '/' . $angle . '-' . bin2hex(random_bytes(8)) . '.' . ($extension ?: 'bin');
                ($this->storage ?? new R2StorageLibrary())->uploadFile($key, $file);
                $uploadedKeys[] = $key;
                $imageRows[] = ['lead_id' => (int) $leadId, 'angle' => $angle, 'storage_key' => $key, 'mime_type' => (string) $file->getMimeType(), 'file_size' => (int) $file->getSize(), 'created_at' => $now];
            }
            (new ShopifyHairAnalysisImageModel())->insertBatch($imageRows);
            $imagesPersisted = true;
            $result = $this->analyze($config, $questionnaire, $files, $leadReference);
            $leadModel->update((int) $leadId, ['status' => 'completed', 'result_json' => json_encode($result, JSON_UNESCAPED_SLASHES), 'processed_at' => $now, 'updated_at' => date('Y-m-d H:i:s')]);
            service('audit')->record('shopify.hair_analysis_completed', ['lead_reference' => $leadReference, 'configuration_version' => (int) $config['version']]);
        } catch (\Throwable $exception) {
            if (! $imagesPersisted) foreach ($uploadedKeys as $uploadedKey) {
                try { ($this->storage ?? new R2StorageLibrary())->delete($uploadedKey); } catch (\Throwable $cleanupException) {
                    log_message('warning', 'Shopify hair analysis object cleanup failed ' . json_encode(['lead_reference' => $leadReference, 'exception_class' => get_class($cleanupException)], JSON_UNESCAPED_SLASHES));
                }
            }
            $failureCode = $exception instanceof InvalidArgumentException ? 'VALIDATION_FAILED' : $this->safeFailureCode($exception->getMessage());
            $leadModel->update((int) $leadId, ['status' => 'failed', 'error_code' => $failureCode, 'error_message' => $failureCode, 'updated_at' => date('Y-m-d H:i:s')]);
            log_message('error', 'Shopify hair analysis failed ' . json_encode(['lead_reference' => $leadReference, 'category' => $exception instanceof InvalidArgumentException ? 'validation' : 'processing', 'exception_class' => get_class($exception)], JSON_UNESCAPED_SLASHES));
        }

        return $this->publicLead($leadModel->find((int) $leadId));
    }

    /** @return array<string,mixed> */
    public function status(array $context, string $reference): array
    {
        $lead = ($this->leads ?? new ShopifyHairAnalysisLeadModel())->where(['lead_reference' => trim($reference), 'store_domain' => (string) ($context['store_domain'] ?? ''), 'shopify_customer_id' => (string) ($context['customer_id'] ?? '')])->first();
        if ($lead === null) throw new RuntimeException('SHOPIFY_HAIR_ANALYSIS_NOT_FOUND');
        return $this->publicLead($lead, true);
    }

    /** @return list<array<string,mixed>> */
    public function adminList(): array
    {
        return ($this->leads ?? new ShopifyHairAnalysisLeadModel())->orderBy('id', 'DESC')->findAll(100);
    }

    /** @return array<string,mixed> */
    public function adminFind(int $id): ?array
    {
        $lead = ($this->leads ?? new ShopifyHairAnalysisLeadModel())->find($id);
        if ($lead === null) return null;
        if (! empty($lead['patient_id'])) {
            $lead['patient_reference'] = (string) (db_connect()->table('patients')->select('registration_number')->where('id', (int) $lead['patient_id'])->get()->getRow('registration_number') ?? '');
        }
        $lead['images'] = ($this->images ?? new ShopifyHairAnalysisImageModel())->where('lead_id', $id)->findAll();
        return $lead;
    }

    /** @return array<string,mixed> */
    public function linkPatient(int $id, string $patientReference, int $actor): array
    {
        $leadModel = $this->leads ?? new ShopifyHairAnalysisLeadModel();
        $lead = $leadModel->find($id);
        if ($lead === null) throw new InvalidArgumentException('Hair analysis lead was not found.');
        $patientReference = trim($patientReference);
        if ($patientReference === '') throw new InvalidArgumentException('Enter a ROOTINS patient reference.');
        $patient = db_connect()->table('patients')->select('id, registration_number, status')->where('registration_number', $patientReference)->get()->getRowArray();
        if ($patient === null || (int) $patient['status'] !== 1) throw new InvalidArgumentException('Select an active ROOTINS patient.');
        $leadModel->update($id, ['patient_id' => (int) $patient['id'], 'updated_at' => date('Y-m-d H:i:s')]);
        service('audit')->record('admin.shopify_hair_analysis_lead_patient_linked', ['lead_id' => $id, 'patient_reference' => $patientReference, 'actor_user_id' => $actor]);
        return ['reference' => (string) $lead['lead_reference'], 'patient_reference' => (string) $patient['registration_number']];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createPatient(int $id, array $input, int $actor): array
    {
        $leadModel = $this->leads ?? new ShopifyHairAnalysisLeadModel();
        $lead = $leadModel->find($id);
        if ($lead === null) throw new InvalidArgumentException('Hair analysis lead was not found.');
        if (! empty($lead['patient_id'])) return ['reference' => (string) $lead['lead_reference'], 'patient_reference' => (string) (db_connect()->table('patients')->select('registration_number')->where('id', (int) $lead['patient_id'])->get()->getRow('registration_number') ?? '')];
        $mobile = trim((string) ($input['mobile_number'] ?? $lead['mobile_number'] ?? ''));
        $country = trim((string) ($input['country_code'] ?? $lead['country_code'] ?? ''));
        $dob = trim((string) ($input['date_of_birth'] ?? $lead['date_of_birth'] ?? ''));
        if ($mobile === '' || $country === '' || $dob === '') throw new InvalidArgumentException('Mobile number, country code, and date of birth are required.');
        $parts = preg_split('/\s+/', trim((string) $lead['first_name']), 2);
        $result = (new PatientRegistrationService())->register([
            'clinic_id' => null,
            'first_name' => (string) ($lead['first_name'] ?? ($parts[0] ?? '')),
            'last_name' => trim((string) ($lead['last_name'] ?? ($parts[1] ?? ''))),
            'gender' => $lead['gender'] ?? null,
            'date_of_birth' => $dob,
            'email' => trim((string) ($input['email'] ?? $lead['email'] ?? '')),
            'mobile_number' => $mobile,
            'country_code' => $country,
        ], $actor, ['source_type' => 'shopify', 'source_user_id' => $actor, 'source_reference' => 'hair-analysis:' . $lead['lead_reference']]);
        $leadModel->update($id, ['patient_id' => (int) $result['id'], 'mobile_number' => $mobile, 'country_code' => $country, 'date_of_birth' => $dob, 'updated_at' => date('Y-m-d H:i:s')]);
        service('audit')->record('admin.shopify_hair_analysis_lead_patient_created', ['lead_id' => $id, 'patient_reference' => $result['registration_number'], 'actor_user_id' => $actor]);
        return ['reference' => (string) $lead['lead_reference'], 'patient_reference' => (string) $result['registration_number']];
    }

    /** @return array<string,mixed> */
    public function retry(int $id): array
    {
        $leadModel = $this->leads ?? new ShopifyHairAnalysisLeadModel();
        $lead = $leadModel->find($id);
        if ($lead === null) throw new InvalidArgumentException('Hair analysis lead was not found.');
        $rows = ($this->images ?? new ShopifyHairAnalysisImageModel())->where('lead_id', $id)->orderBy('id', 'ASC')->findAll();
        if ($rows === []) throw new RuntimeException('SHOPIFY_HAIR_ANALYSIS_IMAGES_NOT_FOUND');
        $config = ($this->configurations ?? new ShopifyHairAnalysisConfigurationModel())->where('is_active', 1)->orderBy('version', 'DESC')->first();
        if ($config === null) throw new RuntimeException('SHOPIFY_HAIR_ANALYSIS_CONFIGURATION_MISSING');
        $photos = [];
        foreach ($rows as $row) {
            $object = ($this->storage ?? new R2StorageLibrary())->getObject((string) $row['storage_key']);
            $photos[] = ['angle' => (string) $row['angle'], 'mime' => (string) $row['mime_type'], 'bytes' => (string) ($object['Body'] ?? '')];
        }
        $questionnaire = json_decode((string) ($lead['questionnaire_json'] ?? ''), true);
        $questionnaire = is_array($questionnaire) ? $questionnaire : ['first_name' => (string) $lead['first_name']];
        $leadModel->update($id, ['status' => 'processing', 'error_code' => null, 'error_message' => null, 'updated_at' => date('Y-m-d H:i:s')]);
        try {
            $result = $this->analyzePhotos($config, $questionnaire, $photos, (string) $lead['lead_reference']);
            $leadModel->update($id, ['status' => 'completed', 'result_json' => json_encode($result, JSON_UNESCAPED_SLASHES), 'processed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
            service('audit')->record('admin.shopify_hair_analysis_retried', ['lead_id' => $id, 'status' => 'completed']);
        } catch (\Throwable $exception) {
            $failureCode = $this->safeFailureCode($exception->getMessage());
            $leadModel->update($id, ['status' => 'failed', 'error_code' => $failureCode, 'error_message' => $failureCode, 'updated_at' => date('Y-m-d H:i:s')]);
            throw new RuntimeException('SHOPIFY_HAIR_ANALYSIS_RETRY_FAILED');
        }
        return $this->publicLead($leadModel->find($id), true);
    }

    private function validateFile(UploadedFile $file): void
    {
        if (! $file->isValid() || $file->getError() !== UPLOAD_ERR_OK || $file->hasMoved()) throw new InvalidArgumentException('One of the uploaded images is invalid.');
        if ((int) $file->getSize() < 1 || (int) $file->getSize() > self::MAX_FILE_BYTES) throw new InvalidArgumentException('Each image must be 10 MB or smaller.');
        if (! in_array(strtolower((string) $file->getMimeType()), self::ALLOWED_MIME, true)) throw new InvalidArgumentException('Only JPG, PNG, and WebP images are supported.');
    }

    private function angle(array $fields, int $index): string
    {
        $angles = $fields['angles'] ?? [];
        if (is_string($angles)) $angles = array_map('trim', explode(',', $angles));
        $angle = is_array($angles) ? strtolower(trim((string) ($angles[$index] ?? ''))) : '';
        if (! in_array($angle, self::ANGLES, true)) $angle = self::ANGLES[$index] ?? 'front';
        return $angle;
    }

    /** @return array<string,mixed> */
    private function analyze(array $config, array $questionnaire, array $files, string $leadReference = ''): array
    {
        $photos = [];
        foreach (array_values($files) as $index => $file) {
            $photos[] = ['angle' => self::ANGLES[$index] ?? 'photo', 'mime' => strtolower((string) $file->getMimeType()), 'bytes' => (string) file_get_contents($file->getTempName())];
        }
        return $this->analyzePhotos($config, $questionnaire, $photos, $leadReference);
    }

    /** @param list<array{angle:string,mime:string,bytes:string}> $photos */
    private function analyzePhotos(array $config, array $questionnaire, array $photos, string $leadReference = ''): array
    {
        $apiKey = trim((string) env('OPENAI_API_KEY'));
        if ($apiKey === '') {
            $this->logOpenAiFailure($leadReference, $config, 'configuration_missing', null, null);
            throw new RuntimeException('OPENAI_CONFIGURATION_MISSING');
        }
        $content = [['type' => 'text', 'text' => "Patient context:\n" . json_encode($questionnaire, JSON_UNESCAPED_SLASHES)]];
        foreach ($photos as $index => $photo) {
            $content[] = ['type' => 'text', 'text' => 'Photo ' . ($index + 1) . ': ' . $photo['angle']];
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $photo['mime'] . ';base64,' . base64_encode($photo['bytes']), 'detail' => 'high']];
        }
        $body = ['model' => (string) $config['model'], 'temperature' => (float) $config['temperature'], 'messages' => [['role' => 'system', 'content' => (string) $config['prompt_text']], ['role' => 'user', 'content' => $content]], 'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'rootins_hair_report', 'strict' => true, 'schema' => json_decode((string) $config['schema_json'], true)]]];
        $handle = curl_init('https://api.openai.com/v1/chat/completions');
        if ($handle === false) {
            $this->logOpenAiFailure($leadReference, $config, 'curl_init_failed', null, null);
            throw new RuntimeException('OPENAI_CURL_UNAVAILABLE');
        }
        curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_SLASHES), CURLOPT_TIMEOUT => 120]);
        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($handle);
        curl_close($handle);
        if (! is_string($raw) || $status < 200 || $status >= 300) {
            $decodedError = is_string($raw) ? json_decode($raw, true) : null;
            $errorCode = is_array($decodedError) ? (string) ($decodedError['error']['code'] ?? '') : '';
            $errorType = is_array($decodedError) ? (string) ($decodedError['error']['type'] ?? '') : '';
            $this->logOpenAiFailure($leadReference, $config, $curlError > 0 ? 'curl_request_failed' : 'http_request_failed', $status, $errorCode !== '' ? $errorCode : ($errorType !== '' ? $errorType : null));
            throw new RuntimeException($curlError > 0 ? 'OPENAI_CURL_REQUEST_FAILED' : 'OPENAI_HTTP_REQUEST_FAILED');
        }
        $decoded = json_decode($raw, true);
        $contentJson = $decoded['choices'][0]['message']['content'] ?? null;
        $result = is_string($contentJson) ? json_decode($contentJson, true) : null;
        if (! is_array($result)) {
            $this->logOpenAiFailure($leadReference, $config, 'response_invalid', $status, null);
            throw new RuntimeException('OPENAI_RESPONSE_INVALID');
        }
        return $result;
    }

    private function logOpenAiFailure(string $leadReference, array $config, string $stage, ?int $httpStatus, ?string $providerCode): void
    {
        log_message('error', 'Shopify hair analysis OpenAI failure ' . json_encode([
            'lead_reference' => $leadReference !== '' ? $leadReference : null,
            'configuration_version' => (int) ($config['version'] ?? 0),
            'model' => (string) ($config['model'] ?? ''),
            'stage' => $stage,
            'http_status' => $httpStatus,
            'provider_code' => $providerCode,
        ], JSON_UNESCAPED_SLASHES));
    }

    private function safeFailureCode(string $message): string
    {
        return preg_match('/^[A-Z0-9_]{3,100}$/', $message) === 1 ? $message : 'SHOPIFY_HAIR_ANALYSIS_PROCESSING_FAILED';
    }

    /** @return array<string,mixed> */
    private function publicLead(?array $lead, bool $includeResult = false): array
    {
        if ($lead === null) return [];
        $out = ['reference' => (string) $lead['lead_reference'], 'status' => (string) $lead['status'], 'created_at' => $lead['created_at'] ?? null];
        if ($includeResult && (string) $lead['status'] === 'completed') $out['result'] = json_decode((string) $lead['result_json'], true);
        if ((string) $lead['status'] === 'failed') $out['error_code'] = (string) ($lead['error_code'] ?? 'SHOPIFY_HAIR_ANALYSIS_FAILED');
        return $out;
    }
}
