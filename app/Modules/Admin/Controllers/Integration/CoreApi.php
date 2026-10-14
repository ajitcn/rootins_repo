<?php

namespace Admin\Controllers\Integration;

use App\Controllers\BaseAdminController;
use App\Modules\CoreApi\Config\EndpointDefinitions;
use App\Modules\CoreApi\Models\CoreApiCredentialModel;
use App\Modules\CoreApi\Services\CoreApiCredentialService;
use App\Modules\CoreApi\Services\CoreApiException;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

final class CoreApi extends BaseAdminController
{
    public function index(): string
    {
        $query = (new CoreApiCredentialModel())->select('id, key_id, status, rate_limit_per_minute, created_at, last_used_at')->orderBy('id', 'DESC');

        return view(ADMIN_VIEW . 'pages/integrations/core_api', [
            'credentials' => $query->findAll(),
            'apiBaseUrl' => rtrim(base_url(), '/'),
            'canManage' => $this->hasPermission('core_api.manage'),
            'canCreate' => $this->hasPermission('core_api.create'),
            'clientExample' => <<<'PHP'
$baseUrl = 'http://127.0.0.1/rootins/public';
$path = '/api/v1/patients/register'; // Do not include /rootins/public here.
$apiKeyId = 'YOUR_API_KEY_ID';
$secret = 'YOUR_API_SECRET';
$body = json_encode([
    'first_name' => 'Ada',
    'last_name' => 'Lovelace',
    'country_code' => '91',
    'mobile_number' => '9876543210',
    'date_of_birth' => '01-01-1990',
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$timestamp = (string) time(); // Unix seconds, not milliseconds.
$canonical = $timestamp . "\n" . "POST" . "\n" . $path . "\n" . $body;
$signature = base64_encode(hash_hmac('sha256', $canonical, $secret, true));
$idempotencyKey = 'shopify-patient-' . bin2hex(random_bytes(12));

$curl = curl_init($baseUrl . $path);
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-API-Key-Id: ' . $apiKeyId,
        'X-API-Timestamp: ' . $timestamp,
        'X-API-Signature: ' . $signature,
        'Idempotency-Key: ' . $idempotencyKey,
    ],
    CURLOPT_POSTFIELDS => $body, // Sign and send this exact string.
]);
$responseBody = curl_exec($curl);
curl_close($curl);
echo $responseBody;
PHP,
            'endpoints' => EndpointDefinitions::all(),
        ]);
    }

    public function create(): ResponseInterface
    {
        if (! $this->request->isAJAX()) {
            return $this->json(405, false, 'Invalid request.');
        }
        $keyId = trim((string) $this->request->getPost('key_id'));
        $rateLimit = (int) $this->request->getPost('rate_limit_per_minute');
        $rateLimit = $rateLimit > 0 ? $rateLimit : 60;
        if ($keyId === '') {
            $keyId = 'rootins-app-' . bin2hex(random_bytes(4));
        }
        if (! preg_match('/^[A-Za-z0-9._-]{3,100}$/', $keyId)) {
            return $this->json(422, false, 'API key ID must use 3-100 letters, numbers, dots, underscores, or hyphens.');
        }

        try {
            $result = (new CoreApiCredentialService())->createGenerated($keyId, $rateLimit);
            $this->audit->record('admin.core_api.credential_created', ['api_key_id' => $keyId, 'scope' => 'application']);

            return $this->response->setStatusCode(201)->setJSON(['status' => true, 'msg' => 'Credential created. Copy the secret now; it will not be shown again.', 'data' => ['id' => (new CoreApiCredentialModel())->where('key_id', $keyId)->first()['id'] ?? null, 'key_id' => $result['key_id'], 'secret' => $result['secret']]]);
        } catch (Throwable $exception) {
            log_message('error', 'Core API credential creation failed: ' . $exception->getMessage());

            return $this->json(409, false, 'The credential could not be created safely. The key ID may already exist.');
        }
    }

    public function revoke(int $id): ResponseInterface
    {
        return $this->updateStatus($id, 'revoked', 'admin.core_api.credential_revoked', 'Credential revoked.');
    }

    public function rotate(int $id): ResponseInterface
    {
        if (! $this->request->isAJAX()) {
            return $this->json(405, false, 'Invalid request.');
        }
        if ((new CoreApiCredentialModel())->find($id) === null) {
            return $this->json(404, false, 'Credential not found in permitted scope.');
        }
        try {
            $result = (new CoreApiCredentialService())->rotate($id, max(1, (int) $this->request->getPost('rate_limit_per_minute') ?: 60));
            $this->audit->record('admin.core_api.credential_rotated', ['credential_id' => $id, 'scope' => 'application', 'api_key_id' => $result['key_id']]);

            return $this->response->setJSON(['status' => true, 'msg' => 'Credential rotated. Copy the new secret now; it will not be shown again.', 'data' => ['key_id' => $result['key_id'], 'secret' => $result['secret']]]);
        } catch (Throwable $exception) {
            log_message('error', 'Core API credential rotation failed: ' . $exception->getMessage());

            return $this->json(500, false, 'The credential could not be rotated safely.');
        }
    }

    public function test(int $id): ResponseInterface
    {
        if (! $this->request->isAJAX()) {
            return $this->json(405, false, 'Invalid request.');
        }
        if ((new CoreApiCredentialModel())->find($id) === null) {
            return $this->json(404, false, 'Credential not found in permitted scope.');
        }
        try {
            $result = (new CoreApiCredentialService())->testConnection($id);
            $ok = $result['http_status'] === 200 && $result['api_status'] === true;
            $this->audit->record($ok ? 'admin.core_api.connection_test_succeeded' : 'admin.core_api.connection_test_failed', ['credential_id' => $id, 'scope' => 'application', 'http_status' => $result['http_status'], 'error_code' => $result['error_code']]);

            return $this->response->setStatusCode($ok ? 200 : 502)->setJSON(['status' => $ok, 'msg' => $ok ? 'Signed Core API connection succeeded.' : 'Signed Core API connection failed.', 'data' => ['http_status' => $result['http_status'], 'error_code' => $result['error_code']]]);
        } catch (CoreApiException $exception) {
            $this->audit->record('admin.core_api.connection_test_failed', ['credential_id' => $id, 'scope' => 'application', 'error_code' => $exception->errorCode]);

            return $this->json($exception->httpStatus, false, $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Core API connection test failed: ' . $exception->getMessage());

            return $this->json(502, false, 'The signed Core API connection could not be verified safely.');
        }
    }

    private function updateStatus(int $id, string $status, string $event, string $message): ResponseInterface
    {
        if (! $this->request->isAJAX()) {
            return $this->json(405, false, 'Invalid request.');
        }
        if ((new CoreApiCredentialModel())->find($id) === null) {
            return $this->json(404, false, 'Credential not found in permitted scope.');
        }
        try {
            (new CoreApiCredentialModel())->update($id, ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
            $this->audit->record($event, ['credential_id' => $id, 'scope' => 'application']);

            return $this->json(200, true, $message);
        } catch (Throwable $exception) {
            log_message('error', 'Core API credential status update failed: ' . $exception->getMessage());

            return $this->json(500, false, 'The credential could not be updated safely.');
        }
    }

    private function json(int $status, bool $success, string $message): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['status' => $success, 'msg' => $message]);
    }
}
