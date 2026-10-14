<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('patient/login', '\Patient\Controllers\Auth::index');
$routes->post('patient/login', '\Patient\Controllers\Auth::authenticate');
$routes->post('patient/login/setup', '\Patient\Controllers\Auth::setupPassword');
$routes->group('patient', ['namespace' => 'Patient\Controllers', 'filter' => 'patientauth'], function ($routes) {
    $routes->get('/', 'Dashboard::index');
    $routes->get('profile', 'Pages::profile');
    $routes->get('appointments', 'Pages::appointments');
    $routes->get('prescriptions', 'Pages::prescriptions');
    $routes->get('orders', 'Pages::orders');
    $routes->get('invoices', 'Pages::invoices');
    $routes->get('visits', 'Pages::visits');
    $routes->get('documents', 'Pages::documents');
    $routes->get('support', 'Pages::support');
    $routes->get('logout', 'Auth::logout');
    $routes->get('portal', 'Portal::index');
    $routes->get('notifications', 'Notification::index');
    $routes->get('notifications/unread-count', 'Notification::unreadCount');
    $routes->post('notifications/(:num)/read', 'Notification::read/$1');
    $routes->post('notifications/read-all', 'Notification::readAll');
    $routes->post('profile', 'Portal::updateProfile');
    $routes->post('appointments/book', 'Portal::bookAppointment');
    $routes->post('appointments/reschedule/(:num)', 'Portal::rescheduleAppointment/$1');
    $routes->post('appointments/cancel/(:num)', 'Portal::cancelAppointment/$1');
    $routes->post('documents/upload', 'Portal::uploadDocument');
    $routes->get('documents/(:num)/download', 'Portal::downloadDocument/$1');
});
