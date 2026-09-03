<?php

use CodeIgniter\Router\RouteCollection;
/** @var RouteCollection $routes */
$routes->group('patient', ['namespace' => 'Patient\Controllers', 'filter' => 'auth'], function ($routes) {
    $routes->get('/', 'Portal::index');
    $routes->get('notifications', 'Notification::index');
    $routes->post('notifications/(:num)/read', 'Notification::read/$1');
    $routes->post('profile', 'Portal::updateProfile');
    $routes->post('appointments/book', 'Portal::bookAppointment');
    $routes->post('appointments/reschedule/(:num)', 'Portal::rescheduleAppointment/$1');
    $routes->post('appointments/cancel/(:num)', 'Portal::cancelAppointment/$1');
    $routes->post('documents/upload', 'Portal::uploadDocument');
    $routes->get('documents/(:num)/download', 'Portal::downloadDocument/$1');
});
