<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->group('pharmacy', ['namespace' => 'Pharmacy\Controllers', 'filter' => 'auth'], function ($routes) {
    $routes->get('/', 'Operations::index', ['filter' => 'permission:pharmacy.dashboard.view']);
    $routes->get('prescriptions', 'PrescriptionReview::index', ['filter' => 'permission:pharmacy.prescription.review.view']);
    $routes->get('inventory', 'Inventory::index', ['filter' => 'permission:pharmacy.inventory.view']);
    $routes->post('inventory/adjust', 'Inventory::adjust', ['filter' => 'permission:pharmacy.inventory.batch.adjust']);
    $routes->post('inventory/create', 'Inventory::create', ['filter' => 'permission:pharmacy.inventory.batch.create']);
    $routes->get('orders', 'Orders::index', ['filter' => 'permission:pharmacy.order.view']);
    $routes->get('orders/detail/(:num)', 'Orders::detail/$1', ['filter' => 'permission:pharmacy.order.view']);
    $routes->get('shopify-orders', 'ShopifyOrders::index', ['filter' => 'permission:pharmacy.shopify_orders.view']);
    $routes->get('shopify-orders/(:num)', 'ShopifyOrders::detail/$1', ['filter' => 'permission:pharmacy.shopify_orders.view']);
    $routes->get('shopify-orders/(:num)/prescription', 'ShopifyOrders::viewPrescription/$1', ['filter' => 'permission:pharmacy.shopify_orders.view']);
    $routes->post('shopify-orders/action/(:num)', 'ShopifyOrders::action/$1', ['filter' => 'permission:pharmacy.shopify_orders.manage']);
    $routes->post('shopify-orders/prescriptions/review', 'ShopifyOrders::reviewPrescription', ['filter' => 'permission:pharmacy.shopify_orders.manage']);
    $routes->get('orders/labels/(:num)', 'Orders::labels/$1', ['filter' => 'permission:pharmacy.order.label.view']);
    $routes->get('audit', 'Audit::index', ['filter' => 'permission:pharmacy.audit.view']);
    $routes->get('billing', 'Billing::index', ['filter' => 'permission:pharmacy.billing.view']);
    $routes->get('billing/detail/(:num)', 'Billing::detail/$1', ['filter' => 'permission:pharmacy.billing.view']);
    $routes->get('delivery', 'Delivery::index', ['filter' => 'permission:pharmacy.delivery.view']);
    $routes->get('delivery/detail/(:num)', 'Delivery::detail/$1', ['filter' => 'permission:pharmacy.delivery.view']);
    $routes->get('notifications', 'Notification::index', ['filter' => 'permission:pharmacy.notification.view']);
    $routes->get('notifications/unread-count', 'Notification::unreadCount', ['filter' => 'permission:pharmacy.notification.view']);
    $routes->post('notifications/(:num)/read', 'Notification::read/$1', ['filter' => 'permission:pharmacy.notification.view']);
    $routes->post('notifications/read-all', 'Notification::readAll', ['filter' => 'permission:pharmacy.notification.view']);
    $routes->post('prescriptions/review', 'PrescriptionReview::review', ['filter' => 'permission:pharmacy.prescription.review.view']);
    $routes->post('orders/create', 'Orders::create', ['filter' => 'permission:pharmacy.order.create']);
    $routes->post('orders/cancel', 'Orders::cancel', ['filter' => 'permission:pharmacy.order.cancel']);
    $routes->post('orders/labels/print', 'Orders::printLabels', ['filter' => 'permission:pharmacy.order.label.print']);
    $routes->post('billing/reconcile', 'Billing::reconcile', ['filter' => 'permission:pharmacy.billing.payment.update']);
    $routes->post('billing/refund', 'Billing::refund', ['filter' => 'permission:pharmacy.billing.refund']);
    $routes->post('delivery/update', 'Delivery::update', ['filter' => 'permission:pharmacy.delivery.update']);
    $routes->post('orders/payment', 'Operations::reconcilePayment', ['filter' => 'permission:pharmacy.medicine.edit']);
    $routes->post('orders/delivery', 'Delivery::update', ['filter' => 'permission:pharmacy.delivery.update']);
});

// Temporary compatibility aliases. Pharmacy is canonical; preserve legacy links
// and POST clients while they migrate to the new route prefix.
$routes->match(['GET', 'POST'], 'farmassist', 'LegacyRedirect::index', ['namespace' => 'Pharmacy\\Controllers', 'filter' => 'auth']);
$routes->match(['GET', 'POST'], 'farmassist/(:any)', 'LegacyRedirect::path/$1', ['namespace' => 'Pharmacy\\Controllers', 'filter' => 'auth']);
