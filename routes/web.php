<?php
/**
 * SynDrasi - Route definitions.
 * @var Router $router
 *
 * Every route below carries an explicit access option so Router::dispatch()
 * can enforce it before the controller runs (deny-by-default — see Router.php):
 *   - ['public' => true]      no session required (token/PIN/secret-gated in the controller)
 *   - ['roles' => [...]]     session required AND current_role() must be in the list
 *   - (no options)            session required, any authenticated role
 */

/* Public (no auth required) */
$router->get('/public/events/{token}', 'PublicEventController@show', ['public' => true]);
$router->get('/public/story/{token}', 'PublicEventController@story', ['public' => true]);
$router->get('/public/story/{token}/photo/{id}', 'PublicEventController@storyPhoto', ['public' => true]);
$router->get('/public/story/{token}/video/{id}', 'PublicEventController@storyVideo', ['public' => true]);
$router->get('/public/fire-risk-map/{token}', 'FireRiskMapController@show', ['public' => true]);

/* Emergency mobilization — volunteer response (token link, no login required) */
$router->get('/m/{token}',         'MobilizationController@respondForm', ['public' => true]);
$router->post('/m/{token}/respond', 'MobilizationController@respond', ['public' => true]);

/* Mission Commander field hub (token link, no login required) */
$router->get('/f/{token}',           'FieldController@hub', ['public' => true]);
$router->get('/f/{token}/comms',     'FieldController@comms', ['public' => true]);
$router->post('/f/{token}/location', 'FieldController@location', ['public' => true]);
$router->post('/f/{token}/status',   'FieldController@status', ['public' => true]);
$router->post('/f/{token}/sos',      'FieldController@sos', ['public' => true]);
$router->post('/f/{token}/ack-order','FieldController@ackOrder', ['public' => true]);
$router->post('/f/{token}/photo',    'FieldController@photo', ['public' => true]);
$router->post('/f/{token}/video',    'FieldController@video', ['public' => true]);
$router->post('/f/{token}/pin',      'FieldController@pin', ['public' => true]);
$router->post('/f/{token}/room',     'FieldController@room', ['public' => true]);
$router->post('/f/{token}/message',  'FieldController@message', ['public' => true]);
$router->post('/f/{token}/shortage', 'FieldController@shortage', ['public' => true]);

/* Home: redirect by role (handles both logged-in and anonymous visitors itself) */
$router->get('/', 'AuthController@home', ['public' => true]);

/* Auth */
$router->get('/login', 'AuthController@showLogin', ['public' => true]);
$router->post('/login', 'AuthController@login', ['public' => true]);
$router->post('/logout', 'AuthController@logout');
$router->get('/forgot-password', 'AuthController@showForgotPassword', ['public' => true]);
$router->post('/forgot-password', 'AuthController@sendResetLink', ['public' => true]);
$router->get('/reset-password', 'AuthController@showResetForm', ['public' => true]);
$router->post('/reset-password', 'AuthController@doResetPassword', ['public' => true]);
$router->get('/profile', 'AuthController@profile');
$router->post('/profile/password', 'AuthController@changePassword');

/* Notifications (any authenticated role) */
$router->get('/notifications', 'NotificationController@index');
$router->get('/notifications/poll', 'NotificationController@poll');
$router->post('/notifications/{id}/read', 'NotificationController@markRead');
$router->post('/notifications/read-all', 'NotificationController@markAllRead');
$router->get('/notification-center', 'NotificationCenterController@index', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/notification-center/mail/{id}/retry', 'NotificationCenterController@retryEmail', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/notification-center/clear', 'NotificationCenterController@clearHistory', ['roles' => [Role::MUNICIPALITY_ADMIN]]);

/* Municipality admin: dashboard */
$router->get('/dashboard', 'DashboardController@municipality', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);

/* Municipality admin: official Fire Service incidents */
$router->get('/fire-service', 'FireServiceController@index', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/fire-service/sync', 'FireServiceController@sync', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/fire-service/{id}/create-event', 'FireServiceController@createEvent', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->get('/fire-service/{id}/mobilize', 'FireServiceController@mobilizeReview', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/fire-service/{id}/mobilize', 'FireServiceController@mobilize', ['roles' => [Role::MUNICIPALITY_ADMIN]]);

/* Municipality admin: volunteer teams */
$router->get('/teams', 'TeamController@index', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->get('/teams/create', 'TeamController@create', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/teams/store', 'TeamController@store', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->get('/teams/{id}/edit', 'TeamController@edit', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->get('/teams/{id}/assistants', 'TeamController@assistants', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/teams/{id}/update', 'TeamController@update', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/teams/{id}/toggle', 'TeamController@toggleStatus', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/teams/{id}/members/{memberId}/assistant/revoke', 'TeamController@revokeAssistant', ['roles' => [Role::MUNICIPALITY_ADMIN]]);

/* Municipality admin: events */
$router->get('/events', 'EventController@index', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->get('/events/drafts', 'EventController@drafts', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->get('/events/closed', 'EventController@closed', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->get('/events/completed', 'EventController@completed', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->get('/events/calendar', 'EventController@calendar', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->get('/events/create', 'EventController@create', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/events/store', 'EventController@store', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->get('/events/{id}', 'EventController@show', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->get('/events/{id}/edit', 'EventController@edit', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/events/{id}/update', 'EventController@update', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/events/{id}/publish', 'EventController@publish', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/events/{id}/activate', 'EventController@activate', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->post('/events/{id}/close', 'EventController@close', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->post('/events/{id}/complete', 'EventController@complete', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->post('/events/{id}/archive', 'EventController@archive', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->get('/events/{id}/story', 'EventController@story', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->get('/events/{id}/story/download', 'EventController@storyDownload', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->post('/events/{id}/story/publish', 'EventController@publishStory', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->get('/events/{id}/reconcile', 'EventController@reconcile', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/events/{id}/reconcile', 'EventController@saveReconciliation', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/events/{id}/remind', 'EventController@remind', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/events/{id}/cancel', 'EventController@cancel', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/events/{id}/clone',  'EventController@clone', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/events/{id}/save-template', 'EventController@saveTemplate', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/event-templates/{id}/delete', 'EventController@deleteTemplate', ['roles' => [Role::MUNICIPALITY_ADMIN]]);

/* Municipality admin: applications */
$router->get('/events/{id}/applications', 'ApplicationController@index', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/applications/{id}/approve', 'ApplicationController@approve', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/applications/{id}/reject', 'ApplicationController@reject', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/events/{id}/applications/bulk', 'ApplicationController@bulkApprove', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->get('/applications', 'ApplicationController@pending', ['roles' => [Role::MUNICIPALITY_ADMIN]]);

/* Municipality admin: event shifts management */
$router->post('/events/{id}/shifts/store', 'ShiftController@store', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/events/{id}/shifts/{sid}/update', 'ShiftController@update', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/events/{id}/shifts/{sid}/delete', 'ShiftController@destroy', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/shift-applications/{id}/approve', 'ShiftController@approve', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/shift-applications/{id}/reject', 'ShiftController@reject', ['roles' => [Role::MUNICIPALITY_ADMIN]]);

/* Team admin: Mobile Action Hub */
$router->get('/team/live/{id}', 'TeamPortalController@live', ['roles' => [Role::TEAM_ADMIN]]);
$router->get('/team/qr-checkin/{id}', 'TeamPortalController@qrCheckin', ['roles' => [Role::TEAM_ADMIN]]);

/* Team portal: shift apply / cancel */
$router->post('/team/events/{id}/shifts/{sid}/apply', 'ShiftController@teamApply', ['roles' => [Role::TEAM_ADMIN]]);
$router->post('/team/shift-applications/{id}/cancel', 'ShiftController@teamCancel', ['roles' => [Role::TEAM_ADMIN]]);

/* Municipality admin & operator: emergency mobilization (command side) */
$router->get('/mobilizations',                 'MobilizationController@index', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->get('/mobilizations/new',             'MobilizationController@create', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->post('/mobilizations',                'MobilizationController@store', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->get('/mobilizations/{id}',            'MobilizationController@show', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->get('/mobilizations/{id}/stream',     'MobilizationController@stream', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->post('/mobilizations/{id}/stand-down', 'MobilizationController@standDown', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->post('/mobilizations/{id}/checkin',   'MobilizationController@checkin', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);

/* Municipality admin & operator: operational page */
$router->get('/operations', 'OperationController@index', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->get('/operations/war-room', 'OperationController@warRoom', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->get('/operations/war-room/status', 'OperationController@warRoomStatus', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->get('/operations/war-room/stream', 'OperationController@warRoomStream', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->get('/operations/events/{id}', 'OperationController@show', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->get('/operations/events/{id}/gate-qr', 'OperationController@gateQr', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->get('/operations/events/{id}/status', 'OperationController@status', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->get('/operations/events/{id}/stream', 'OperationController@stream', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->get('/operations/events/{id}/locations', 'OperationController@locations', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->post('/operations/events/{id}/note', 'OperationController@addNote', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->post('/operations/events/{id}/request-photo', 'OperationController@requestPhoto', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->post('/operations/events/{id}/request-gps', 'OperationController@requestGps', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->post('/operations/events/{id}/request-video', 'OperationController@requestVideo', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->post('/operations/events/{id}/message', 'OperationController@sendMessage', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->post('/operations/events/{id}/room', 'OperationController@sendRoom', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->post('/operations/events/{id}/applications/{appId}/approve', 'OperationController@approveApplication', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/operations/events/{id}/applications/{appId}/reject',  'OperationController@rejectApplication', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
/* servePhoto/serveVideo are shared with team_admin (own-team media); the controller
   narrows further by municipality/team ownership after this role gate. */
$router->get('/operations/photos/{id}', 'OperationController@servePhoto', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR, Role::TEAM_ADMIN]]);
$router->get('/operations/videos/{id}', 'OperationController@serveVideo', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR, Role::TEAM_ADMIN]]);
$router->get('/operations/videos/{id}/download', 'OperationController@downloadVideo', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->post('/operations/videos/{id}/delete', 'OperationController@deleteVideo', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->post('/shortages/{id}/acknowledge', 'OperationController@acknowledgeShortage', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->post('/shortages/{id}/resolve', 'OperationController@resolveShortage', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
/* Smart Resource Dispatch (Φάση 1): αιτήματα διάθεσης πόρων από το war-room */
$router->post('/operations/events/{id}/resource-request', 'OperationController@createResourceRequest', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->post('/operations/resource-requests/{id}/delivered', 'OperationController@resourceRequestDelivered', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->post('/operations/resource-requests/{id}/cancel', 'OperationController@resourceRequestCancel', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);

/* Smart Resource Dispatch (Φάση 2): απάντηση ομάδας από team live ή field link */
$router->post('/team/resource-requests/{id}/respond', 'TeamPortalController@respondResourceRequest', ['roles' => [Role::TEAM_ADMIN]]);
$router->post('/f/{token}/resource-requests/{id}/respond', 'FieldController@respondResourceRequest', ['public' => true]);
$router->post('/sos/{id}/acknowledge', 'OperationController@sosAck', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->post('/sos/{id}/resolve', 'OperationController@sosResolve', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);

/* Municipality admin: statistics, awards, reports/exports */
$router->get('/statistics', 'StatisticsController@index', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->get('/statistics/teams/{id}', 'StatisticsController@team', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->get('/analytics', 'AnalyticsController@index', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->get('/analytics/export', 'AnalyticsController@export', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->get('/awards', 'AwardController@index', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->get('/reports', 'ReportController@index', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->get('/exports/events', 'ReportController@exportEvents', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->get('/exports/events/{id}/applications', 'ReportController@exportEventApplications', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->get('/exports/events/{id}/coverage', 'ReportController@exportEventCoverage', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->get('/exports/team-statistics', 'ReportController@exportTeamStatistics', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->get('/exports/municipality-statistics', 'ReportController@exportMunicipalityStatistics', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->get('/exports/awards', 'ReportController@exportAwards', ['roles' => [Role::MUNICIPALITY_ADMIN]]);

/* PDF print views */
$router->get('/reports/pdf/event/{id}/coverage',    'ReportController@pdfCoverage', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->get('/reports/pdf/event/{id}/certificate', 'ReportController@pdfCertificate', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->get('/reports/pdf/awards/{id}',            'ReportController@pdfAwards', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->get('/reports/pdf/annual/{id}',            'ReportController@pdfAnnual', ['roles' => [Role::MUNICIPALITY_ADMIN]]);

/* Team admin */
$router->get('/team/dashboard', 'TeamPortalController@dashboard', ['roles' => [Role::TEAM_ADMIN]]);
$router->get('/team/readiness', 'TeamPortalController@readiness', ['roles' => [Role::TEAM_ADMIN]]);
$router->post('/team/readiness', 'TeamPortalController@saveReadiness', ['roles' => [Role::TEAM_ADMIN]]);
$router->get('/team/events', 'TeamPortalController@events', ['roles' => [Role::TEAM_ADMIN]]);
$router->get('/team/events/{id}', 'TeamPortalController@showEvent', ['roles' => [Role::TEAM_ADMIN]]);
$router->post('/team/events/{id}/apply', 'TeamPortalController@apply', ['roles' => [Role::TEAM_ADMIN]]);
$router->post('/team/events/{id}/application/members', 'TeamPortalController@updateApplicationMembers', ['roles' => [Role::TEAM_ADMIN]]);
$router->post('/team/applications/{id}/cancel', 'TeamPortalController@cancelApplication', ['roles' => [Role::TEAM_ADMIN]]);
$router->post('/team/applications/{id}/send-field-link', 'TeamPortalController@sendFieldLink', ['roles' => [Role::TEAM_ADMIN]]);
$router->post('/team/applications/{id}/regenerate-pin', 'TeamPortalController@regenerateFieldPin', ['roles' => [Role::TEAM_ADMIN]]);
$router->get('/team/applications', 'TeamPortalController@applications', ['roles' => [Role::TEAM_ADMIN]]);
$router->get('/team/operations/events/{id}', 'TeamPortalController@operations', ['roles' => [Role::TEAM_ADMIN]]);
$router->post('/team/operations/events/{id}/checkin', 'TeamPortalController@checkin', ['roles' => [Role::TEAM_ADMIN]]);
$router->post('/team/operations/events/{id}/send-location', 'TeamPortalController@sendLocation', ['roles' => [Role::TEAM_ADMIN]]);
$router->post('/team/operations/events/{id}/photo', 'TeamPortalController@uploadPhoto', ['roles' => [Role::TEAM_ADMIN]]);
$router->post('/team/operations/events/{id}/video', 'TeamPortalController@uploadVideo', ['roles' => [Role::TEAM_ADMIN]]);
$router->post('/team/operations/events/{id}/sos', 'TeamPortalController@sos', ['roles' => [Role::TEAM_ADMIN]]);
$router->post('/team/operations/events/{id}/message', 'TeamPortalController@sendTeamMessage', ['roles' => [Role::TEAM_ADMIN]]);
$router->post('/team/operations/events/{id}/status-ping', 'TeamPortalController@statusPing', ['roles' => [Role::TEAM_ADMIN]]);
$router->post('/team/operations/events/{id}/ack-order', 'TeamPortalController@ackOrder', ['roles' => [Role::TEAM_ADMIN]]);
$router->get('/team/operations/events/{id}/comms', 'TeamPortalController@commsFeed', ['roles' => [Role::TEAM_ADMIN]]);
$router->post('/team/operations/events/{id}/room', 'TeamPortalController@sendRoomMessage', ['roles' => [Role::TEAM_ADMIN]]);

/* Team debrief */
$router->get('/team/events/{id}/debrief',  'TeamPortalController@debrief', ['roles' => [Role::TEAM_ADMIN]]);
$router->post('/team/events/{id}/debrief', 'TeamPortalController@saveDebrief', ['roles' => [Role::TEAM_ADMIN]]);

/* Municipality admin: event debriefs overview */
$router->get('/events/{id}/debriefs', 'EventController@debriefs', ['roles' => [Role::MUNICIPALITY_ADMIN, Role::EVENT_OPERATOR]]);
$router->post('/events/{id}/municipality-debrief', 'EventController@saveMunicipalityDebrief', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/team/operations/events/{id}/shortage', 'TeamPortalController@reportShortage', ['roles' => [Role::TEAM_ADMIN]]);
$router->post('/team/events/{id}/report', 'TeamPortalController@submitReport', ['roles' => [Role::TEAM_ADMIN]]);
$router->get('/team/statistics', 'TeamPortalController@statistics', ['roles' => [Role::TEAM_ADMIN]]);

/* Team admin: member roster */
$router->get('/team/members', 'TeamMemberController@index', ['roles' => [Role::TEAM_ADMIN]]);
$router->get('/team/members/create', 'TeamMemberController@create', ['roles' => [Role::TEAM_ADMIN]]);
$router->post('/team/members', 'TeamMemberController@store', ['roles' => [Role::TEAM_ADMIN]]);
$router->get('/team/members/{id}/edit', 'TeamMemberController@edit', ['roles' => [Role::TEAM_ADMIN]]);
$router->get('/team/members/{id}/stats', 'TeamMemberController@stats', ['roles' => [Role::TEAM_ADMIN]]);
$router->get('/team/members/{id}/certificate', 'TeamMemberController@certificate', ['roles' => [Role::TEAM_ADMIN]]);
$router->post('/team/members/{id}', 'TeamMemberController@update', ['roles' => [Role::TEAM_ADMIN]]);
$router->post('/team/members/{id}/toggle', 'TeamMemberController@toggle', ['roles' => [Role::TEAM_ADMIN]]);
$router->post('/team/members/{id}/assistant/promote', 'TeamMemberController@promoteAssistant', ['roles' => [Role::TEAM_ADMIN]]);
$router->post('/team/members/{id}/assistant/revoke', 'TeamMemberController@revokeAssistant', ['roles' => [Role::TEAM_ADMIN]]);

/* Municipality admin: settings */
$router->get('/settings', 'SettingsController@index', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/settings/mail', 'SettingsController@saveMail', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/settings/mail/test', 'SettingsController@testMail', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/settings/mail/history/clear', 'SettingsController@clearMailHistory', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/settings/map', 'SettingsController@saveMap', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/settings/awards', 'SettingsController@saveAwards', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/settings/notifications', 'SettingsController@saveNotifications', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/settings/fire-risk-map/sync', 'SettingsController@syncFireRiskMap', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/settings/fire-risk-map/upload', 'SettingsController@uploadFireRiskMap', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/settings/sms', 'SettingsController@saveSms', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/settings/sms/test', 'SettingsController@testSms', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/settings/telegram', 'SettingsController@saveTelegram', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/settings/telegram/test', 'SettingsController@testTelegram', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/settings/event-defaults', 'SettingsController@saveEventDefaults', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/settings/branding', 'SettingsController@saveBranding', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/settings/member-fields', 'SettingsController@saveMemberFields', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/settings/email-templates', 'SettingsController@saveEmailTemplates', ['roles' => [Role::MUNICIPALITY_ADMIN]]);
$router->post('/settings/organisation',   'SettingsController@saveOrganisation', ['roles' => [Role::MUNICIPALITY_ADMIN]]);

/* Web Push subscription management (any authenticated role) */
$router->get('/push/vapid-key', 'PushController@vapidKey');
$router->post('/push/subscribe', 'PushController@subscribe');
$router->post('/push/unsubscribe', 'PushController@unsubscribe');

/* Cron endpoints (token-protected via Authorization: Bearer, no session required) */
$router->get('/cron/shift-reminders', 'CronController@shiftReminders', ['public' => true]);
$router->get('/cron/cleanup', 'CronController@cleanup', ['public' => true]);
$router->get('/cron/mail-queue', 'CronController@processMailQueue', ['public' => true]);
$router->get('/cron/fire-service', 'CronController@fireService', ['public' => true]);
$router->get('/cron/fire-risk-map', 'CronController@fireRiskMap', ['public' => true]);
$router->post('/cron/fire-risk-map/ingest', 'CronController@ingestFireRiskMap', ['public' => true]);

/* Super admin */
$router->get('/admin/dashboard', 'AdminController@dashboard', ['roles' => [Role::SUPER_ADMIN]]);
$router->get('/admin/municipalities', 'AdminController@municipalities', ['roles' => [Role::SUPER_ADMIN]]);
$router->post('/admin/municipalities/store', 'AdminController@storeMunicipality', ['roles' => [Role::SUPER_ADMIN]]);
$router->get('/admin/municipalities/{id}', 'AdminController@showMunicipality', ['roles' => [Role::SUPER_ADMIN]]);
$router->post('/admin/municipalities/{id}/update', 'AdminController@updateMunicipality', ['roles' => [Role::SUPER_ADMIN]]);
$router->post('/admin/municipalities/{id}/toggle', 'AdminController@toggleMunicipality', ['roles' => [Role::SUPER_ADMIN]]);
$router->get('/admin/teams', 'AdminController@teamsOverview', ['roles' => [Role::SUPER_ADMIN]]);
$router->get('/admin/users', 'AdminController@users', ['roles' => [Role::SUPER_ADMIN]]);
$router->post('/admin/users/store', 'AdminController@storeUser', ['roles' => [Role::SUPER_ADMIN]]);
$router->post('/admin/users/{id}/update', 'AdminController@updateUser', ['roles' => [Role::SUPER_ADMIN]]);
$router->post('/admin/users/{id}/reset-password', 'AdminController@resetUserPassword', ['roles' => [Role::SUPER_ADMIN]]);
$router->post('/admin/users/{id}/toggle', 'AdminController@toggleUser', ['roles' => [Role::SUPER_ADMIN]]);
$router->post('/admin/impersonate/{id}', 'AdminController@impersonate', ['roles' => [Role::SUPER_ADMIN]]);
$router->post('/admin/stop-impersonation', 'AdminController@stopImpersonation');
$router->get('/admin/settings', 'AdminController@settings', ['roles' => [Role::SUPER_ADMIN]]);
$router->post('/admin/settings', 'AdminController@saveSettings', ['roles' => [Role::SUPER_ADMIN]]);

/* Super admin: maintenance (cron) + self-update */
$router->post('/admin/maintenance/cleanup',    'MaintenanceController@cleanup', ['roles' => [Role::SUPER_ADMIN]]);
$router->post('/admin/maintenance/reset-data', 'MaintenanceController@resetData', ['roles' => [Role::SUPER_ADMIN]]);
$router->post('/admin/updates/backup',      'MaintenanceController@backup', ['roles' => [Role::SUPER_ADMIN]]);
$router->post('/admin/updates/check',       'MaintenanceController@checkUpdate', ['roles' => [Role::SUPER_ADMIN]]);
$router->post('/admin/updates/apply',       'MaintenanceController@applyUpdate', ['roles' => [Role::SUPER_ADMIN]]);
$router->post('/admin/migrations/run',      'MaintenanceController@runMigrations', ['roles' => [Role::SUPER_ADMIN]]);
$router->get('/admin/backups/download',      'MaintenanceController@downloadBackup', ['roles' => [Role::SUPER_ADMIN]]);
$router->post('/admin/backups/restore',      'MaintenanceController@restoreBackup', ['roles' => [Role::SUPER_ADMIN]]);
