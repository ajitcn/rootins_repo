<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->group('admin', [
    'namespace' => 'Admin\Controllers',
    'filter'    => 'auth'
], function ($routes) {
    $routes->get('/', 'Dashboard::index');
});


/*=====================================
=            Clinic Routes            =
=====================================*/
$routes->group('admin/clinics', ['namespace' => 'Admin\Controllers\Clinic'], function ($routes) {
    $routes->get('/', 'Clinic::index');
    $routes->post('create', 'Clinic::create');
    $routes->get('clinic-by-id/(:num)', 'Clinic::clinicById/$1');
    $routes->post('update', 'Clinic::update');
    $routes->post('delete/(:num)', 'Clinic::delete/$1');
});


/*=====  End of Clinic Routes  ======*/





/*=====================================
=            Doctors Routes            =
=====================================*/
$routes->group('admin/doctors', ['namespace' => 'Admin\Controllers\Doctor'], function ($routes) {
    $routes->get('/', 'Doctor::index');
    $routes->post('create', 'Doctor::create');
    $routes->get('doctor-by-id/(:num)', 'Doctor::doctorById/$1');
    $routes->post('update', 'Doctor::update');
    $routes->post('delete/(:num)', 'Doctor::delete/$1');
});


/*=====  End of Doctors Routes  ======*/


/*================================================
=            Doctors Schedule Routes            ==
=================================================*/
$routes->group('admin/doctor-schedules', [
    'namespace' => 'Admin\Controllers\Doctor',
    'filter'    => 'auth'
], function ($routes) {

    $routes->get(
        '/',
        'DoctorSchedule::index',
        ['filter' => 'permission:doctor_schedule.view']
    );
    $routes->post(
        'create',
        'DoctorSchedule::create',
        ['filter' => 'permission:doctor_schedule.create']
    );
    $routes->get(
        'schedule-by-id/(:num)', 
        'DoctorSchedule::scheduleById/$1',
        ['filter' => 'permission:doctor_schedule.edit']
    );
    $routes->post(
        'update', 
        'DoctorSchedule::update',
        ['filter' => 'permission:doctor_schedule.update']
    );
    $routes->post(
        'delete/(:num)', 
        'DoctorSchedule::delete/$1',
        ['filter' => 'permission:doctor_schedule.delete']
    );

});


/*=====  End of Doctors Schedule Routes  ======*/


/*================================================
=            Doctors Leave Routes            ==
=================================================*/
$routes->group('admin/doctor-leaves', [
    'namespace' => 'Admin\Controllers\Doctor',
    'filter'    => 'auth'
], function ($routes) {

    $routes->get(
        '/', 
        'DoctorLeave::index',
        ['filter' => 'permission:doctor_leave.view']
    );
    $routes->post(
        'create', 
        'DoctorLeave::create',
        ['filter' => 'permission:doctor_leave.create']
    );
    
    $routes->post(
        'update-status', 
        'DoctorLeave::updateStatus',
        ['filter' => 'permission:doctor_leave.update']
    );
    $routes->post(
        'delete/(:num)', 
        'DoctorLeave::delete/$1',
        ['filter' => 'permission:doctor_leave.delete']
    );

});


/*=====  End of Doctors Leave Routes  ======*/



/*================================================
=            Doctors Fee Routes            ==
=================================================*/
$routes->group('admin/doctor-fees', [
    'namespace' => 'Admin\Controllers\Doctor',
    'filter'    => 'auth'
], function ($routes) {

    $routes->get(
        '/', 
        'DoctorConsultationFee::index',
        ['filter' => 'permission:doctor_fee.view']
    );
    $routes->post(
        'create', 
        'DoctorConsultationFee::create',
        ['filter' => 'permission:doctor_fee.create']
    );
    $routes->get(
        'fee-by-id/(:num)', 
        'DoctorConsultationFee::feeById/$1',
        ['filter' => 'permission:doctor_fee.edit']
    );
    $routes->post(
        'update', 
        'DoctorConsultationFee::update',
        ['filter' => 'permission:doctor_fee.update']
    );
    $routes->post(
        'delete/(:num)', 
        'DoctorConsultationFee::delete/$1',
        ['filter' => 'permission:doctor_fee.delete']
    );

});


/*=====  End of Doctors Fee Routes  ======*/


/*==================================================
=            Doctors Commission Routes            ==
===================================================*/

$routes->group('admin/doctor-commissions', [
    'namespace' => 'Admin\Controllers\Doctor',
    'filter'    => 'auth'
], function ($routes) {

    $routes->get(
        '/', 
        'DoctorCommission::index',
        ['filter' => 'permission:doctor_commission.view']
    );
    $routes->post(
        'create', 
        'DoctorCommission::create',
        ['filter' => 'permission:doctor_commission.create']
    );
    $routes->get(
        'commission-by-id/(:num)', 
        'DoctorCommission::commissionById/$1',
        ['filter' => 'permission:doctor_commission.edit']
    );
    $routes->post(
        'update', 
        'DoctorCommission::update',
        ['filter' => 'permission:doctor_commission.update']
    );
    $routes->post(
        'delete/(:num)', 
        'DoctorCommission::delete/$1',
        ['filter' => 'permission:doctor_commission.delete']
    );

});


/*=====  End of Doctors Commission Routes  ======*/


/*==================================================
=            Doctors Document Routes            ==
===================================================*/

$routes->group('admin/doctor-documents', [
    'namespace' => 'Admin\Controllers\Doctor',
    'filter'    => 'auth'
], function ($routes) {

    $routes->get(
        '/',
        'DoctorDocument::index',
        ['filter' => 'permission:doctor_document.view']
    );
    $routes->post(
        'create',
        'DoctorDocument::create',
        ['filter' => 'permission:doctor_document.create']
    );
    $routes->get(
        'document-by-id/(:num)',
        'DoctorDocument::documentById/$1',
        ['filter' => 'permission:doctor_document.edit']
    );
    $routes->post(
        'update',
        'DoctorDocument::update',
        ['filter' => 'permission:doctor_document.update']
    );
    $routes->post(
        'delete/(:num)',
        'DoctorDocument::delete/$1',
        ['filter' => 'permission:doctor_document.delete']
    );

});


/*=====  End of Doctors Document Routes  ======*/


/*=====================================
=            Patient Routes           =
=====================================*/
$routes->group('admin/patients', [
    'namespace' => 'Admin\Controllers\Patient',
    'filter'    => 'auth'
], function ($routes) {

    $routes->get(
        '/',
        'Patient::index',
        ['filter' => 'permission:patient.view']
    );
    $routes->get(
        'edit/(:num)',
        'Patient::edit/$1',
        ['filter' => 'permission:patient.view']
    );
    $routes->get(
        'view/(:num)',
        'Patient::view/$1',
        ['filter' => 'permission:patient.view']
    );
    $routes->post(
        'save',
        'Patient::save',
        ['filter' => 'permission:patient.create']
    );
    $routes->post(
        'update/(:num)',
        'Patient::update/$1',
        ['filter' => 'permission:patient.edit']
    );

});


/*=====  End of Patient Routes  ======*/


/*=====================================
=       Patient Registration Routes   =
=====================================*/
$routes->group('admin/patient-registration', [
    'namespace' => 'Admin\Controllers\Patient',
    'filter'    => 'auth'
], function ($routes) {

    $routes->get(
        '/',
        'Patient::registration',
        ['filter' => 'permission:patient.create']
    );
    $routes->post(
        'register',
        'Patient::register',
        ['filter' => 'permission:patient.create']
    );

});


/*=====  End of Patient Registration Routes  ======*/


/*=====================================
=       Patient Medical History      =
=====================================*/
$routes->group('admin/patients/(:num)/medical-history', [
    'namespace' => 'Admin\Controllers\Patient',
    'filter'    => 'auth'
], function ($routes) {

    $routes->get(
        '/',
        'PatientMedicalHistory::index/$1',
        ['filter' => 'permission:patient.view']
    );
    $routes->post(
        'save',
        'PatientMedicalHistory::save/$1',
        ['filter' => 'permission:patient.create']
    );
    $routes->get(
        'edit/(:num)',
        'PatientMedicalHistory::edit/$1/$2',
        ['filter' => 'permission:patient.edit']
    );
    $routes->post(
        'update/(:num)',
        'PatientMedicalHistory::update/$1/$2',
        ['filter' => 'permission:patient.edit']
    );

});


/*=====  End of Patient Medical History  ======*/


/*=====================================
=          Patient Documents         =
=====================================*/
$routes->group('admin/patients/(:num)/documents', [
    'namespace' => 'Admin\Controllers\Patient',
    'filter'    => 'auth'
], function ($routes) {

    $routes->get(
        '/',
        'PatientDocument::index/$1',
        ['filter' => 'permission:patient.view']
    );
    $routes->post(
        'save',
        'PatientDocument::save/$1',
        ['filter' => 'permission:patient.create']
    );
    $routes->get(
        'edit/(:num)',
        'PatientDocument::edit/$1/$2',
        ['filter' => 'permission:patient.edit']
    );
    $routes->post(
        'update/(:num)',
        'PatientDocument::update/$1/$2',
        ['filter' => 'permission:patient.edit']
    );
    $routes->get(
        'download/(:num)',
        'PatientDocument::download/$1/$2',
        ['filter' => 'permission:patient.view']
    );
    $routes->post(
        'delete/(:num)',
        'PatientDocument::delete/$1/$2',
        ['filter' => 'permission:patient.delete']
    );

});


/*=====  End of Patient Documents  ======*/


/*=====================================
=          Appointment Routes        =
=====================================*/
$routes->group('admin/appointments', [
    'namespace' => 'Admin\Controllers\Appointment',
    'filter'    => 'auth'
], function ($routes) {

    $routes->get(
        '/',
        'Appointment::index',
        ['filter' => 'permission:appointment.view']
    );
    $routes->get(
        'view/(:num)',
        'Appointment::view/$1',
        ['filter' => 'permission:appointment.view']
    );
    $routes->get(
        'calendar',
        'Appointment::calendar',
        ['filter' => 'permission:appointment.view']
    );
    $routes->get(
        'calendar-data',
        'Appointment::calendarData',
        ['filter' => 'permission:appointment.view']
    );
    $routes->get(
        'queue',
        'Appointment::queue',
        ['filter' => 'permission:appointment.view']
    );
    $routes->get(
        'queue-data',
        'Appointment::queueData',
        ['filter' => 'permission:appointment.view']
    );
    $routes->post(
        'check-in/(:num)',
        'Appointment::checkIn/$1',
        ['filter' => 'permission:appointment.edit']
    );
    $routes->post(
        'queue-move/(:num)/(:alpha)',
        'Appointment::moveQueue/$1/$2',
        ['filter' => 'permission:appointment.edit']
    );
    $routes->post(
        'queue-status/(:num)',
        'Appointment::queueStatus/$1',
        ['filter' => 'permission:appointment.edit']
    );
    $routes->get(
        'reports',
        'Appointment::reports',
        ['filter' => 'permission:appointment.view']
    );
    $routes->get(
        'reports-data',
        'Appointment::reportsData',
        ['filter' => 'permission:appointment.view']
    );
    $routes->get(
        'edit/(:num)',
        'Appointment::edit/$1',
        ['filter' => 'permission:appointment.edit']
    );
    $routes->post(
        'save',
        'Appointment::save',
        ['filter' => 'permission:appointment.create']
    );
    $routes->post(
        'update/(:num)',
        'Appointment::update/$1',
        ['filter' => 'permission:appointment.edit']
    );
    $routes->post(
        'delete/(:num)',
        'Appointment::delete/$1',
        ['filter' => 'permission:appointment.delete']
    );

});


/*=====  End of Appointment Routes  ======*/


/*=====================================
=          Consultation Routes       =
=====================================*/
$routes->group('admin/consultations', [
    'namespace' => 'Admin\Controllers\Consultation',
    'filter'    => 'auth'
], function ($routes) {

    $routes->get(
        '/',
        'Consultation::index',
        ['filter' => 'permission:consultation.view']
    );
    $routes->get(
        'view/(:num)',
        'Consultation::view/$1',
        ['filter' => 'permission:consultation.view']
    );
    $routes->get(
        'edit/(:num)',
        'Consultation::edit/$1',
        ['filter' => 'permission:consultation.edit']
    );
    $routes->post(
        'save',
        'Consultation::save',
        ['filter' => 'permission:consultation.create']
    );
    $routes->post(
        'update/(:num)',
        'Consultation::update/$1',
        ['filter' => 'permission:consultation.edit']
    );
    $routes->post(
        'delete/(:num)',
        'Consultation::delete/$1',
        ['filter' => 'permission:consultation.delete']
    );

});


/*=====  End of Consultation Routes  ======*/


/*=====================================
=          Prescription Routes       =
=====================================*/
$routes->group('admin/prescriptions', [
    'namespace' => 'Admin\Controllers\Prescription',
    'filter'    => 'auth'
], function ($routes) {

    $routes->get(
        '/',
        'Prescription::index',
        ['filter' => 'permission:prescription.view']
    );
    $routes->get(
        'view/(:num)',
        'Prescription::view/$1',
        ['filter' => 'permission:prescription.view']
    );
    $routes->get(
        'pdf/(:num)',
        'Prescription::pdf/$1',
        ['filter' => 'permission:prescription.view']
    );
    $routes->get(
        'edit/(:num)',
        'Prescription::edit/$1',
        ['filter' => 'permission:prescription.edit']
    );
    $routes->post(
        'save',
        'Prescription::save',
        ['filter' => 'permission:prescription.create']
    );
    $routes->post(
        'update/(:num)',
        'Prescription::update/$1',
        ['filter' => 'permission:prescription.edit']
    );
    $routes->post(
        'delete/(:num)',
        'Prescription::delete/$1',
        ['filter' => 'permission:prescription.delete']
    );

});


/*=====  End of Prescription Routes  ======*/


/*=====================================
=            Medicine Routes          =
=====================================*/
$routes->group('admin/medicines', [
    'namespace' => 'Admin\Controllers\Medicine',
    'filter'    => 'auth'
], function ($routes) {

    $routes->get('/', 'Medicine::index', ['filter' => 'permission:medicine.view']);
    $routes->get('search', 'Medicine::search', ['filter' => 'permission:medicine.view']);
    $routes->get('edit/(:num)', 'Medicine::edit/$1', ['filter' => 'permission:medicine.edit']);
    $routes->post('save', 'Medicine::save', ['filter' => 'permission:medicine.create']);
    $routes->post('update/(:num)', 'Medicine::update/$1', ['filter' => 'permission:medicine.edit']);
    $routes->post('delete/(:num)', 'Medicine::delete/$1', ['filter' => 'permission:medicine.delete']);

});


/*=====  End of Medicine Routes  ======*/
