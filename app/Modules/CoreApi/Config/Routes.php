<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->group('api/v1', ['namespace' => 'App\Modules\CoreApi\Controllers', 'filter' => 'coreapi'], static function ($routes): void {
    $routes->post('patients/register', 'V1::register');
    $routes->get('appointments/availability', 'V1::availability');
    $routes->post('appointments/book', 'V1::book');
    $routes->post('prescriptions/upload', 'V1::upload');
    $routes->get('prescriptions/(:segment)', 'V1::prescriptionDetails/$1');
});
