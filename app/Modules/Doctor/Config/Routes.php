<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->group('doctor', ['namespace' => 'Doctor\Controllers', 'filter' => 'auth'], function ($routes) {
    $routes->get('/', 'Dashboard::index', ['filter' => 'permission:doctor.view']);
    $routes->get('appointments/patients/search', 'Records::patientSearch', ['filter' => 'permission:appointment.create']);
    $routes->get('appointments/(:num)', 'Appointment::view/$1', ['filter' => 'permission:appointment.view']);
    $routes->get('clinical/appointments/search', 'Clinical::appointmentSearch', ['filter' => 'permission:consultation.view']);
    $routes->get('clinical/past-visits', 'Clinical::pastVisits', ['filter' => 'permission:consultation.view']);
    $routes->get('clinical/past-visits/(:num)', 'Clinical::pastVisit/$1', ['filter' => 'permission:consultation.view']);
    $routes->get('clinical/medicines/search', 'Clinical::medicineSearch', ['filter' => 'permission:prescription.create']);
    $routes->get('clinical/medicines/(:num)/autofill', 'Clinical::medicineAutofill/$1', ['filter' => 'permission:prescription.create']);
    $routes->get('clinical', 'Clinical::index', ['filter' => 'permission:consultation.view']);
    $routes->get('appointments', 'Records::appointments', ['filter' => 'permission:appointment.view']);
    $routes->get('availability', 'Availability::index', ['filter' => 'permission:doctor.view']);
    $routes->post('availability/schedule/save', 'Availability::saveSchedule', ['filter' => 'permission:doctor.edit']);
    $routes->post('availability/schedule/(:num)/cancel', 'Availability::cancelSchedule/$1', ['filter' => 'permission:doctor.edit']);
    $routes->post('availability/leave/save', 'Availability::saveLeave', ['filter' => 'permission:doctor.edit']);
    $routes->post('availability/leave/(:num)/cancel', 'Availability::cancelLeave/$1', ['filter' => 'permission:doctor.edit']);
    $routes->get('follow-ups', 'Records::followUps', ['filter' => 'permission:consultation.view']);
    $routes->get('follow-ups/patients/search', 'Records::followUpPatientSearch', ['filter' => 'permission:consultation.view']);
    $routes->post('follow-ups/create', 'Records::createFollowUp', ['filter' => 'permission:consultation.edit']);
    $routes->post('follow-ups/update/(:num)', 'Records::updateFollowUp/$1', ['filter' => 'permission:consultation.edit']);
    $routes->get('billing', 'Records::billing', ['filter' => 'permission:billing.view']);
    $routes->get('prescriptions', 'Records::prescriptions', ['filter' => 'permission:prescription.view']);
    $routes->get('video/(:num)', 'VideoConsultation::view/$1', ['filter' => 'permission:appointment.view']);
    $routes->post('video/(:num)/start', 'VideoConsultation::start/$1', ['filter' => 'permission:consultation.edit']);
    $routes->get('video/(:num)/status', 'VideoConsultation::status/$1', ['filter' => 'permission:consultation.edit']);
    $routes->post('video/(:num)/token', 'VideoConsultation::token/$1', ['filter' => 'permission:consultation.edit']);
    $routes->post('video/(:num)/end', 'VideoConsultation::end/$1', ['filter' => 'permission:consultation.edit']);
    $routes->post('video/(:num)/recording/start', 'VideoConsultation::startRecording/$1', ['filter' => 'permission:consultation.edit']);
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
    $routes->post('clinical/save', 'Clinical::saveWorkspace', ['filter' => 'permission:consultation.edit']);
    $routes->post('prescriptions/save', 'Clinical::savePrescription', ['filter' => 'permission:prescription.create']);
});
