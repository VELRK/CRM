<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'AuthController::home');
$routes->get('login', 'AuthController::loginForm');
$routes->post('login', 'AuthController::login');
$routes->get('register', 'AuthController::registerForm');
$routes->post('register', 'AuthController::register');
$routes->post('logout', 'AuthController::logout');
$routes->get('cron/reminders', 'CronController::reminders');
$routes->post('cron/reminders', 'CronController::reminders');
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
    $routes->post('projects/(:num)/image/delete', 'Api\DeskApi::deleteProjectImage/$1');
    $routes->get('projects/(:num)/image', 'Api\DeskApi::projectImage/$1');
    $routes->post('projects/(:num)/image', 'Api\DeskApi::saveProjectImage/$1');
    $routes->get('projects/(:num)/dashboard', 'Api\DeskApi::projectDashboard/$1');
    $routes->post('projects/(:num)/delete', 'Api\DeskApi::deleteProject/$1');
    $routes->post('projects/(:num)', 'Api\DeskApi::saveProject/$1');

    $routes->get('plots/export', 'Api\DeskApi::exportPlots');
    $routes->get('plots', 'Api\DeskApi::plots');
    $routes->post('plots', 'Api\DeskApi::savePlot');
    $routes->post('plots/(:num)/booking', 'Api\DeskApi::plotBooking/$1');
    $routes->post('plots/(:num)/images/(:num)/delete', 'Api\DeskApi::deletePlotImage/$1/$2');
    $routes->get('plots/(:num)/images/(:segment)', 'Api\DeskApi::plotImage/$1/$2');
    $routes->post('plots/(:num)/images', 'Api\DeskApi::savePlotImage/$1');
    $routes->get('plots/(:num)/history', 'Api\DeskApi::plotHistory/$1');
    $routes->post('plots/(:num)/delete', 'Api\DeskApi::deletePlot/$1');
    $routes->post('plots/(:num)', 'Api\DeskApi::savePlot/$1');

    $routes->get('mobile/dashboard', 'Api\DeskApi::mobileDashboard');

    $routes->get('lead-stages', 'Api\DeskApi::catalogList/lead_stage');
    $routes->post('lead-stages/(:num)/delete', 'Api\DeskApi::catalogDelete/lead_stage/$1');
    $routes->post('lead-stages/(:num)', 'Api\DeskApi::catalogSave/lead_stage/$1');
    $routes->post('lead-stages', 'Api\DeskApi::catalogSave/lead_stage');

    $routes->get('lead-sources', 'Api\DeskApi::catalogList/lead_source');
    $routes->post('lead-sources/(:num)/delete', 'Api\DeskApi::catalogDelete/lead_source/$1');
    $routes->post('lead-sources/(:num)', 'Api\DeskApi::catalogSave/lead_source/$1');
    $routes->post('lead-sources', 'Api\DeskApi::catalogSave/lead_source');

    $routes->get('campaign-sources', 'Api\DeskApi::catalogList/campaign_source');
    $routes->post('campaign-sources/(:num)/delete', 'Api\DeskApi::catalogDelete/campaign_source/$1');
    $routes->post('campaign-sources/(:num)', 'Api\DeskApi::catalogSave/campaign_source/$1');
    $routes->post('campaign-sources', 'Api\DeskApi::catalogSave/campaign_source');

    $routes->get('followup-purposes', 'Api\DeskApi::catalogList/followup_purpose');
    $routes->post('followup-purposes/(:num)/delete', 'Api\DeskApi::catalogDelete/followup_purpose/$1');
    $routes->post('followup-purposes/(:num)', 'Api\DeskApi::catalogSave/followup_purpose/$1');
    $routes->post('followup-purposes', 'Api\DeskApi::catalogSave/followup_purpose');

    $routes->get('call-feedback', 'Api\DeskApi::catalogList/call_feedback');
    $routes->post('call-feedback/(:num)/delete', 'Api\DeskApi::catalogDelete/call_feedback/$1');
    $routes->post('call-feedback/(:num)', 'Api\DeskApi::catalogSave/call_feedback/$1');
    $routes->post('call-feedback', 'Api\DeskApi::catalogSave/call_feedback');

    $routes->get('visit-feedback', 'Api\DeskApi::catalogList/visit_feedback');
    $routes->post('visit-feedback/(:num)/delete', 'Api\DeskApi::catalogDelete/visit_feedback/$1');
    $routes->post('visit-feedback/(:num)', 'Api\DeskApi::catalogSave/visit_feedback/$1');
    $routes->post('visit-feedback', 'Api\DeskApi::catalogSave/visit_feedback');

    $routes->get('deals', 'Api\DeskApi::deals');
    $routes->post('deals/(:num)/approve', 'Api\DeskApi::approveDeal/$1');

    $routes->get('leads/export', 'Api\DeskApi::exportLeads');
    $routes->get('leads/filter', 'Api\DeskApi::filterLeads');
    $routes->post('leads/filter', 'Api\DeskApi::filterLeads');
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
    $routes->post('leads/(:num)/approve', 'Api\DeskApi::approveLead/$1');
    $routes->post('leads/(:num)/whatsapp', 'Api\DeskApi::sendTemplate/$1');
    $routes->post('leads/(:num)/delete', 'Api\DeskApi::deleteLead/$1');
    $routes->get('leads/(:num)', 'Api\DeskApi::lead/$1');
    $routes->post('leads/(:num)', 'Api\DeskApi::saveLead/$1');

    $routes->get('followups/filter', 'Api\DeskApi::filterFollowups');
    $routes->post('followups/filter', 'Api\DeskApi::filterFollowups');
    $routes->get('followups', 'Api\DeskApi::followups');
    $routes->post('followups', 'Api\DeskApi::saveFollowup');
    $routes->post('followups/(:num)/done', 'Api\DeskApi::doneFollowup/$1');
    $routes->post('followups/(:num)/reschedule', 'Api\DeskApi::rescheduleFollowup/$1');

    $routes->get('visits/filter', 'Api\DeskApi::filterVisits');
    $routes->post('visits/filter', 'Api\DeskApi::filterVisits');
    $routes->get('visits', 'Api\DeskApi::visits');
    $routes->post('visits', 'Api\DeskApi::saveVisit');
    $routes->post('visits/(:num)/done', 'Api\DeskApi::doneVisit/$1');
    $routes->post('visits/(:num)/reschedule', 'Api\DeskApi::rescheduleVisit/$1');
    $routes->post('visits/(:num)', 'Api\DeskApi::updateVisit/$1');

    $routes->get('tasks/filter', 'Api\DeskApi::filterTasks');
    $routes->post('tasks/filter', 'Api\DeskApi::filterTasks');
    $routes->get('tasks', 'Api\DeskApi::tasks');
    $routes->post('tasks', 'Api\DeskApi::saveTask');
    $routes->post('tasks/(:num)/done', 'Api\DeskApi::doneTask/$1');
    $routes->post('tasks/(:num)/reschedule', 'Api\DeskApi::rescheduleTask/$1');

    $routes->get('attendance/locations', 'Api\DeskApi::attendanceLocations');
    $routes->post('attendance/locations/(:num)', 'Api\DeskApi::saveAttendanceLocation/$1');
    $routes->post('attendance/locations', 'Api\DeskApi::saveAttendanceLocation');
    $routes->get('attendance/places', 'Api\DeskApi::attendancePlaces');
    $routes->get('attendance/sessions', 'Api\DeskApi::attendanceSessions');
    $routes->get('attendance', 'Api\DeskApi::attendance');
    $routes->post('attendance', 'Api\DeskApi::markAttendance');

    $routes->get('incentives', 'Api\DeskApi::incentives');
    $routes->post('incentives/rule', 'Api\DeskApi::incentiveRule');
    $routes->post('incentives/(:num)', 'Api\DeskApi::incentiveStatus/$1');

    $routes->get('reports/attendance', 'Api\DeskApi::reportAttendance');
    $routes->get('reports/excel', 'Api\DeskApi::exportExcel');
    $routes->get('reports/pdf', 'Api\DeskApi::exportPdf');
    $routes->get('reports', 'Api\DeskApi::reports');
    $routes->get('activity', 'Api\DeskApi::activity');
    $routes->post('devices/deactivate', 'Api\DeskApi::deactivateDevice');
    $routes->post('devices', 'Api\DeskApi::registerDevice');
    $routes->get('notifications/summary', 'Api\DeskApi::notificationSummary');
    $routes->get('notifications/options', 'Api\DeskApi::notificationOptions');
    $routes->get('notifications/rules', 'Api\DeskApi::notificationRules');
    $routes->post('notifications/rules/(:num)/delete', 'Api\DeskApi::deleteNotificationRule/$1');
    $routes->post('notifications/rules/(:num)', 'Api\DeskApi::saveNotificationRule/$1');
    $routes->post('notifications/rules', 'Api\DeskApi::saveNotificationRule');
    $routes->get('notifications/history/(:num)', 'Api\DeskApi::notificationRecipients/$1');
    $routes->get('notifications/history', 'Api\DeskApi::notificationHistory');
    $routes->post('notifications/media', 'Api\DeskApi::uploadNotificationMedia');
    $routes->get('notifications/media/(:segment)', 'Api\DeskApi::notificationMedia/$1');
    $routes->post('notifications/send', 'Api\DeskApi::sendNotification');
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
