<?php

use CodeIgniter\Router\RouteCollection;
/** @var RouteCollection $routes */
$routes->group('farmassist', ['namespace' => 'Farmassist\Controllers', 'filter' => 'auth'], function ($routes) {
    $routes->get('/', 'Operations::index', ['filter' => 'permission:prescription.view']);
    $routes->get('prescriptions', 'PrescriptionReview::index', ['filter' => 'permission:prescription.view']);
    $routes->get('audit', 'Audit::index', ['filter' => 'permission:audit.view']);
    $routes->post('prescriptions/review', 'PrescriptionReview::review', ['filter' => 'permission:prescription.edit']);
    $routes->post('orders/create', 'Operations::createOrder', ['filter' => 'permission:medicine.create']);
    $routes->post('orders/payment', 'Operations::reconcilePayment', ['filter' => 'permission:medicine.edit']);
    $routes->post('orders/delivery', 'Operations::updateDelivery', ['filter' => 'permission:medicine.edit']);
});
