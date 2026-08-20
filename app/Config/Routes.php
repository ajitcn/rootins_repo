<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');


/**
 *-------------------------------------------------------------------
 * Common Routes
 *-------------------------------------------------------------------
 */
$routes->get('system/login', 'UserAuth::index');
$routes->post('system/user-auth-authenticate', 'UserAuth::authenticate');


//Location routes
$routes->get('location/states/(:num)', 'Location::getStates/$1');
$routes->get('location/cities/(:num)', 'Location::getCities/$1');




// Import module routes
foreach (glob(APPPATH . 'Modules/*/Config/Routes.php') as $moduleRoute) {
    require $moduleRoute;
}
