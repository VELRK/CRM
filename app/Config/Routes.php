<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'AuthController::home');
$routes->get('login', 'AuthController::loginForm');
$routes->post('login', 'AuthController::login');
$routes->get('register', 'AuthController::registerForm');
$routes->post('register', 'AuthController::register');
$routes->post('logout', 'AuthController::logout');
$routes->get('join/(:segment)', 'AuthController::joinForm/$1');
$routes->post('join/(:segment)', 'AuthController::join/$1');

$routes->post('api/v1/auth/login', 'Api\DeskApi::login');
$routes->post('api/v1/auth/password', 'Api\DeskApi::setPassword');
$routes->options('api/v1', 'Api\DeskApi::options');
$routes->options('api/v1/(:any)', 'Api\DeskApi::options');
$routes->group('api/v1', ['filter' => 'apiauth'], static function ($routes) {
    $routes->get('catalog', 'Api\DeskApi::catalog');
    $routes->get('catalog/postman', 'Api\DeskApi::postman');
    $routes->post('auth/logout', 'Api\DeskApi::logout');
    $routes->get('me', 'Api\DeskApi::me');
    $routes->post('me/password', 'Api\DeskApi::changePassword');
    $routes->get('company', 'Api\DeskApi::company');
    $routes->post('company', 'Api\DeskApi::saveCompany');
    $routes->get('company/logo', 'Api\DeskApi::companyLogo');
    $routes->post('company/logo', 'Api\DeskApi::saveLogo');

    $routes->get('staff', 'Api\DeskApi::staff');
    $routes->get('staff/(:num)/open-work', 'Api\DeskApi::openWork/$1');
    $routes->post('staff/(:num)', 'Api\DeskApi::saveStaff/$1');

    $routes->get('teams', 'Api\DeskApi::teams');
    $routes->post('teams', 'Api\DeskApi::saveTeam');
    $routes->get('teams/(:num)/history', 'Api\DeskApi::teamHistory/$1');
    $routes->post('teams/(:num)/delete', 'Api\DeskApi::deleteTeam/$1');
    $routes->post('teams/(:num)', 'Api\DeskApi::saveTeam/$1');

    $routes->get('projects', 'Api\DeskApi::projects');
    $routes->post('projects', 'Api\DeskApi::saveProject');
    $routes->get('projects/(:num)/dashboard', 'Api\DeskApi::projectDashboard/$1');
    $routes->post('projects/(:num)/delete', 'Api\DeskApi::deleteProject/$1');
    $routes->post('projects/(:num)', 'Api\DeskApi::saveProject/$1');

    $routes->get('plots/export', 'Api\DeskApi::exportPlots');
    $routes->get('plots', 'Api\DeskApi::plots');
    $routes->post('plots', 'Api\DeskApi::savePlot');
    $routes->post('plots/(:num)/booking', 'Api\DeskApi::plotBooking/$1');
    $routes->get('plots/(:num)/history', 'Api\DeskApi::plotHistory/$1');
    $routes->post('plots/(:num)/delete', 'Api\DeskApi::deletePlot/$1');
    $routes->post('plots/(:num)', 'Api\DeskApi::savePlot/$1');

    $routes->get('leads/export', 'Api\DeskApi::exportLeads');
    $routes->post('leads/check', 'Api\DeskApi::checkLead');
    $routes->post('leads/transfer', 'Api\DeskApi::bulkTransfer');
    $routes->get('leads', 'Api\DeskApi::leads');
    $routes->post('leads', 'Api\DeskApi::saveLead');
    $routes->get('leads/(:num)/timeline', 'Api\DeskApi::timeline/$1');
    $routes->post('leads/(:num)/stage', 'Api\DeskApi::leadStage/$1');
    $routes->post('leads/(:num)/projects', 'Api\DeskApi::leadProjects/$1');
    $routes->get('leads/(:num)/notes', 'Api\DeskApi::leadNotes/$1');
    $routes->post('leads/(:num)/notes', 'Api\DeskApi::saveNote/$1');
    $routes->post('leads/(:num)/notes/(:num)/delete', 'Api\DeskApi::deleteNote/$1/$2');
    $routes->get('leads/(:num)/deals', 'Api\DeskApi::leadDeals/$1');
    $routes->post('leads/(:num)/deals', 'Api\DeskApi::saveDeal/$1');
    $routes->post('leads/(:num)/transfer', 'Api\DeskApi::transfer/$1');
    $routes->post('leads/(:num)/whatsapp', 'Api\DeskApi::sendTemplate/$1');
    $routes->post('leads/(:num)/delete', 'Api\DeskApi::deleteLead/$1');
    $routes->get('leads/(:num)', 'Api\DeskApi::lead/$1');
    $routes->post('leads/(:num)', 'Api\DeskApi::saveLead/$1');

    $routes->get('followups', 'Api\DeskApi::followups');
    $routes->post('followups', 'Api\DeskApi::saveFollowup');
    $routes->post('followups/(:num)/done', 'Api\DeskApi::doneFollowup/$1');
    $routes->post('followups/(:num)/reschedule', 'Api\DeskApi::rescheduleFollowup/$1');

    $routes->get('visits', 'Api\DeskApi::visits');
    $routes->post('visits', 'Api\DeskApi::saveVisit');
    $routes->post('visits/(:num)/done', 'Api\DeskApi::doneVisit/$1');
    $routes->post('visits/(:num)/reschedule', 'Api\DeskApi::rescheduleVisit/$1');
    $routes->post('visits/(:num)', 'Api\DeskApi::updateVisit/$1');

    $routes->get('tasks', 'Api\DeskApi::tasks');
    $routes->post('tasks', 'Api\DeskApi::saveTask');
    $routes->post('tasks/(:num)/done', 'Api\DeskApi::doneTask/$1');
    $routes->post('tasks/(:num)/reschedule', 'Api\DeskApi::rescheduleTask/$1');

    $routes->get('attendance', 'Api\DeskApi::attendance');
    $routes->post('attendance', 'Api\DeskApi::markAttendance');

    $routes->get('incentives', 'Api\DeskApi::incentives');
    $routes->post('incentives/rule', 'Api\DeskApi::incentiveRule');
    $routes->post('incentives/(:num)', 'Api\DeskApi::incentiveStatus/$1');

    $routes->get('reports/excel', 'Api\DeskApi::exportExcel');
    $routes->get('reports/pdf', 'Api\DeskApi::exportPdf');
    $routes->get('reports', 'Api\DeskApi::reports');
    $routes->get('activity', 'Api\DeskApi::activity');
    $routes->get('notifications', 'Api\DeskApi::notifications');
    $routes->post('notifications/read', 'Api\DeskApi::readNotifications');
    $routes->post('notifications/(:num)/read', 'Api\DeskApi::readNotification/$1');

    $routes->get('whatsapp/messages', 'Api\DeskApi::messages');
    $routes->get('whatsapp/templates', 'Api\DeskApi::templates');
    $routes->post('whatsapp/templates', 'Api\DeskApi::saveTemplate');

    $routes->get('access', 'Api\DeskApi::accessCatalog');
    $routes->get('roles', 'Api\DeskApi::roles');
    $routes->post('roles', 'Api\DeskApi::createRole');
    $routes->get('roles/(:num)', 'Api\DeskApi::role/$1');
    $routes->post('roles/(:num)/access', 'Api\DeskApi::saveAccess/$1');
    $routes->post('roles/(:num)/delete', 'Api\DeskApi::deleteRole/$1');
    $routes->post('users', 'Api\DeskApi::createUser');
    $routes->post('invites', 'Api\DeskApi::invite');
    $routes->get('lookups', 'Api\DeskApi::lookups');
    $routes->get('files', 'Api\DeskApi::files');
    $routes->post('files', 'Api\DeskApi::upload');
});

$routes->group('app', ['filter' => 'sessionauth'], static function ($routes) {
    $routes->get('/', 'HomeController::index');
    $routes->get('reports', 'ReportsController::index');
    $routes->get('reports/excel', 'ReportsController::excel');
    $routes->get('reports/pdf', 'ReportsController::pdf');
    $routes->get('search', 'SearchController::index');
    $routes->get('notices', 'NoticesController::index');
    $routes->post('notices/read', 'NoticesController::readAll');
    $routes->post('notices/(:num)/read', 'NoticesController::read/$1');
    $routes->post('files', 'FilesController::create');
    $routes->get('files/(:num)', 'FilesController::download/$1');
    $routes->post('files/(:num)/delete', 'FilesController::delete/$1');
    $routes->get('billing', 'BillingController::index');
    $routes->post('billing', 'BillingController::save');
    $routes->get('audit', 'AuditController::index');

    $routes->get('designations', 'EstateController::designations');
    $routes->post('designations', 'EstateController::saveDesignation');
    $routes->post('designations/(:num)/delete', 'EstateController::deleteDesignation/$1');
    $routes->post('designations/(:num)', 'EstateController::saveDesignation/$1');

    $routes->get('staff', 'StaffController::index');
    $routes->post('staff/(:num)', 'StaffController::save/$1');

    $routes->get('projects', 'ProjectsController::index');
    $routes->post('projects', 'ProjectsController::save');
    $routes->post('projects/(:num)/delete', 'ProjectsController::delete/$1');
    $routes->post('projects/(:num)', 'ProjectsController::save/$1');

    $routes->get('plots', 'PlotsController::index');
    $routes->get('plots/export', 'EstateController::exportPlots');
    $routes->post('plots', 'PlotsController::save');
    $routes->post('plots/(:num)/delete', 'PlotsController::delete/$1');
    $routes->post('plots/(:num)', 'PlotsController::save/$1');

    $routes->get('leads', 'LeadsController::index');
    $routes->get('leads/export', 'EstateController::exportLeads');
    $routes->post('leads', 'LeadsController::save');
    $routes->post('leads/(:num)/delete', 'LeadsController::delete/$1');
    $routes->post('leads/(:num)/transfer', 'LeadsController::transfer/$1');
    $routes->post('leads/(:num)/followups', 'LeadsController::followup/$1');
    $routes->post('leads/(:num)/visits', 'LeadsController::visit/$1');
    $routes->post('leads/(:num)', 'LeadsController::save/$1');

    $routes->get('followups', 'FollowupsController::index');
    $routes->post('followups', 'FollowupsController::create');
    $routes->post('followups/(:num)/done', 'FollowupsController::done/$1');

    $routes->get('visits', 'VisitsController::index');
    $routes->post('visits', 'VisitsController::create');
    $routes->post('visits/(:num)/attendance', 'VisitsController::attendance/$1');

    $routes->get('teams', 'EstateController::teams');
    $routes->post('teams', 'EstateController::saveTeam');
    $routes->post('teams/(:num)', 'EstateController::saveTeam/$1');

    $routes->get('attendance', 'EstateController::attendance');
    $routes->post('attendance', 'EstateController::markAttendance');

    $routes->get('incentives', 'EstateController::incentives');
    $routes->post('incentives/rule', 'EstateController::saveIncentiveRule');
    $routes->post('incentives/(:num)', 'EstateController::incentiveStatus/$1');

    $routes->get('lists', 'EstateController::lists');
    $routes->post('lists', 'EstateController::saveList');
    $routes->post('lists/(:num)', 'EstateController::saveList/$1');

    $routes->get('whatsapp', 'EstateController::whatsapp');
    $routes->post('whatsapp/settings', 'EstateController::saveWhatsapp');
    $routes->post('whatsapp/templates', 'EstateController::saveTemplate');
    $routes->post('leads/(:num)/whatsapp', 'EstateController::sendTemplate/$1');

    $routes->get('roles', 'RolesController::index');
    $routes->get('roles/new', 'RolesController::new');
    $routes->post('roles', 'RolesController::create');
    $routes->get('roles/(:num)', 'RolesController::access/$1');
    $routes->post('roles/(:num)/access', 'RolesController::saveAccess/$1');
    $routes->post('roles/(:num)/delete', 'RolesController::delete/$1');

    $routes->get('team', 'TeamController::index');
    $routes->post('team/(:num)/role', 'TeamController::assign/$1');
    $routes->post('team/invites', 'TeamController::invite');

    $routes->get('settings', 'SettingsController::index');
    $routes->post('settings', 'SettingsController::save');
});
