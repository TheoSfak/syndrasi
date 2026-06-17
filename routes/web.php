<?php
/**
 * SynDrasi - Route definitions.
 * @var Router $router
 */

/* Public (no auth required) */
$router->get('/public/events/{token}', 'PublicEventController@show');

/* Emergency mobilization — volunteer response (token link, no login required) */
$router->get('/m/{token}',         'MobilizationController@respondForm');
$router->post('/m/{token}/respond', 'MobilizationController@respond');

/* Mission Commander field hub (token link, no login required) */
$router->get('/f/{token}',           'FieldController@hub');
$router->get('/f/{token}/comms',     'FieldController@comms');
$router->post('/f/{token}/location', 'FieldController@location');
$router->post('/f/{token}/status',   'FieldController@status');
$router->post('/f/{token}/sos',      'FieldController@sos');
$router->post('/f/{token}/ack-order','FieldController@ackOrder');
$router->post('/f/{token}/photo',    'FieldController@photo');
$router->post('/f/{token}/room',     'FieldController@room');

/* Home: redirect by role */
$router->get('/', 'AuthController@home');

/* Auth */
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->post('/logout', 'AuthController@logout');
$router->get('/forgot-password', 'AuthController@showForgotPassword');
$router->post('/forgot-password', 'AuthController@sendResetLink');
$router->get('/reset-password', 'AuthController@showResetForm');
$router->post('/reset-password', 'AuthController@doResetPassword');
$router->get('/profile', 'AuthController@profile');
$router->post('/profile/password', 'AuthController@changePassword');

/* Notifications */
$router->get('/notifications', 'NotificationController@index');
$router->get('/notifications/poll', 'NotificationController@poll');
$router->post('/notifications/{id}/read', 'NotificationController@markRead');
$router->post('/notifications/read-all', 'NotificationController@markAllRead');

/* Municipality admin: dashboard */
$router->get('/dashboard', 'DashboardController@municipality');

/* Municipality admin: volunteer teams */
$router->get('/teams', 'TeamController@index');
$router->get('/teams/create', 'TeamController@create');
$router->post('/teams/store', 'TeamController@store');
$router->get('/teams/{id}/edit', 'TeamController@edit');
$router->post('/teams/{id}/update', 'TeamController@update');
$router->post('/teams/{id}/toggle', 'TeamController@toggleStatus');

/* Municipality admin: events */
$router->get('/events', 'EventController@index');
$router->get('/events/drafts', 'EventController@drafts');
$router->get('/events/closed', 'EventController@closed');
$router->get('/events/completed', 'EventController@completed');
$router->get('/events/calendar', 'EventController@calendar');
$router->get('/events/create', 'EventController@create');
$router->post('/events/store', 'EventController@store');
$router->get('/events/{id}', 'EventController@show');
$router->get('/events/{id}/edit', 'EventController@edit');
$router->post('/events/{id}/update', 'EventController@update');
$router->post('/events/{id}/publish', 'EventController@publish');
$router->post('/events/{id}/activate', 'EventController@activate');
$router->post('/events/{id}/close', 'EventController@close');
$router->post('/events/{id}/complete', 'EventController@complete');
$router->post('/events/{id}/archive', 'EventController@archive');
$router->get('/events/{id}/reconcile', 'EventController@reconcile');
$router->post('/events/{id}/reconcile', 'EventController@saveReconciliation');
$router->post('/events/{id}/remind', 'EventController@remind');
$router->post('/events/{id}/cancel', 'EventController@cancel');
$router->post('/events/{id}/clone',  'EventController@clone');
$router->post('/events/{id}/save-template', 'EventController@saveTemplate');
$router->post('/event-templates/{id}/delete', 'EventController@deleteTemplate');

/* Municipality admin: applications */
$router->get('/events/{id}/applications', 'ApplicationController@index');
$router->post('/applications/{id}/approve', 'ApplicationController@approve');
$router->post('/applications/{id}/reject', 'ApplicationController@reject');
$router->post('/events/{id}/applications/bulk', 'ApplicationController@bulkApprove');
$router->get('/applications', 'ApplicationController@pending');

/* Municipality admin: event shifts management */
$router->post('/events/{id}/shifts/store', 'ShiftController@store');
$router->post('/events/{id}/shifts/{sid}/update', 'ShiftController@update');
$router->post('/events/{id}/shifts/{sid}/delete', 'ShiftController@destroy');
$router->post('/shift-applications/{id}/approve', 'ShiftController@approve');
$router->post('/shift-applications/{id}/reject', 'ShiftController@reject');

/* Team admin: Mobile Action Hub */
$router->get('/team/live/{id}', 'TeamPortalController@live');
$router->get('/team/qr-checkin/{id}', 'TeamPortalController@qrCheckin');

/* Team portal: shift apply / cancel */
$router->post('/team/events/{id}/shifts/{sid}/apply', 'ShiftController@teamApply');
$router->post('/team/shift-applications/{id}/cancel', 'ShiftController@teamCancel');

/* Municipality admin & operator: emergency mobilization (command side) */
$router->get('/mobilizations',                 'MobilizationController@index');
$router->get('/mobilizations/new',             'MobilizationController@create');
$router->post('/mobilizations',                'MobilizationController@store');
$router->get('/mobilizations/{id}',            'MobilizationController@show');
$router->get('/mobilizations/{id}/stream',     'MobilizationController@stream');
$router->post('/mobilizations/{id}/stand-down', 'MobilizationController@standDown');
$router->post('/mobilizations/{id}/checkin',   'MobilizationController@checkin');

/* Municipality admin & operator: operational page */
$router->get('/operations', 'OperationController@index');
$router->get('/operations/war-room', 'OperationController@warRoom');
$router->get('/operations/war-room/stream', 'OperationController@warRoomStream');
$router->get('/operations/events/{id}', 'OperationController@show');
$router->get('/operations/events/{id}/gate-qr', 'OperationController@gateQr');
$router->get('/operations/events/{id}/status', 'OperationController@status');
$router->get('/operations/events/{id}/stream', 'OperationController@stream');
$router->get('/operations/events/{id}/locations', 'OperationController@locations');
$router->post('/operations/events/{id}/note', 'OperationController@addNote');
$router->post('/operations/events/{id}/request-photo', 'OperationController@requestPhoto');
$router->post('/operations/events/{id}/message', 'OperationController@sendMessage');
$router->post('/operations/events/{id}/room', 'OperationController@sendRoom');
$router->get('/operations/photos/{id}', 'OperationController@servePhoto');
$router->post('/shortages/{id}/acknowledge', 'OperationController@acknowledgeShortage');
$router->post('/shortages/{id}/resolve', 'OperationController@resolveShortage');
$router->post('/sos/{id}/acknowledge', 'OperationController@sosAck');
$router->post('/sos/{id}/resolve', 'OperationController@sosResolve');

/* Municipality admin: statistics, awards, reports/exports */
$router->get('/statistics', 'StatisticsController@index');
$router->get('/statistics/teams/{id}', 'StatisticsController@team');
$router->get('/analytics', 'AnalyticsController@index');
$router->get('/analytics/export', 'AnalyticsController@export');
$router->get('/awards', 'AwardController@index');
$router->get('/reports', 'ReportController@index');
$router->get('/exports/events', 'ReportController@exportEvents');
$router->get('/exports/events/{id}/applications', 'ReportController@exportEventApplications');
$router->get('/exports/events/{id}/coverage', 'ReportController@exportEventCoverage');
$router->get('/exports/team-statistics', 'ReportController@exportTeamStatistics');
$router->get('/exports/municipality-statistics', 'ReportController@exportMunicipalityStatistics');
$router->get('/exports/awards', 'ReportController@exportAwards');

/* PDF print views */
$router->get('/reports/pdf/event/{id}/coverage',    'ReportController@pdfCoverage');
$router->get('/reports/pdf/event/{id}/certificate', 'ReportController@pdfCertificate');
$router->get('/reports/pdf/awards/{id}',            'ReportController@pdfAwards');
$router->get('/reports/pdf/annual/{id}',            'ReportController@pdfAnnual');

/* Team admin */
$router->get('/team/dashboard', 'TeamPortalController@dashboard');
$router->get('/team/events', 'TeamPortalController@events');
$router->get('/team/events/{id}', 'TeamPortalController@showEvent');
$router->post('/team/events/{id}/apply', 'TeamPortalController@apply');
$router->post('/team/events/{id}/application/members', 'TeamPortalController@updateApplicationMembers');
$router->post('/team/applications/{id}/cancel', 'TeamPortalController@cancelApplication');
$router->post('/team/applications/{id}/send-field-link', 'TeamPortalController@sendFieldLink');
$router->get('/team/applications', 'TeamPortalController@applications');
$router->get('/team/operations/events/{id}', 'TeamPortalController@operations');
$router->post('/team/operations/events/{id}/checkin', 'TeamPortalController@checkin');
$router->post('/team/operations/events/{id}/send-location', 'TeamPortalController@sendLocation');
$router->post('/team/operations/events/{id}/photo', 'TeamPortalController@uploadPhoto');
$router->post('/team/operations/events/{id}/sos', 'TeamPortalController@sos');
$router->post('/team/operations/events/{id}/message', 'TeamPortalController@sendTeamMessage');
$router->post('/team/operations/events/{id}/status-ping', 'TeamPortalController@statusPing');
$router->post('/team/operations/events/{id}/ack-order', 'TeamPortalController@ackOrder');
$router->get('/team/operations/events/{id}/comms', 'TeamPortalController@commsFeed');
$router->post('/team/operations/events/{id}/room', 'TeamPortalController@sendRoomMessage');

/* Team debrief */
$router->get('/team/events/{id}/debrief',  'TeamPortalController@debrief');
$router->post('/team/events/{id}/debrief', 'TeamPortalController@saveDebrief');

/* Municipality admin: event debriefs overview */
$router->get('/events/{id}/debriefs', 'EventController@debriefs');
$router->post('/events/{id}/municipality-debrief', 'EventController@saveMunicipalityDebrief');
$router->post('/team/operations/events/{id}/shortage', 'TeamPortalController@reportShortage');
$router->post('/team/events/{id}/report', 'TeamPortalController@submitReport');
$router->get('/team/statistics', 'TeamPortalController@statistics');

/* Team admin: member roster */
$router->get('/team/members', 'TeamMemberController@index');
$router->get('/team/members/create', 'TeamMemberController@create');
$router->post('/team/members', 'TeamMemberController@store');
$router->get('/team/members/{id}/edit', 'TeamMemberController@edit');
$router->get('/team/members/{id}/stats', 'TeamMemberController@stats');
$router->get('/team/members/{id}/certificate', 'TeamMemberController@certificate');
$router->post('/team/members/{id}', 'TeamMemberController@update');
$router->post('/team/members/{id}/toggle', 'TeamMemberController@toggle');

/* Municipality admin: settings */
$router->get('/settings', 'SettingsController@index');
$router->post('/settings/mail', 'SettingsController@saveMail');
$router->post('/settings/mail/test', 'SettingsController@testMail');
$router->post('/settings/map', 'SettingsController@saveMap');
$router->post('/settings/awards', 'SettingsController@saveAwards');
$router->post('/settings/notifications', 'SettingsController@saveNotifications');
$router->post('/settings/sms', 'SettingsController@saveSms');
$router->post('/settings/sms/test', 'SettingsController@testSms');
$router->post('/settings/event-defaults', 'SettingsController@saveEventDefaults');
$router->post('/settings/branding', 'SettingsController@saveBranding');
$router->post('/settings/member-fields', 'SettingsController@saveMemberFields');
$router->post('/settings/email-templates', 'SettingsController@saveEmailTemplates');

/* Web Push subscription management */
$router->get('/push/vapid-key', 'PushController@vapidKey');
$router->post('/push/subscribe', 'PushController@subscribe');
$router->post('/push/unsubscribe', 'PushController@unsubscribe');

/* Cron endpoints (token-protected, no session required) */
$router->ge