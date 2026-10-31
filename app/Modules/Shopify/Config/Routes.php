<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->group('admin/integrations/shopify', [
    'namespace' => 'App\Modules\Shopify\Controllers',
    'filter' => 'auth',
], static function ($routes): void {
    $routes->get('/', 'Integration::index', ['filter' => 'permission:shopify.integration.view']);
    $routes->get('orders', 'Integration::orders', ['filter' => 'permission:shopify.integration.view']);
    $routes->get('webhooks', 'Integration::webhooks', ['filter' => 'permission:shopify.integration.view']);
    $routes->get('proxy-setup', 'Integration::proxySetup', ['filter' => 'permission:shopify.integration.view']);
    $routes->get('ai-hair-analysis', 'Integration::hairAnalysisConfig', ['filter' => 'permission:shopify.integration.view']);
    $routes->get('ai-hair-analysis/leads', 'Integration::hairAnalysisLeads', ['filter' => 'permission:shopify.integration.view']);
    $routes->get('ai-hair-analysis/leads/(:num)', 'Integration::hairAnalysisLead/$1', ['filter' => 'permission:shopify.integration.view']);
    $routes->get('ai-skin-analysis', 'Integration::skinAnalysisConfig', ['filter' => 'permission:shopify.integration.view']);
    $routes->get('ai-skin-analysis/leads', 'Integration::skinAnalysisLeads', ['filter' => 'permission:shopify.integration.view']);
    $routes->get('ai-skin-analysis/leads/(:num)', 'Integration::skinAnalysisLead/$1', ['filter' => 'permission:shopify.integration.view']);
    $routes->get('ai-analysis/callback-requests', 'Integration::aiAnalysisCallbackRequests', ['filter' => 'permission:shopify.integration.view']);
    $routes->get('on-demand-apis', 'Integration::onDemandApis', ['filter' => 'permission:shopify.integration.view']);
    $routes->get('on-demand-apis/customers/(:any)', 'Integration::customer/$1', ['filter' => 'permission:shopify.integration.view']);
    $routes->get('customers', 'Integration::customers', ['filter' => 'permission:shopify.integration.view']);
    $routes->post('webhooks/validate/(:any)', 'Integration::validateWebhook/$1', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('webhooks/validate-all', 'Integration::validateAllWebhooks', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('webhooks/test-endpoint/(:any)', 'Integration::testWebhookEndpoint/$1', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('webhooks/check-subscriptions', 'Integration::checkShopifySubscriptions', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('webhooks/verify-live-delivery', 'Integration::verifyLiveDelivery', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('webhooks/preview', 'Integration::previewWebhooks', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('webhooks/apply', 'Integration::applyWebhooks', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('webhooks/sync', 'Integration::syncWebhooks', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('webhooks/register-missing', 'Integration::registerMissingWebhooks', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('test-connection', 'Integration::testConnection', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('proxy-setup/save', 'Integration::saveProxyConfiguration', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('ai-hair-analysis/save', 'Integration::saveHairAnalysisConfig', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('ai-hair-analysis/leads/(:num)/link-patient', 'Integration::linkHairAnalysisLeadPatient/$1', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('ai-hair-analysis/leads/(:num)/create-patient', 'Integration::createHairAnalysisLeadPatient/$1', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('ai-hair-analysis/leads/(:num)/retry', 'Integration::retryHairAnalysisLead/$1', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('ai-skin-analysis/save', 'Integration::saveSkinAnalysisConfig', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('ai-skin-analysis/leads/(:num)/link-patient', 'Integration::linkSkinAnalysisLeadPatient/$1', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('ai-skin-analysis/leads/(:num)/create-patient', 'Integration::createSkinAnalysisLeadPatient/$1', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('ai-skin-analysis/leads/(:num)/retry', 'Integration::retrySkinAnalysisLead/$1', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('proxy-setup/test', 'Integration::testProxyConnection', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('proxy-setup/rotate-secret', 'Integration::rotateProxySecret', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('proxy-setup/enable', 'Integration::enableProxy', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('proxy-setup/disable', 'Integration::disableProxy', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('configuration/save', 'Integration::saveConfiguration', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('configuration/rotate-secret', 'Integration::rotateSecret', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('customers/link', 'Integration::linkCustomer', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('customers/create', 'Integration::createPatient', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('customers/reject', 'Integration::rejectCustomer', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('customers/unlink', 'Integration::unlinkCustomer', ['filter' => 'permission:shopify.integration.manage']);
    $routes->post('customers/test-details', 'Integration::testCustomerDetails', ['filter' => 'permission:shopify.integration.view']);
    $routes->get('customers/(:any)', 'Integration::customer/$1', ['filter' => 'permission:shopify.integration.view']);
    $routes->post('orders/import', 'Integration::importOrder', ['filter' => 'permission:shopify.order.import']);
    $routes->post('orders/reconcile', 'Integration::importOrder', ['filter' => 'permission:shopify.order.reconcile']);
});

$routes->post('integrations/shopify/webhooks/orders/paid', '\App\Modules\Shopify\Controllers\Webhook::receive/orders/paid');
$routes->post('integrations/shopify/webhooks/customers/create', '\App\Modules\Shopify\Controllers\Webhook::receive/customers/create');
$routes->post('integrations/shopify/webhooks/customers/update', '\App\Modules\Shopify\Controllers\Webhook::receive/customers/update');
$routes->post('integrations/shopify/webhooks/(:any)', '\App\Modules\Shopify\Controllers\Webhook::receive/$1');
$routes->get('patient/login/shopify', '\App\Modules\Shopify\Controllers\Handoff::patientLogin');
$routes->get('integrations/shopify/appointments/availability', '\App\Modules\Shopify\Controllers\Appointment::availability');
$routes->post('integrations/shopify/appointments/book', '\App\Modules\Shopify\Controllers\Appointment::book');
$routes->post('integrations/shopify/appointments/(:num)/cancel', '\App\Modules\Shopify\Controllers\Appointment::cancel/$1');
$routes->post('integrations/shopify/appointments/(:num)/reschedule', '\App\Modules\Shopify\Controllers\Appointment::reschedule/$1');
$routes->post('integrations/shopify/prescription-uploads', '\App\Modules\Shopify\Controllers\PrescriptionUpload::upload');
$routes->get('integrations/shopify/prescription-uploads/(:num)/download', '\App\Modules\Shopify\Controllers\PrescriptionUpload::download/$1');
$routes->post('integrations/shopify/prescription-uploads/review', '\App\Modules\Shopify\Controllers\PrescriptionUpload::review', ['filter' => ['auth', 'permission:pharmacy.prescription.review.view']]);
$routes->get('rootins-proxy/health', '\App\Modules\Shopify\Controllers\Proxy::health');
$routes->get('rootins-proxy/appointments/availability', '\App\Modules\Shopify\Controllers\Proxy::availability');
$routes->post('rootins-proxy/appointments/book', '\App\Modules\Shopify\Controllers\Proxy::book');
$routes->post('rootins-proxy/patients/register', '\App\Modules\Shopify\Controllers\Proxy::register');
$routes->post('apps/rootins/patients/register', '\App\Modules\Shopify\Controllers\Proxy::register');
$routes->get('apps/rootins/prescriptions/active', '\App\Modules\Shopify\Controllers\ActivePrescriptions::show');
$routes->get('rootins-proxy/prescriptions/active', '\App\Modules\Shopify\Controllers\ActivePrescriptions::show');
$routes->post('apps/rootins/prescriptions/cart-pushed', '\App\Modules\Shopify\Controllers\ActivePrescriptions::cartPushed');
$routes->post('rootins-proxy/prescriptions/cart-pushed', '\App\Modules\Shopify\Controllers\ActivePrescriptions::cartPushed');
$routes->post('apps/rootins/prescriptions/cart-status', '\App\Modules\Shopify\Controllers\ActivePrescriptions::cartStatus');
$routes->post('rootins-proxy/prescriptions/cart-status', '\App\Modules\Shopify\Controllers\ActivePrescriptions::cartStatus');
$routes->post('public/rootins-proxy/prescriptions/cart-status', '\App\Modules\Shopify\Controllers\ActivePrescriptions::cartStatus');
$routes->post('rootins-proxy/prescriptions/upload', '\App\Modules\Shopify\Controllers\Proxy::upload');
$routes->get('rootins-proxy/orders/status', '\App\Modules\Shopify\Controllers\CustomerOrderStatus::show');
$routes->post('apps/rootins/hair-analysis/submit', '\App\Modules\Shopify\Controllers\HairAnalysis::submit');
$routes->get('apps/rootins/hair-analysis/status', '\App\Modules\Shopify\Controllers\HairAnalysis::status');
$routes->post('rootins-proxy/hair-analysis/submit', '\App\Modules\Shopify\Controllers\HairAnalysis::submit');
$routes->get('rootins-proxy/hair-analysis/status', '\App\Modules\Shopify\Controllers\HairAnalysis::status');
$routes->post('apps/rootins/skin-analysis/submit', '\App\Modules\Shopify\Controllers\SkinAnalysis::submit');
$routes->get('apps/rootins/skin-analysis/status', '\App\Modules\Shopify\Controllers\SkinAnalysis::status');
$routes->post('apps/rootins/ai-analysis/callback-request', '\App\Modules\Shopify\Controllers\AiAnalysisCallback::request');
$routes->post('rootins-proxy/ai-analysis/callback-request', '\App\Modules\Shopify\Controllers\AiAnalysisCallback::request');
$routes->post('rootins-proxy/skin-analysis/submit', '\App\Modules\Shopify\Controllers\SkinAnalysis::submit');
$routes->get('rootins-proxy/skin-analysis/status', '\App\Modules\Shopify\Controllers\SkinAnalysis::status');
