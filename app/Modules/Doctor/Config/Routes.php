<?php

use CodeIgniter\Router\RouteCollection;
/** @var RouteCollection $routes */
$routes->group('doctor', ['namespace' => 'Doctor\Controllers', 'filter' => 'auth'], function ($routes) {
    $routes->get('/', 'Dashboard::index', ['filter' => 'permission:doctor.view']);
    $routes->get('appointments/(:num)', 'Appointment::view/$1', ['filter' => 'permission:appointment.view']);
    $routes->get('clinical', 'Clinical::index', ['filter' => 'permission:consultation.view']);
    $routes->get('appointments', 'Records::appointments', ['filter' => 'permission:appointment.view']);
    $routes->get('follow-ups', 'Records::followUps', ['filter' => 'permission:consultation.view']);
    $routes->post('follow-ups/create', 'Records::createFollowUp', ['filter' => 'permission:consultation.edit']);
    $routes->get('billing', 'Records::billing', ['filter' => 'permission:billing.view']);
    $routes->get('prescriptions', 'Records::prescriptions', ['filter' => 'permission:prescription.view']);
    $routes->get('video/(:num)', 'VideoConsultation::view/$1', ['filter' => 'permission:appointment.view']);
    $routes->get('notifications', 'Notification::index', ['filter' => 'permission:doctor.view']);
    $routes->post('notifications/(:num)/read', 'Notification::read/$1', ['filter' => 'permission:doctor.view']);
    $routes->get('profile', 'Profile::index', ['filter' => 'permission:doctor.view']);
    $routes->post('profile', 'Profile::update', ['filter' => 'permission:doctor.edit']);
    $routes->get('patients', 'Patient::index', ['filter' => 'permission:patient.view']);
    $routes->get('patients/(:num)', 'Patient::view/$1', ['filter' => 'permission:patient.view']);
    $routes->post('patients/create', 'Patient::create', ['filter' => 'permission:patient.create']);
    $routes->post('patients/update/(:num)', 'Patient::update/$1', ['filter' => 'permission:patient.edit']);
    $routes->post('appointments/create', 'Records::createAppointment', ['filter' => 'permission:appointment.create']);
    $routes->post('appointments/reschedule/(:num)', 'Records::reschedule/$1', ['filter' => 'permission:appointment.edit']);
    $routes->post('appointments/cancel/(:num)', 'Records::cancel/$1', ['filter' => 'permission:appointment.edit']);
    $routes->post('appointments/queue/(:num)', 'Clinical::queue/$1', ['filter' => 'permission:appointment.edit']);
    $routes->post('consultations/save', 'Clinical::saveConsultation', ['filter' => 'permission:consultation.edit']);
$routes->post('prescriptions/save', 'Clinical::savePrescription', ['filter' => 'permission:prescription.create']);
});
