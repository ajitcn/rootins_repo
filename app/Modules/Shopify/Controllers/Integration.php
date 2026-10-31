<?php

namespace App\Modules\Shopify\Controllers;

use App\Controllers\BaseAdminController;
use App\Services\Shopify\ShopifyClient;
use App\Services\Shopify\ShopifyConfiguration;
use App\Services\Shopify\ShopifyConfigurationException;
use App\Services\Shopify\ShopifyConfigurationService;
use App\Services\Shopify\ShopifyCustomerIdentityService;
use App\Services\Shopify\ShopifyCustomerLookupException;
use App\Services\Shopify\ShopifyCustomerLookupService;
use App\Services\Shopify\ShopifyOrderImportService;
use App\Services\Shopify\ShopifyProviderException;
use App\Services\Shopify\ShopifyWebhookValidationService;
use App\Services\Shopify\ShopifyWebhookRegistrationService;
use App\Services\Shopify\ShopifyProxyConfigurationService;
use App\Services\Shopify\ShopifyProxyCredentialVerificationException;
use App\Services\Shopify\ShopifyProxyCredentialVerificationService;
use App\Modules\Shopify\Models\ShopifyHairAnalysisConfigurationModel;
use App\Modules\Shopify\Models\ShopifySkinAnalysisConfigurationModel;
use App\Services\Shopify\ShopifySkinAnalysisService;
use App\Services\Shopify\ShopifyHairAnalysisService;
use App\Services\Shopify\ShopifyAiAnalysisCallbackService;
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
                'orderImports' => (new ShopifyOrderImportService())->queue(),
                'configurations' => $configurationService->safeList(),
                'canView' => $this->hasPermission('shopify.integration.view'),
                'canManage' => $this->hasPermission('shopify.integration.manage'),
            ],
        );
    }

    public function proxySetup(): string
    {
        return view(ADMIN_VIEW . 'pages/integrations/shopify_proxy_setup', [
            'shopify' => (new ShopifyProxyConfigurationService())->summary(),
            'canManage' => $this->hasPermission('shopify.integration.manage'),
        ]);
    }

    public function hairAnalysisConfig(): string
    {
        if (! db_connect()->tableExists('shopify_hair_analysis_configurations')) {
            return view(ADMIN_VIEW . 'pages/integrations/shopify_hair_analysis', [
                'activeConfig' => null,
                'history' => [],
                'canManage' => $this->hasPermission('shopify.integration.manage'),
                'migrationRequired' => true,
            ]);
        }
        $model = new ShopifyHairAnalysisConfigurationModel();
        return view(ADMIN_VIEW . 'pages/integrations/shopify_hair_analysis', [
            'activeConfig' => $model->where('is_active', 1)->orderBy('version', 'DESC')->first(),
            'history' => $model->orderBy('version', 'DESC')->findAll(20),
            'canManage' => $this->hasPermission('shopify.integration.manage'),
            'migrationRequired' => false,
        ]);
    }

    public function skinAnalysisConfig(): string
    {
        if (! db_connect()->tableExists('shopify_skin_analysis_configurations')) return view(ADMIN_VIEW . 'pages/integrations/shopify_skin_analysis', ['activeConfig'=>null,'history'=>[],'canManage'=>$this->hasPermission('shopify.integration.manage'),'migrationRequired'=>true]);
        $model=new ShopifySkinAnalysisConfigurationModel();
        return view(ADMIN_VIEW . 'pages/integrations/shopify_skin_analysis', ['activeConfig'=>$model->where('is_active',1)->orderBy('version','DESC')->first(),'history'=>$model->orderBy('version','DESC')->findAll(20),'canManage'=>$this->hasPermission('shopify.integration.manage'),'migrationRequired'=>false]);
    }

    public function saveSkinAnalysisConfig(): ResponseInterface
    {
        if (! db_connect()->tableExists('shopify_skin_analysis_configurations')) return $this->response->setStatusCode(503)->setJSON(['status'=>false,'msg'=>'Run the database migrations before saving Skin Analysis configuration.']);
        $prompt=trim((string)$this->request->getPost('prompt_text'));$schema=trim((string)$this->request->getPost('schema_json'));$modelName=trim((string)$this->request->getPost('model'))?:'gpt-4.1-mini';$temperature=(float)$this->request->getPost('temperature');
        if(mb_strlen($prompt)<20||mb_strlen($prompt)>500000)return $this->response->setStatusCode(422)->setJSON(['status'=>false,'msg'=>'The skin analysis prompt must be between 20 and 500,000 characters.']);
        try{$decoded=json_decode($schema,true,512,JSON_THROW_ON_ERROR);}catch(\JsonException){return $this->response->setStatusCode(422)->setJSON(['status'=>false,'msg'=>'The skin analysis schema must contain valid JSON.']);}
        if(!is_array($decoded)||!preg_match('/^[A-Za-z0-9._-]{1,100}$/',$modelName)||$temperature<0||$temperature>2)return $this->response->setStatusCode(422)->setJSON(['status'=>false,'msg'=>'Skin analysis schema, model, or temperature is invalid.']);
        $db=db_connect();$now=date('Y-m-d H:i:s');$db->transStart();$next=(int)($db->table('shopify_skin_analysis_configurations')->selectMax('version')->get()->getRow('version')??0)+1;$db->table('shopify_skin_analysis_configurations')->where('is_active',1)->update(['is_active'=>0,'updated_at'=>$now]);$id=$db->table('shopify_skin_analysis_configurations')->insert(['version'=>$next,'prompt_text'=>$prompt,'schema_json'=>json_encode($decoded,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),'model'=>$modelName,'temperature'=>number_format($temperature,2,'.',''),'is_active'=>1,'created_by'=>(int)($this->user['id']??0)?:null,'created_at'=>$now,'updated_at'=>$now]);$db->transComplete();if(!$id||$db->transStatus()===false)return $this->response->setStatusCode(503)->setJSON(['status'=>false,'msg'=>'Skin analysis configuration could not be saved safely.']);service('audit')->record('admin.shopify_skin_analysis_configuration_saved',['version'=>$next,'model'=>$modelName]);return $this->response->setJSON(['status'=>true,'msg'=>'Skin Analysis configuration saved.','data'=>['version'=>$next]]);
    }

    public function skinAnalysisLeads(): string { if(!db_connect()->tableExists('shopify_skin_analysis_leads'))return view(ADMIN_VIEW.'pages/integrations/shopify_skin_analysis_leads',['leads'=>[],'migrationRequired'=>true]);return view(ADMIN_VIEW.'pages/integrations/shopify_skin_analysis_leads',['leads'=>(new ShopifySkinAnalysisService())->adminList(),'migrationRequired'=>false]); }
    public function skinAnalysisLead(int $id): string { $lead=(new ShopifySkinAnalysisService())->adminFind($id);if(!$lead)throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Skin analysis lead not found.');return view(ADMIN_VIEW.'pages/integrations/shopify_skin_analysis_lead',['lead'=>$lead]); }
    public function aiAnalysisCallbackRequests(): string { if (! db_connect()->tableExists('shopify_ai_analysis_callback_requests')) return view(ADMIN_VIEW.'pages/integrations/shopify_ai_analysis_callbacks',['requests'=>[],'migrationRequired'=>true]); return view(ADMIN_VIEW.'pages/integrations/shopify_ai_analysis_callbacks',['requests'=>(new ShopifyAiAnalysisCallbackService())->adminList(),'migrationRequired'=>false]); }
    public function linkSkinAnalysisLeadPatient(int $id): ResponseInterface { try{return $this->response->setJSON(['status'=>true,'data'=>(new ShopifySkinAnalysisService())->linkPatient($id,(string)$this->request->getPost('patient_reference'),(int)($this->user['id']??0))]);}catch(\Throwable $e){return $this->response->setStatusCode(422)->setJSON(['status'=>false,'msg'=>$e->getMessage()]);} }
    public function createSkinAnalysisLeadPatient(int $id): ResponseInterface { try{return $this->response->setJSON(['status'=>true,'data'=>(new ShopifySkinAnalysisService())->createPatient($id,$this->request->getPost(),(int)($this->user['id']??0))]);}catch(\Throwable $e){return $this->response->setStatusCode(422)->setJSON(['status'=>false,'msg'=>$e->getMessage()]);} }
    public function retrySkinAnalysisLead(int $id): ResponseInterface { if (! $this->request->isAJAX()) return $this->response->setStatusCode(405)->setJSON(['status'=>false,'msg'=>'Invalid retry request.']); try{return $this->response->setJSON(['status'=>true,'msg'=>'Skin analysis completed.','data'=>(new ShopifySkinAnalysisService())->retry($id)]);}catch(\Throwable $e){log_message('error','Skin analysis retry failed '.get_class($e));return $this->response->setStatusCode(503)->setJSON(['status'=>false,'msg'=>'The skin analysis could not be retried safely.']);} }

    public function hairAnalysisLeads(): string
    {
        if (! db_connect()->tableExists('shopify_hair_analysis_leads')) {
            return view(ADMIN_VIEW . 'pages/integrations/shopify_hair_analysis_leads', ['leads' => [], 'migrationRequired' => true]);
        }
        return view(ADMIN_VIEW . 'pages/integrations/shopify_hair_analysis_leads', [
            'leads' => (new ShopifyHairAnalysisService())->adminList(),
            'canManage' => $this->hasPermission('shopify.integration.manage'),
            'migrationRequired' => false,
        ]);
    }

    public function hairAnalysisLead(int $id): string
    {
        if (! db_connect()->tableExists('shopify_hair_analysis_leads')) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Hair analysis lead table is not available.');
        }
        $lead = (new ShopifyHairAnalysisService())->adminFind($id);
        if ($lead === null) throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Hair analysis lead not found.');
        return view(ADMIN_VIEW . 'pages/integrations/shopify_hair_analysis_lead', ['lead' => $lead]);
    }

    public function linkHairAnalysisLeadPatient(int $id): ResponseInterface
    {
        if (! $this->request->isAJAX()) return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid link request.']);
        try {
            $data = (new ShopifyHairAnalysisService())->linkPatient($id, (string) $this->request->getPost('patient_reference'), (int) ($this->user['id'] ?? 0));
            return $this->response->setJSON(['status' => true, 'msg' => 'Hair analysis lead linked to the ROOTINS patient.', 'data' => $data]);
        } catch (InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            log_message('error', 'Hair analysis patient link failed ' . get_class($exception));
            return $this->response->setStatusCode(503)->setJSON(['status' => false, 'msg' => 'The lead could not be linked safely.']);
        }
    }

    public function retryHairAnalysisLead(int $id): ResponseInterface
    {
        if (! $this->request->isAJAX()) return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid retry request.']);
        try {
            return $this->response->setJSON(['status' => true, 'msg' => 'Hair analysis completed.', 'data' => (new ShopifyHairAnalysisService())->retry($id)]);
        } catch (\Throwable $exception) {
            log_message('error', 'Hair analysis retry failed ' . get_class($exception));
            return $this->response->setStatusCode(503)->setJSON(['status' => false, 'msg' => 'The hair analysis could not be retried safely.']);
        }
    }

    public function createHairAnalysisLeadPatient(int $id): ResponseInterface
    {
        if (! $this->request->isAJAX()) return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid patient request.']);
        try {
            $data = (new ShopifyHairAnalysisService())->createPatient($id, $this->request->getPost(), (int) ($this->user['id'] ?? 0));
            return $this->response->setJSON(['status' => true, 'msg' => 'Global ROOTINS patient created from the lead.', 'data' => $data]);
        } catch (InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            log_message('error', 'Hair analysis patient creation failed ' . get_class($exception));
            return $this->response->setStatusCode(503)->setJSON(['status' => false, 'msg' => 'The patient could not be created safely.']);
        }
    }

    public function saveHairAnalysisConfig(): ResponseInterface
    {
        if (! db_connect()->tableExists('shopify_hair_analysis_configurations')) {
            return $this->response->setStatusCode(503)->setJSON(['status' => false, 'msg' => 'Run the database migrations before saving AI Hair Analysis configuration.', 'code' => 'SHOPIFY_HAIR_ANALYSIS_MIGRATION_REQUIRED']);
        }
        if (! $this->request->isAJAX()) {
            return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid configuration request.']);
        }

        $prompt = trim((string) $this->request->getPost('prompt_text'));
        $schema = trim((string) $this->request->getPost('schema_json'));
        $modelName = trim((string) $this->request->getPost('model')) ?: 'gpt-4.1-mini';
        $temperature = (float) $this->request->getPost('temperature');
        if (mb_strlen($prompt) < 20 || mb_strlen($prompt) > 500000) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => 'The analysis prompt must be between 20 and 500,000 characters.']);
        }
        if ($schema === '' || mb_strlen($schema) > 500000) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => 'A JSON schema is required.']);
        }
        try {
            $decoded = json_decode($schema, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => 'The analysis schema must contain valid JSON.']);
        }
        if (! is_array($decoded)) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => 'The analysis schema must be a JSON object.']);
        }
        if (! preg_match('/^[A-Za-z0-9._-]{1,100}$/', $modelName) || $temperature < 0 || $temperature > 2) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => 'Model or temperature is invalid.']);
        }

        $db = db_connect();
        $db->transStart();
        $next = (int) ($db->table('shopify_hair_analysis_configurations')->selectMax('version')->get()->getRow('version') ?? 0) + 1;
        $db->table('shopify_hair_analysis_configurations')->where('is_active', 1)->update(['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        $id = $db->table('shopify_hair_analysis_configurations')->insert([
            'version' => $next,
            'prompt_text' => $prompt,
            'schema_json' => json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'model' => $modelName,
            'temperature' => number_format($temperature, 2, '.', ''),
            'is_active' => 1,
            'created_by' => (int) ($this->user['id'] ?? 0) ?: null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $db->transComplete();
        if (! $id || $db->transStatus() === false) {
            return $this->response->setStatusCode(503)->setJSON(['status' => false, 'msg' => 'The AI Hair Analysis configuration could not be saved safely.']);
        }
        service('audit')->record('admin.shopify_hair_analysis_configuration_saved', ['version' => $next, 'model' => $modelName]);
        return $this->response->setJSON(['status' => true, 'msg' => 'AI Hair Analysis configuration saved.', 'data' => ['version' => $next]]);
    }

    public function saveProxyConfiguration(): ResponseInterface
    {
        try {
            $data = (new ShopifyProxyConfigurationService())->save($this->request->getPost(), (int) ($this->user['id'] ?? 0));
            return $this->response->setJSON(['status' => true, 'msg' => 'Shopify App Proxy configuration saved.', 'data' => $data]);
        } catch (InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => $exception->getMessage()]);
        } catch (\Throwable $exception) {
            log_message('error', 'Shopify proxy configuration save failed safely.');
            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Shopify App Proxy configuration could not be saved safely.']);
        }
    }

    public function testProxyConnection(): ResponseInterface
    {
        try {
            $configuration = new ShopifyProxyConfigurationService();
            $summary = $configuration->summary();
            $submittedClientId = trim((string) $this->request->getPost('core_api_client_id'));
            $submittedClientSecret = trim((string) $this->request->getPost('core_api_client_secret'));
            if (! $summary['configured'] || ((! $summary['has_client_id'] && $submittedClientId === '') || (! $summary['has_client_secret'] && $submittedClientSecret === '')) || ! $summary['proxy_enabled']) {
                return $this->proxyTestError(422, 'SHOPIFY_PROXY_CONFIGURATION_MISSING', 'Configure and enable the separate Core API Shopify App Proxy before testing it.');
            }
            $runtime = $configuration->runtime();
            if ($submittedClientId !== '') $runtime['client_id'] = $submittedClientId;
            if ($submittedClientSecret !== '') $runtime['client_secret'] = $submittedClientSecret;
            $result = (new ShopifyProxyCredentialVerificationService())->verify($runtime);
            service('audit')->record('shopify.proxy_connection_tested', ['status' => 'verified', 'category' => 'SHOPIFY_PROXY_CONFIGURATION_READY']);
            return $this->response->setJSON(['status' => true, 'msg' => 'Shopify Core API Proxy credentials verified.', 'data' => $result]);
        } catch (ShopifyProxyCredentialVerificationException $exception) {
            service('audit')->record('shopify.proxy_connection_tested', ['status' => 'failed', 'category' => $exception->errorCode]);
            return $this->proxyTestError($exception->httpStatus, $exception->errorCode, $exception->getMessage());
        } catch (\Throwable $exception) {
            log_message('error', 'Shopify Core API Proxy credential verification failed safely.');
            return $this->proxyTestError(503, 'SHOPIFY_PROXY_CONNECTION_FAILED', 'Shopify Core API Proxy credential verification could not be completed safely.');
        }
    }

    private function proxyTestError(int $status, string $code, string $message): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['status' => false, 'error' => ['code' => $code, 'message' => $message]]);
    }

    public function rotateProxySecret(): ResponseInterface
    {
        try {
            $data = (new ShopifyProxyConfigurationService())->rotateSecret((string) $this->request->getPost('core_api_client_secret'), (int) ($this->user['id'] ?? 0));
            return $this->response->setJSON(['status' => true, 'msg' => 'Shopify Core API App Proxy secret rotated.', 'data' => $data]);
        } catch (InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => $exception->getMessage()]);
        } catch (\Throwable) {
            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Shopify Core API App Proxy secret could not be rotated safely.']);
        }
    }

    public function enableProxy(): ResponseInterface { return $this->setProxyStatus(true); }
    public function disableProxy(): ResponseInterface { return $this->setProxyStatus(false); }
    private function setProxyStatus(bool $enabled): ResponseInterface
    {
        try { return $this->response->setJSON(['status' => true, 'msg' => $enabled ? 'Shopify Core API App Proxy enabled.' : 'Shopify Core API App Proxy disabled.', 'data' => (new ShopifyProxyConfigurationService())->setEnabled($enabled, (int) ($this->user['id'] ?? 0))]); }
        catch (InvalidArgumentException $exception) { return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => $exception->getMessage()]); }
        catch (\Throwable) { return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Shopify Core API App Proxy status could not be changed safely.']); }
    }

    public function webhooks(): string
    {
        $configuration = new ShopifyConfiguration();

        return view(ADMIN_VIEW . 'pages/integrations/shopify_webhooks', [
            'shopify' => $configuration->safeSummary(),
            'webhooks' => (new \App\Services\Shopify\ShopifyWebhookCatalogService())->catalog($configuration),
            'callbackBase' => (new \App\Services\Shopify\ShopifyWebhookCatalogService())->publicCallbackBase($configuration->appUrl()),
            'canManage' => $this->hasPermission('shopify.integration.manage'),
        ]);
    }

    public function customers(): string
    {
        return view(ADMIN_VIEW . 'pages/integrations/shopify_customers', [
            'shopify' => (new ShopifyConfiguration())->safeSummary(),
            'customerLinks' => (new ShopifyCustomerIdentityService())->reviewQueue(),
            'patientOptions' => (new ShopifyCustomerIdentityService())->patientOptions(),
            'canManage' => $this->hasPermission('shopify.integration.manage'),
        ]);
    }

    public function onDemandApis(): string
    {
        return view(ADMIN_VIEW . 'pages/integrations/shopify_on_demand_apis', [
            'shopify' => (new ShopifyConfiguration())->safeSummary(),
        ]);
    }

    public function validateWebhook(string $topic): ResponseInterface
    {
        try {
            $result = (new ShopifyWebhookValidationService())->validateTopic($topic);
            service('audit')->record('shopify.webhook_validation_completed', ['topic' => $topic, 'subscription_status' => $result['shopify_subscription']['status'], 'endpoint_status' => $result['rootins_endpoint']['status']]);

            return $this->response->setJSON(['status' => true, 'msg' => 'Webhook validation completed.', 'data' => $result]);
        } catch (InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => $exception->getMessage()]);
        } catch (\Throwable) {
            service('audit')->record('shopify.webhook_validation_failed', ['topic' => $topic, 'reason' => 'internal']);

            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Webhook validation could not be completed safely.']);
        }
    }

    public function validateAllWebhooks(): ResponseInterface
    {
        try {
            $results = (new ShopifyWebhookValidationService())->validateAll();
            service('audit')->record('shopify.webhook_validation_all_completed', ['count' => count($results)]);

            return $this->response->setJSON(['status' => true, 'msg' => 'Webhook validation completed.', 'data' => $results]);
        } catch (\Throwable) {
            service('audit')->record('shopify.webhook_validation_all_failed', ['reason' => 'internal']);

            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Webhook validation could not be completed safely.']);
        }
    }

    public function testWebhookEndpoint(string $topic): ResponseInterface
    {
        $routeTopic = trim($topic);
        $postedTopic = trim((string) $this->request->getPost('topic'));
        $selectedTopic = $postedTopic !== '' ? $postedTopic : $routeTopic;

        try {
            $result = (new ShopifyWebhookValidationService())->testEndpoint($selectedTopic);
            service('audit')->record('shopify.webhook_endpoint_test_completed', ['topic' => $selectedTopic, 'status' => $result['status'], 'error_category' => $result['error_category']]);

            return $this->response->setJSON(['status' => $result['status'] === 'Passed', 'msg' => $result['status'] === 'Passed' ? 'ROOTINS endpoint test passed.' : 'ROOTINS endpoint test did not pass.', 'data' => $result]);
        } catch (InvalidArgumentException $exception) {
            service('audit')->record('shopify.webhook_endpoint_test_failed', ['topic' => $selectedTopic, 'reason' => 'validation']);

            return $this->response->setStatusCode(422)->setJSON([
                'status' => false,
                'code' => 'WEBHOOK_TEST_VALIDATION_FAILED',
                'msg' => 'The ROOTINS endpoint test request could not be validated.',
                'errors' => ['topic' => 'The selected webhook topic is not valid for this endpoint test.'],
            ]);
        } catch (\Throwable) {
            service('audit')->record('shopify.webhook_endpoint_test_failed', ['topic' => $topic, 'reason' => 'internal']);

            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'ROOTINS endpoint test could not be completed safely.']);
        }
    }

    public function checkShopifySubscriptions(): ResponseInterface
    {
        try {
            $result = (new ShopifyWebhookRegistrationService())->sync();
            service('audit')->record('shopify.webhook_subscriptions_checked', [
                'registered' => $result['registered_correctly'],
                'missing' => $result['missing'],
                'url_mismatch' => $result['url_mismatch'],
                'inactive' => $result['inactive'],
                'failed' => $result['failed'],
            ]);

            return $this->response->setJSON(['status' => true, 'msg' => 'Shopify subscriptions checked.', 'data' => $result]);
        } catch (ShopifyConfigurationException) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'code' => 'SHOPIFY_CONFIGURATION_MISSING', 'msg' => 'Shopify configuration is missing.']);
        } catch (ShopifyProviderException) {
            return $this->response->setStatusCode(502)->setJSON(['status' => false, 'code' => 'SHOPIFY_API_UNAVAILABLE', 'msg' => 'Shopify subscriptions could not be checked safely.']);
        } catch (\Throwable) {
            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'code' => 'SHOPIFY_SUBSCRIPTION_CHECK_FAILED', 'msg' => 'Shopify subscriptions could not be checked safely.']);
        }
    }

    public function verifyLiveDelivery(): ResponseInterface
    {
        try {
            $rows = (new \App\Services\Shopify\ShopifyWebhookCatalogService())->catalog(new ShopifyConfiguration());
            $received = count(array_filter($rows, static fn (array $row): bool => ($row['live_status'] ?? 'Not yet verified') === 'Live event received'));
            service('audit')->record('shopify.webhook_live_delivery_checked', [
                'topics_received' => $received,
                'topics_total' => count($rows),
            ]);

            return $this->response->setJSON([
                'status' => true,
                'msg' => $received > 0 ? 'Live delivery evidence retrieved.' : 'No live Shopify delivery has been received yet.',
                'data' => [
                    'status' => $received > 0 ? 'Live event received' : 'Not yet verified',
                    'received_count' => $received,
                    'topics' => array_map(static fn (array $row): array => [
                        'topic' => $row['topic'],
                        'last_received' => $row['last_received'],
                        'last_processed' => $row['last_processed'],
                        'event_reference' => $row['last_event_reference'],
                        'processing_status' => $row['last_result'],
                        'error_category' => $row['last_error_category'],
                        'live_status' => $row['live_status'],
                    ], $rows),
                ],
            ]);
        } catch (\Throwable) {
            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'code' => 'SHOPIFY_LIVE_DELIVERY_CHECK_FAILED', 'msg' => 'Live delivery evidence could not be checked safely.']);
        }
    }

    public function syncWebhooks(): ResponseInterface
    {
        try {
            $result = (new ShopifyWebhookRegistrationService())->sync();
            $this->audit->record('shopify.webhooks_previewed', [
                'registered_correctly' => $result['registered_correctly'],
                'missing' => $result['missing'],
                'url_mismatch' => $result['url_mismatch'],
                'inactive' => $result['inactive'],
                'failed' => $result['failed'],
            ]);

            return $this->response->setJSON(['status' => true, 'msg' => 'Shopify webhook change preview ready.', 'data' => $result]);
        } catch (ShopifyConfigurationException) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'code' => 'SHOPIFY_CONFIGURATION_MISSING', 'msg' => 'Shopify configuration is missing.']);
        } catch (ShopifyProviderException) {
            return $this->response->setStatusCode(502)->setJSON(['status' => false, 'code' => 'SHOPIFY_API_UNAVAILABLE', 'msg' => 'Shopify webhook subscriptions could not be read safely.']);
        } catch (\Throwable) {
            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'code' => 'SHOPIFY_WEBHOOK_SYNC_FAILED', 'msg' => 'Shopify webhook subscriptions could not be synchronized safely.']);
        }
    }

    public function registerMissingWebhooks(): ResponseInterface
    {
        try {
            if (trim((string) $this->request->getPost('confirmation')) === '' || trim((string) $this->request->getPost('plan_hash')) === '') {
                return $this->response->setStatusCode(422)->setJSON(['status' => false, 'code' => 'SHOPIFY_CONFIRMATION_REQUIRED', 'msg' => 'Review the current Shopify webhook subscriptions and explicitly confirm the proposed changes first.']);
            }
            return $this->applyWebhooks();
        } catch (ShopifyConfigurationException) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'code' => 'SHOPIFY_CONFIGURATION_MISSING', 'msg' => 'Shopify configuration is missing.']);
        } catch (ShopifyProviderException) {
            return $this->response->setStatusCode(502)->setJSON(['status' => false, 'code' => 'SHOPIFY_API_UNAVAILABLE', 'msg' => 'Shopify webhook subscriptions could not be registered safely.']);
        } catch (\Throwable) {
            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'code' => 'SHOPIFY_WEBHOOK_REGISTRATION_FAILED', 'msg' => 'Shopify webhook registration could not be completed safely.']);
        }
    }

    public function previewWebhooks(): ResponseInterface
    {
        try {
            $result = (new ShopifyWebhookRegistrationService())->preview();
            $this->audit->record('shopify.webhooks_previewed', [
                'to_create' => count($result['to_create'] ?? []),
                'already_existing' => count($result['already_existing'] ?? []),
                'to_remove' => count($result['to_remove'] ?? []),
                'skipped' => count($result['skipped'] ?? []),
                'failed' => (int) ($result['failed'] ?? 0),
                'ownership_verified' => (bool) ($result['ownership_verified'] ?? false),
            ]);

            return $this->response->setJSON(['status' => true, 'msg' => 'Shopify webhook change preview ready.', 'data' => $result]);
        } catch (ShopifyConfigurationException) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'code' => 'SHOPIFY_CONFIGURATION_MISSING', 'msg' => 'Shopify configuration is missing.']);
        } catch (ShopifyProviderException) {
            return $this->response->setStatusCode(502)->setJSON(['status' => false, 'code' => 'SHOPIFY_API_UNAVAILABLE', 'msg' => 'Shopify subscriptions could not be queried safely.']);
        } catch (\Throwable) {
            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'code' => 'SHOPIFY_WEBHOOK_PREVIEW_FAILED', 'msg' => 'Shopify webhook changes could not be previewed safely.']);
        }
    }

    public function applyWebhooks(): ResponseInterface
    {
        try {
            $result = (new ShopifyWebhookRegistrationService())->apply((string) $this->request->getPost('confirmation'), (string) $this->request->getPost('plan_hash'), (string) ($this->request->getPost('mode') ?: 'all'));
            $this->audit->record('shopify.webhooks_applied', [
                'created' => (int) ($result['created'] ?? 0),
                'already_existing' => (int) ($result['already_existing'] ?? 0),
                'removed' => (int) ($result['removed'] ?? 0),
                'deleted' => (int) ($result['deleted'] ?? $result['removed'] ?? 0),
                'skipped' => (int) ($result['skipped'] ?? 0),
                'failed' => (int) ($result['failed'] ?? 0),
            ]);

            return $this->response->setJSON(['status' => ($result['failed'] ?? 0) === 0, 'msg' => 'Shopify webhook changes processed.', 'data' => $result]);
        } catch (InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'code' => 'SHOPIFY_CONFIRMATION_REQUIRED', 'msg' => $exception->getMessage()]);
        } catch (ShopifyProviderException $exception) {
            return $this->response->setStatusCode($exception->httpStatus === 409 ? 409 : 502)->setJSON(['status' => false, 'code' => $exception->errorCode ?: 'SHOPIFY_WEBHOOK_APPLY_FAILED', 'msg' => $exception->getMessage()]);
        } catch (ShopifyConfigurationException) {
            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'code' => 'SHOPIFY_CONFIGURATION_MISSING', 'msg' => 'Shopify configuration is missing.']);
        } catch (\Throwable) {
            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'code' => 'SHOPIFY_WEBHOOK_APPLY_FAILED', 'msg' => 'Shopify webhook changes could not be applied safely.']);
        }
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

    public function testCustomerDetails(): ResponseInterface
    {
        $customerId = trim((string) $this->request->getPost('customer_id'));
        $eventId = (int) $this->request->getPost('event_id');

        try {
            $details = (new ShopifyCustomerIdentityService())->customerDetails($customerId !== '' ? $customerId : null, $eventId > 0 ? $eventId : null);
            service('audit')->record('shopify.customer_details_test_succeeded', [
                'store_domain' => $details['store_domain'],
                'customer_id' => $details['shopify_customer_id'],
                'match_status' => $details['match_status'],
            ]);

            return $this->response->setJSON(['status' => true, 'msg' => 'Customer details loaded.', 'data' => $details]);
        } catch (InvalidArgumentException $exception) {
            service('audit')->record('shopify.customer_details_test_failed', [
                'store_domain' => (new ShopifyConfiguration())->storeDomain(),
                'customer_id' => $customerId !== '' ? $customerId : null,
                'reason' => 'not_found_or_invalid',
            ]);

            return $this->response->setStatusCode(422)->setJSON(['status' => false, 'msg' => $exception->getMessage()]);
        } catch (\Throwable) {
            service('audit')->record('shopify.customer_details_test_failed', [
                'store_domain' => (new ShopifyConfiguration())->storeDomain(),
                'customer_id' => $customerId !== '' ? $customerId : null,
                'reason' => 'internal',
            ]);

            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Customer details could not be loaded safely.']);
        }
    }

    public function customer(string $customerId): ResponseInterface
    {
        try {
            $details = (new ShopifyCustomerLookupService())->lookup($customerId);
            $this->audit->record('shopify.customer_lookup_succeeded', [
                'actor' => (int) ($this->user['id'] ?? 0),
                'store_domain' => $details['store_domain'],
                'customer_id' => $details['shopify_customer_id'],
                'category' => 'success',
            ]);

            return $this->response->setJSON(['status' => true, 'msg' => 'Shopify customer details loaded.', 'data' => $details]);
        } catch (ShopifyCustomerLookupException $exception) {
            $this->audit->record('shopify.customer_lookup_failed', [
                'actor' => (int) ($this->user['id'] ?? 0),
                'store_domain' => $exception->storeDomain,
                'customer_id' => $exception->publicCustomerId,
                'category' => $exception->errorCode,
            ]);

            return $this->response->setStatusCode($exception->httpStatus)->setJSON([
                'status' => false,
                'code' => $exception->errorCode,
                'msg' => $exception->getMessage(),
            ]);
        } catch (\Throwable) {
            $this->audit->record('shopify.customer_lookup_failed', [
                'actor' => (int) ($this->user['id'] ?? 0),
                'store_domain' => null,
                'customer_id' => null,
                'category' => 'SHOPIFY_API_UNAVAILABLE',
            ]);

            return $this->response->setStatusCode(503)->setJSON([
                'status' => false,
                'code' => 'SHOPIFY_API_UNAVAILABLE',
                'msg' => 'Shopify customer details are temporarily unavailable.',
            ]);
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
        $correlationId = bin2hex(random_bytes(16));
        $importId = (int) $this->request->getPost('import_id');
        $prescriptionReference = trim((string) $this->request->getPost('prescription_reference'));

        try {
            $result = (new ShopifyOrderImportService())->importExisting($importId, $prescriptionReference !== '' ? $prescriptionReference : null, $correlationId);

            $body = (array) ($result['body'] ?? []);
            $body['correlation_id'] = $correlationId;
            return $this->response->setStatusCode((int) $result['http'])->setJSON($body);
        } catch (\Throwable $exception) {
            log_message('error', 'Shopify order import request failed safely exception=' . get_class($exception) . ' file=' . basename($exception->getFile()) . ' line=' . $exception->getLine());

            return $this->response->setStatusCode(500)->setJSON(['status' => false, 'msg' => 'Shopify order could not be imported safely.']);
        }
    }
}
