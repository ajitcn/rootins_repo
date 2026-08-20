<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->group('admin', ['namespace' => 'Admin\Controllers'], function ($routes) {
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
    $routes->get('delete/(:num)', 'Clinic::delete/$1');
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
    $routes->get('delete/(:num)', 'Doctor::delete/$1');
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
    $routes->get(
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
    $routes->get(
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
    $routes->get(
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
    $routes->get(
        'delete/(:num)', 
        'DoctorCommission::delete/$1',
        ['filter' => 'permission:doctor_commission.delete']
    );

});


/*=====  End of Doctors Commission Routes  ======*/
