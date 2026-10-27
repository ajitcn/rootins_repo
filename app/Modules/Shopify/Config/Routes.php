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
$routes->post('rootins-proxy/prescriptions/upload', '\App\Modules\Shopify\Controllers\Proxy::upload');
$routes->get('rootins-proxy/orders/status', '\App\Modules\Shopify\Controllers\CustomerOrderStatus::show');
