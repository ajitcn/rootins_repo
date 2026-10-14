<?php

namespace App\Modules\Shopify\Controllers;

use App\Controllers\BaseAdminController;
use App\Services\Shopify\ShopifyClient;
use App\Services\Shopify\ShopifyConfiguration;
use App\Services\Shopify\ShopifyConfigurationException;
use App\Services\Shopify\ShopifyConfigurationService;
use App\Services\Shopify\ShopifyCustomerIdentityService;
use App\Services\Shopify\ShopifyOrderImportService;
use App\Services\Shopify\ShopifyProviderException;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;

final class Integration extends BaseAdminController
{
    public function index(): string
    {
        $configurationService = new ShopifyConfigurationService();

        return view(
            ADMIN_VIEW . 'pages/integrations/shopify',
            [
                'shopify' => (new ShopifyConfiguration())->safeSummary(),
                'customerLinks' => (new ShopifyCustomerIdentityService())->reviewQueue(),
                'patientOptions' => (new ShopifyCustomerIdentityService())->patientOptions(),
                'orderImports' => (new ShopifyOrderImportService())->queue(),
                'configurations' => $configurationService->safeList(),
                'canManage' => $this->hasPermission('shopify.integration.manage'),
            ],
        );
    }

    public function saveConfiguration(): ResponseInterface
    {
        $id = (int) $this->request->getPost('id');
        $configurationId = $id > 0 ? $id : null;

        try {
            $saved = (new ShopifyConfigurationService())->save($this->request->getPost(), $configurationId, (int) ($this->user['id'] ?? 0));
            $this->audit->record($configurationId === null ? 'shopify.configuration_created' : 'shopify.configuration_updated', [
                'configuration_id' => (int) $saved['id'],
                'scope' => 'application',
            ]);

            return $this->response->setJSON(['status' => true, 'msg' => $configurationId === null ? 'Shopify configuration created.' : 'Shopify configuration updated.']);
        } catch (InvalidArgumentException $exception) {
            $this->audit->record('shopify.configuration_save_failed', ['configuration_id' => (int) ($configurationId ?? 0), 'reason' => 'validation']);

            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => $exception->getMessage()]);
        } catch (\Throwable) {
            $this->audit->record('shopify.configuration_save_failed', ['configuration_id' => (int) ($configurationId ?? 0), 'reason' => 'internal']);

            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Shopify configuration could not be saved safely.']);
        }
    }

    public function rotateSecret(): ResponseInterface
    {
        $configurationId = (int) $this->request->getPost('id');

        try {
            $saved = (new ShopifyConfigurationService())->rotateSecret($configurationId, trim((string) $this->request->getPost('secret_type')), (string) $this->request->getPost('secret_value'), (int) ($this->user['id'] ?? 0));
            $this->audit->record('shopify.configuration_secret_rotated', ['configuration_id' => $configurationId, 'scope' => 'application', 'secret_type' => trim((string) $this->request->getPost('secret_type'))]);

            return $this->response->setJSON(['status' => true, 'msg' => 'Shopify secret rotated. Enter the new value in the Shopify developer configuration if required.']);
        } catch (InvalidArgumentException $exception) {
            $this->audit->record('shopify.configuration_secret_rotation_failed', ['configuration_id' => $configurationId, 'reason' => 'validation']);

            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => $exception->getMessage()]);
        } catch (\Throwable) {
            $this->audit->record('shopify.configuration_secret_rotation_failed', ['configuration_id' => $configurationId, 'reason' => 'internal']);

            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Shopify secret could not be rotated safely.']);
        }
    }

    public function linkCustomer(): ResponseInterface
    {
        try {
            $this->requireConfirmation();
            (new ShopifyCustomerIdentityService())->linkExisting(
                (int) $this->request->getPost('link_id'),
                (int) $this->request->getPost('patient_id'),
            );

            return $this->response->setJSON(['status' => true, 'msg' => 'Shopify customer linked.']);
        } catch (InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => $exception->getMessage()]);
        }
    }

    public function createPatient(): ResponseInterface
    {
        try {
            $this->requireConfirmation();
            (new ShopifyCustomerIdentityService())->createPatient(
                (int) $this->request->getPost('link_id'),
                [
                    'first_name' => $this->request->getPost('first_name'),
                    'last_name' => $this->request->getPost('last_name'),
                    'date_of_birth' => $this->request->getPost('date_of_birth'),
                ],
            );

            return $this->response->setJSON(['status' => true, 'msg' => 'Patient created and Shopify customer linked.']);
        } catch (InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            log_message('error', 'Shopify patient creation failed safely.');

            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Patient could not be created safely.']);
        }
    }

    public function rejectCustomer(): ResponseInterface
    {
        try {
            $this->requireConfirmation();
            (new ShopifyCustomerIdentityService())->reject((int) $this->request->getPost('link_id'));

            return $this->response->setJSON(['status' => true, 'msg' => 'Shopify customer marked as rejected.']);
        } catch (InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => $exception->getMessage()]);
        }
    }

    public function unlinkCustomer(): ResponseInterface
    {
        try {
            $this->requireConfirmation();
            (new ShopifyCustomerIdentityService())->unlink((int) $this->request->getPost('link_id'));

            return $this->response->setJSON(['status' => true, 'msg' => 'Shopify customer unlinked.']);
        } catch (InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => $exception->getMessage()]);
        }
    }

    private function requireConfirmation(): void
    {
        if ($this->request->getPost('confirmation') !== 'I confirm this identity action') {
            throw new InvalidArgumentException('Confirm this identity action before continuing.');
        }
    }

    public function testConnection(): ResponseInterface
    {
        $configurationId = (int) $this->request->getPost('id');
        $configuration = null;
        $client = null;

        try {
            $configuration = (new ShopifyConfigurationService())->runtime($configurationId > 0 ? $configurationId : null);
            $clinic = $configuration->clinicStatus();
            if (($clinic['status'] ?? '') !== 'active') {
                throw new ShopifyConfigurationException('The configured clinic does not exist or is inactive.', 'invalid_clinic');
            }
            $client = new ShopifyClient($configuration);
            $shop = $client->testConnection();
            service('audit')->record('shopify.connection_test_succeeded', [
                'configuration_id' => $configurationId,
            ]);

            return $this->response->setJSON([
                'status' => true,
                'msg' => 'Shopify connection succeeded.',
                'data' => [
                    'shop_name' => (string) ($shop['shop']['name'] ?? 'Configured store'),
                    'configuration_source' => $configuration->source(),
                    'store_domain' => $configuration->storeDomain(),
                    'api_version' => $configuration->apiVersion(),
                    'token' => $client->tokenStatus(),
                    'granted_scopes' => array_values(array_filter(array_map(static fn (mixed $scope): string => is_array($scope) ? (string) ($scope['handle'] ?? '') : (string) $scope, $shop['access_scopes'] ?? []))),
                    'clinic' => $configuration->clinicStatus(),
                ],
            ]);
        } catch (InvalidArgumentException $exception) {
            service('audit')->record('shopify.connection_test_failed', ['configuration_id' => $configurationId, 'reason' => 'validation']);
            $this->logConnectionDiagnostic($configuration, $client, $exception, 'validation');

            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => $exception->getMessage()]);
        } catch (ShopifyConfigurationException $exception) {
            $reason = $exception->errorCode;
            service('audit')->record('shopify.connection_test_failed', ['configuration_id' => $configurationId, 'reason' => $reason]);
            $this->logConnectionDiagnostic($configuration, $client, $exception, $reason);

            return $this->response->setStatusCode(422)->setJSON([
                'status' => false,
                'code' => $reason === 'missing_configuration' ? 'SHOPIFY_CONFIGURATION_MISSING' : $reason,
                'msg' => $this->connectionConfigurationMessage($reason),
                'data' => [
                    'configuration_source' => $configuration?->source() ?? 'unknown',
                    'store_domain' => $configuration?->storeDomain() ?? '',
                    'api_version' => $configuration?->apiVersion() ?? '',
                    'clinic' => $configuration?->clinicStatus() ?? ['status' => 'unknown', 'id' => null, 'name' => null],
                    'token' => $client?->tokenStatus() ?? ['status' => 'not_available', 'expires_at' => null],
                ],
            ]);
        } catch (ShopifyProviderException $exception) {
            $this->logConnectionDiagnostic($configuration, $client, $exception, $exception->category);
            service('audit')->record('shopify.connection_test_failed', [
                'configuration_id' => $configurationId,
                'reason' => $exception->errorCode,
                'http_status' => $exception->httpStatus,
            ]);

            return $this->response->setStatusCode(502)->setJSON([
                'status' => false,
                'code' => $exception->errorCode,
                'msg' => $this->connectionProviderMessage($exception),
                'data' => $this->connectionDiagnosticData($configuration, $client),
            ]);
        } catch (\Throwable $exception) {
            $this->logConnectionDiagnostic($configuration, $client, $exception, 'internal_error');
            service('audit')->record('shopify.connection_test_failed', ['configuration_id' => $configurationId, 'reason' => 'internal_error']);

            return $this->response->setStatusCode(500)->setJSON([
                'status' => false,
                'msg' => 'Shopify connection could not be verified safely. Try again later.',
                'data' => $this->connectionDiagnosticData($configuration, $client),
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function connectionDiagnosticData(?ShopifyConfiguration $configuration, ?ShopifyClient $client): array
    {
        return [
            'configuration_source' => $configuration?->source() ?? 'unknown',
            'store_domain' => $configuration?->storeDomain() ?? '',
            'api_version' => $configuration?->apiVersion() ?? '',
            'clinic' => $configuration?->clinicStatus() ?? ['status' => 'unknown', 'id' => null, 'name' => null],
            'token' => $client?->tokenStatus() ?? ['status' => 'not_available', 'expires_at' => null],
        ];
    }

    private function connectionConfigurationMessage(string $reason): string
    {
        return match ($reason) {
            'missing_configuration' => 'Shopify configuration is incomplete.',
            'invalid_api_version' => 'The configured Shopify API version is invalid.',
            'invalid_clinic' => 'The configured clinic is missing or inactive.',
            default => 'Shopify configuration could not be verified safely.',
        };
    }

    private function connectionProviderMessage(ShopifyProviderException $exception): string
    {
        return match ($exception->category) {
            'invalid_credentials' => 'Shopify credentials were rejected.',
            'organization_mismatch' => 'The app and store must belong to the same Shopify organization when using client credentials. This cannot be fixed by changing the ROOTINS encryption key, clinic ID, API version, or app URL. A different merchant/store requires Shopify installation/OAuth.',
            'network_timeout' => 'Shopify did not respond before the connection timed out.',
            'provider_http' => match ($exception->httpStatus) {
                401 => 'Shopify rejected the connection credentials.',
                403 => 'Shopify denied access for this app or store.',
                404 => 'Shopify could not find the requested API endpoint. Check the store domain and API version.',
                429 => 'Shopify rate-limited the connection test. Try again shortly.',
                default => ($exception->httpStatus !== null && $exception->httpStatus >= 500) ? 'Shopify is temporarily unavailable.' : 'Shopify returned an unsuccessful response.',
            },
            default => 'Shopify connection could not be verified safely. Try again later.',
        };
    }

    private function logConnectionDiagnostic(?ShopifyConfiguration $configuration, ?ShopifyClient $client, \Throwable $exception, string $reason): void
    {
        $tokenStatus = $client?->tokenStatus();
        log_message('error', sprintf(
            'Shopify connection diagnostic route=%s configuration_source=%s store_domain=%s api_version=%s http_status=%s error_code=%s exception=%s file=%s line=%d token_expiry_state=%s reason=%s',
            $this->request->getUri()->getPath(),
            $configuration?->source() ?? 'unknown',
            $configuration?->storeDomain() ?: 'unset',
            $configuration?->apiVersion() ?: 'unset',
            $exception instanceof ShopifyProviderException && $exception->httpStatus !== null ? (string) $exception->httpStatus : 'none',
            $exception instanceof ShopifyProviderException ? $exception->errorCode : ($exception instanceof ShopifyConfigurationException ? $exception->errorCode : 'internal_error'),
            get_class($exception),
            basename($exception->getFile()),
            $exception->getLine(),
            $tokenStatus['status'] ?? 'not_available',
            $reason,
        ));
    }

    public function orders(): string
    {
        return view(ADMIN_VIEW . 'pages/integrations/shopify_orders', [
            'imports' => (new ShopifyOrderImportService())->queue(),
        ]);
    }

    public function importOrder(): ResponseInterface
    {
        $importId = (int) $this->request->getPost('import_id');
        $prescriptionId = (int) $this->request->getPost('prescription_id');

        try {
            $result = (new ShopifyOrderImportService())->importExisting($importId, $prescriptionId > 0 ? $prescriptionId : null);

            return $this->response->setStatusCode((int) $result['http'])->setJSON($result['body']);
        } catch (\Throwable $exception) {
            log_message('error', 'Shopify order import request failed safely.');

            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Shopify order could not be imported safely.']);
        }
    }
}
