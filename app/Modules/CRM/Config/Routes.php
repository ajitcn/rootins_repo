<?php
/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes->group('admin/crm', ['namespace' => 'CRM\Controllers', 'filter' => 'auth'], static function ($routes) {
    $routes->get('leads', 'Crm::leads', ['filter' => 'permission:crm.lead.view']);
    $routes->get('leads/(:num)', 'Crm::viewLead/$1', ['filter' => 'permission:crm.lead.view']);
    $routes->get('leads/(:num)/history', 'Crm::history/$1', ['filter' => 'permission:crm.history.view']);
    $routes->post('leads/(:num)/convert/patient', 'Crm::convertPatient/$1', ['filter' => 'permission:crm.lead.convert']);
    $routes->post('leads/(:num)/convert/appointment', 'Crm::convertAppointment/$1', ['filter' => 'permission:crm.lead.convert']);
    $routes->post('leads/create', 'Crm::saveLead', ['filter' => 'permission:crm.lead.create']);
    $routes->post('leads/update/(:num)', 'Crm::saveLead/$1', ['filter' => 'permission:crm.lead.edit']);
    $routes->post('leads/delete/(:num)', 'Crm::deleteLead/$1', ['filter' => 'permission:crm.lead.delete']);
    $routes->get('follow-ups', 'Crm::followUps', ['filter' => 'permission:crm.followup.view']);
    $routes->get('follow-ups/(:num)/history', 'Crm::followUpHistory/$1', ['filter' => 'permission:crm.history.view']);
    $routes->get('follow-ups/(:num)', 'Crm::followUpDetails/$1', ['filter' => 'permission:crm.followup.view']);
    $routes->post('follow-ups/create', 'Crm::saveFollowUp', ['filter' => 'permission:crm.followup.create']);
    $routes->post('follow-ups/update/(:num)', 'Crm::saveFollowUp/$1', ['filter' => 'permission:crm.followup.edit']);
    $routes->post('follow-ups/(:num)/comment', 'Crm::addFollowUpComment/$1', ['filter' => 'permission:crm.followup.edit']);
    $routes->post('follow-ups/(:num)/reschedule', 'Crm::rescheduleFollowUp/$1', ['filter' => 'permission:crm.followup.edit']);
    $routes->post('follow-ups/(:num)/complete', 'Crm::followUpAction/$1/complete', ['filter' => 'permission:crm.followup.complete']);
    $routes->post('follow-ups/(:num)/cancel', 'Crm::followUpAction/$1/cancel', ['filter' => 'permission:crm.followup.cancel']);
    $routes->post('follow-ups/(:num)/missed', 'Crm::followUpAction/$1/missed', ['filter' => 'permission:crm.followup.cancel']);
    $crmMasters = ['lead-statuses' => 'lead_status', 'lead-sources' => 'lead_source', 'lead-priorities' => 'lead_priority', 'follow-up-types' => 'followup_type', 'follow-up-statuses' => 'followup_status'];
    foreach ($crmMasters as $slug => $permission) {
        $routes->get('masters/' . $slug, 'Masters::index/' . $slug, ['filter' => 'permission:crm.' . $permission . '.view']);
        $routes->post('masters/' . $slug . '/create', 'Masters::save/' . $slug, ['filter' => 'permission:crm.' . $permission . '.create']);
        $routes->post('masters/' . $slug . '/update/(:num)', 'Masters::save/' . $slug . '/$1', ['filter' => 'permission:crm.' . $permission . '.edit']);
        $routes->post('masters/' . $slug . '/toggle/(:num)', 'Masters::toggle/' . $slug . '/$1', ['filter' => 'permission:crm.' . $permission . '.edit']);
        $routes->post('masters/' . $slug . '/archive/(:num)', 'Masters::archive/' . $slug . '/$1', ['filter' => 'permission:crm.' . $permission . '.archive']);
    }
});
$routes->group('crm', ['namespace' => 'CRM\Controllers', 'filter' => 'auth'], static function ($routes) {
    $routes->get('leads', 'Crm::leads', ['filter' => 'permission:crm.lead.view']);
    $routes->get('follow-ups', 'Crm::followUps', ['filter' => 'permission:crm.followup.view']);
});
