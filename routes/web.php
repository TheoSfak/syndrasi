<?php
/**
 * SynDrasi - Route definitions.
 * @var Router $router
 *
 * Every route below carries an explicit access option so Router::dispatch()
 * can enforce it before the controller runs (deny-by-default — see Router.php):
 *   - ['public' => true]      no session required (token/PIN/secret-gated in the controller)
 *   - ['roles'  => [...]]     session required AND current_role() must be in the list
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
$router->get('/notification-center', 'NotificationCenterController@index', ['roles' => ['municipality_admin']]);
$router->post('/notification-center/mail/{id}/retry', 'NotificationCenterController@retryEmail', ['roles' => ['municipality_admin']]);
$router->post('/notification-center/clear', 'NotificationCenterController@clearHistory', ['roles' => ['municipality_admin']]);

/* Municipality admin: dashboard */
$router->get('/dashboard', 'DashboardController@municipality', ['roles' => ['municipality_admin', 'event_operator']]);

/* Municipality admin: official Fire Service incidents */
$router->get('/fire-service', 'FireServiceController@index', ['roles' => ['municipality_admin']]);
$router->post('/fire-service/sync', 'FireServiceController@sync', ['roles' => ['municipality_admin']]);
$router->post('/fire-service/{id}/create-event', 'FireServiceController@createEvent', ['roles' => ['municipality_admin']]);
$router->get('/fire-service/{id}/mobilize', 'FireServiceController@mobilizeReview', ['roles' => ['municipality_admin']]);
$router->post('/fire-service/{id}/mobilize', 'FireServiceController@mobilize', ['roles' => ['municipality_admin']]);

/* Municipality admin: volunteer teams */
$router->get('/teams', 'TeamController@index', ['roles' => ['municipality_admin']]);
$router->get('/teams/create', 'TeamController@create', ['roles' => ['municipality_admin']]);
$router->post('/teams/store', 'TeamController@store', ['roles' => ['municipality_admin']]);
$router->get('/teams/{id}/edit', 'TeamController@edit', ['roles' => ['municipality_admin']]);
$router->get('/teams/{id}/assistants', 'TeamController@assistants', ['roles' => ['municipality_admin']]);
$router->post('/teams/{id}/update', 'TeamController@update', ['roles' => ['municipality_admin']]);
$router->post('/teams/{id}/toggle', 'TeamController@toggleStatus', ['roles' => ['municipality_admin']]);
$router->post('/teams/{id}/members/{memberId}/assistant/revoke', 'TeamController@revokeAssistant', ['roles' => ['municipality_admin']]);

/* Municipality admin: events */
$router->get('/events', 'EventController@index', ['roles' => ['municipality_admin', 'event_operator']]);
$router->get('/events/drafts', 'EventController@drafts', ['roles' => ['municipality_admin']]);
$router->get('/events/closed', 'EventController@closed', ['roles' => ['municipality_admin', 'event_operator']]);
$router->get('/events/completed', 'EventController@completed', ['roles' => ['municipality_admin', 'event_operator']]);
$router->get('/events/calendar', 'EventController@calendar', ['roles' => ['municipality_admin', 'event_operator']]);
$router->get('/events/create', 'EventController@create', ['roles' => ['municipality_admin']]);
$router->post('/events/store', 'EventController@store', ['roles' => ['municipality_admin']]);
$router->get('/events/{id}', 'EventController@show', ['roles' => ['municipality_admin', 'event_operator']]);
$router->get('/events/{id}/edit', 'EventController@edit', ['roles' => ['municipality_admin']]);
$router->post('/events/{id}/update', 'EventController@update', ['roles' => ['municipality_admin']]);
$router->post('/events/{id}/publish', 'EventController@publish', ['roles' => ['municipality_admin']]);
$router->post('/events/{id}/activate', 'EventController@activate', ['roles' => ['municipality_admin', 'event_operator']]);
$router->post('/events/{id}/close', 'EventController@close', ['roles' => ['municipality_admin', 'event_operator']]);
$router->post('/events/{id}/complete', 'EventController@complete', ['roles' => ['municipality_admin', 'event_operator']]);
$router->post('/events/{id}/archive', 'EventController@archive', ['roles' => ['municipality_admin']]);
$router->get('/events/{id}/story', 'EventController@story', ['roles' => ['municipality_admin', 'event_operator']]);
$router->get('/events/{id}/story/download', 'EventController@storyDownload', ['roles' => ['municipality_admin', 'event_operator']]);
$router->post('/events/{id}/story/publish', 'EventController@publishStory', ['roles' => ['municipality_admin', 'event_operator']]);
$router->get('/events/{id}/reconcile', 'EventController@reconcile', ['roles' => ['municipality_admin']]);
$router->post('/events/{id}/reconcile', 'EventController@saveReconciliation', ['roles' => ['municipality_admin']]);
$router->post('/events/{id}/remind', 'EventController@remind', ['roles' => ['municipality_admin']]);
$router->post('/events/{id}/cancel', 'EventController@cancel', ['roles' => ['municipality_admin']]);
$router->post('/events/{id}/clone',  'EventController@clone', ['roles' => ['municipality_admin']]);
$router->post('/events/{id}/save-template', 'EventController@saveTemplate', ['roles' => ['municipality_admin']]);
$router->post('/event-templates/{id}/delete', 'EventController@deleteTemplate', ['roles' => ['municipality_admin']]);

/* Municipality admin: applications */
$router->get('/events/{id}/applications', 'ApplicationController@index', ['roles' => ['municipality_admin']]);
$router->post('/applications/{id}/approve', 'ApplicationController@approve', ['roles' => ['municipality_admin']]);
$router->post('/applications/{id}/reject', 'ApplicationController@reject', ['roles' => ['municipality_admin']]);
$router->post('/events/{id}/applications/bulk', 'ApplicationController@bulkApprove', ['roles' => ['municipality_admin']]);
$router->get('/applications', 'ApplicationController@pending', ['roles' => ['municipality_admin']]);

/* Municipality admin: event shifts management */
$router->post('/events/{id}/shifts/store', 'ShiftController@store', ['roles' => ['municipality_admin']]);
$router->post('/events/{id}/shifts/{sid}/update', 'ShiftController@update', ['roles' => ['municipality_admin']]);
$router->post('/events/{id}/shifts/{sid}/delete', 'ShiftController@destroy', ['roles' => ['municipality_admin']]);
$router->post('/shift-applications/{id}/approve', 'ShiftController@approve', ['roles' => ['municipality_admin']]);
$router->post('/shift-applications/{id}/reject', 'ShiftController@reject', ['roles' => ['municipality_admin']]);

/* Team admin: Mobile Action Hub */
$router->get('/team/live/{id}', 'TeamPortalController@live', ['roles' => ['team_admin']]);
$router->get('/team/qr-checkin/{id}', 'TeamPortalController@qrCheckin', ['roles' => ['team_admin']]);

/* Team portal: shift apply / cancel */
$router->post('/team/events/{id}/shifts/{sid}/apply', 'ShiftController@teamApply', ['roles' => ['team_admin']]);
$router->post('/team/shift-applications/{id}/cancel', 'ShiftController@teamCancel', ['roles' => ['team_admin']]);

/* Municipality admin & operator: emergency mobilization (command side) */
$router->get('/mobilizations',                 'MobilizationController@index', ['roles' => ['municipality_admin', 'event_operator']]);
$router->get('/mobilizations/new',             'MobilizationController@create', ['roles' => ['municipality_admin', 'event_operator']]);
$router->post('/mobilizations',                'MobilizationController@store', ['roles' => ['municipality_admin', 'event_operator']]);
$router->get('/mobilizations/{id}',            'MobilizationController@show', ['roles' => ['municipality_admin', 'event_operator']]);
$router->get('/mobilizations/{id}/stream',     'MobilizationController@stream', ['roles' => ['municipality_admin', 'event_operator']]);
$router->post('/mobilizations/{id}/stand-down', 'MobilizationController@standDown', ['roles' => ['municipality_admin', 'event_operator']]);
$router->post('/mobilizations/{id}/checkin',   'MobilizationController@checkin', ['roles' => ['municipality_admin', 'event_operator']]);

/* Municipality admin & operator: operational page */
$router->get('/operations', 'OperationController@index', ['roles' => ['municipality_admin', 'event_operator']]);
$router->get('/operations/war-room', 'OperationController@warRoom', ['roles' => ['municipality_admin', 'event_operator']]);
$router->get('/operations/war-room/status', 'OperationController@warRoomStatus', ['roles' => ['municipality_admin', 'event_operator']]);
$router->get('/operations/war-room/stream', 'OperationController@warRoomStream', ['roles' => ['municipality_admin', 'event_operator']]);
$router->get('/operations/events/{id}', 'OperationController@show', ['roles' => ['municipality_admin', 'event_operator']]);
$router->get('/operations/events/{id}/gate-qr', 'OperationController@gateQr', ['roles' => ['municipality_admin', 'event_operator']]);
$router->get('/operations/events/{id}/status', 'OperationController@status', ['roles' => ['municipality_admin', 'event_operator']]);
$router->get('/operations/events/{id}/stream', 'OperationController@stream', ['roles' => ['municipality_admin', 'event_operator']]);
$router->get('/operations/events/{id}/locations', 'OperationController@locations', ['roles' => ['municipality_admin', 'event_operator']]);
$router->post('/operations/events/{id}/note', 'OperationController@addNote', ['roles' => ['municipality_admin', 'event_operator']]);
$router->post('/operations/events/{id}/request-photo', 'OperationController@requestPhoto', ['roles' => ['municipality_admin', 'event_operator']]);
$router->post('/operations/events/{id}/request-gps', 'OperationController@requestGps', ['roles' => ['municipality_admin', 'event_operator']]);
$router->post('/operations/events/{id}/request-video', 'OperationController@requestVideo', ['roles' => ['municipality_admin', 'event_operator']]);
$router->post('/operations/events/{id}/message', 'OperationController@sendMessage', ['roles' => ['municipality_admin', 'event_operator']]);
$router->post('/operations/events/{id}/room', 'OperationController@sendRoom', ['roles' => ['municipality_admin', 'event_operator']]);
$router->post('/operations/events/{id}/applications/{appId}/approve', 'OperationController@approveApplication', ['roles' => ['municipality_admin']]);
$router->post('/operations/events/{id}/applications/{appId}/reject',  'OperationController@rejectApplication', ['roles' => ['municipality_admin']]);
/* servePhoto/serveVideo are shared with team_admin (own-team media); the controller
   narrows further by municipality/team ownership after this role gate. */
$router->get('/operations/photos/{id}', 'OperationController@servePhoto', ['roles' => ['municipality_admin', 'event_operator', 'team_admin']]);
$router->get('/operations/videos/{id}', 'OperationController@serveVideo', ['roles' => ['municipality_admin', 'event_operator', 'team_admin']]);
$router->get('/operations/videos/{id}/download', 'OperationController@downloadVideo', ['roles' => ['municipality_admin', 'event_operator']]);
$router->post('/operations/videos/{id}/delete', 'OperationController@deleteVideo', ['roles' => ['municipality_admin', 'event_operator']]);
$router->post('/shortages/{id}/acknowledge', 'OperationController@acknowledgeShortage', ['roles' => ['municipality_admin', 'event_operator']]);
$router->post('/shortages/{id}/resolve', 'OperationController@resolveShortage', ['roles' => ['municipality_admin', 'event_operator']]);
/* Smart Resource Dispatch (Φάση 1): αιτήματα διάθεσης πόρων από το war-room */
$router->post('/operations/events/{id}/resource-request', 'OperationController@createResourceRequest', ['roles' => ['municipality_admin', 'event_operator']]);
$router->post('/operations/resource-requests/{id}/delivered', 'OperationController@resourceRequestDelivered', ['roles' => ['municipality_admin', 'event_operator']]);
$router->post('/operations/resource-requests/{id}/cancel', 'OperationController@resourceRequestCancel', ['roles' => ['municipality_admin', 'event_operator']]);
$router->post('/sos/{id}/acknowledge', 'OperationController@sosAck', ['roles' => ['municipality_admin', 'event_operator']]);
$router->post('/sos/{id}/resolve', 'OperationController@sosResolve', ['roles' => ['municipality_admin', 'event_operator']]);

/* Municipality admin: statistics, awards, reports/exports */
$router->get('/statistics', 'StatisticsController@index', ['roles' => ['municipality_admin']]);
$router->get('/statistics/teams/{id}', 'StatisticsController@team', ['roles' => ['municipality_admin']]);
$router->get('/analytics', 'AnalyticsController@index', ['roles' => ['municipality_admin']]);
$router->get('/analytics/export', 'AnalyticsController@export', ['roles' => ['municipality_admin']]);
$router->get('/awards', 'AwardController@index', ['roles' => ['municipality_admin']]);
$router->get('/reports', 'ReportController@index', ['roles' => ['municipality_admin']]);
$router->get('/exports/events', 'ReportController@exportEvents', ['roles' => ['municipality_admin']]);
$router->get('/exports/events/{id}/applications', 'ReportController@exportEventApplications', ['roles' => ['municipality_admin']]);
$router->get('/exports/events/{id}/coverage', 'ReportController@exportEventCoverage', ['roles' => ['municipality_admin']]);
$router->get('/exports/team-statistics', 'ReportController@exportTeamStatistics', ['roles' => ['municipality_admin']]);
$router->get('/exports/municipality-statistics', 'ReportController@exportMunicipalityStatistics', ['roles' => ['municipality_admin']]);
$router->get('/exports/awards', 'ReportController@exportAwards', ['roles' => ['municipality_admin']]);

/* PDF print views */
$router->get('/reports/pdf/event/{id}/coverage',    'ReportController@pdfCoverage', ['roles' => ['municipality_admin', 'event_operator']]);
$router->get('/reports/pdf/event/{id}/certificate', 'ReportController@pdfCertificate', ['roles' => ['municipality_admin', 'event_operator']]);
$router->get('/reports/pdf/awards/{id}',            'ReportController@pdfAwards', ['roles' => ['municipality_admin']]);
$router->get('/reports/pdf/annual/{id}',            'ReportController@pdfAnnual', ['roles' => ['municipality_admin']]);

/* Team admin */
$router->get('/team/dashboard', 'TeamPortalController@dashboard', ['roles' => ['team_admin']]);
$router->get('/team/readiness', 'TeamPortalController@readiness', ['roles' => ['team_admin']]);
$router->post('/team/readiness', 'TeamPortalController@saveReadiness', ['roles' => ['team_admin']]);
$router->get('/team/events', 'TeamPortalController@events', ['roles' => ['team_admin']]);
$router->get('/team/events/{id}', 'TeamPortalController@showEvent', ['roles' => ['team_admin']]);
$router->post('/team/events/{id}/apply', 'TeamPortalController@apply', ['roles' => ['team_admin']]);
$router->post('/team/events/{id}/application/members', 'TeamPortalController@updateApplicationMembers', ['roles' => ['team_admin']]);
$router->post('/team/applications/{id}/cancel', 'TeamPortalController@cancelApplication', ['roles' => ['team_admin']]);
$router->post('/team/applications/{id}/send-field-link', 'TeamPortalController@sendFieldLink', ['roles' => ['team_admin']]);
$router->post('/team/applications/{id}/regenerate-pin', 'TeamPortalController@regenerateFieldPin', ['roles' => ['team_admin']]);
$router->get('/team/applications', 'TeamPortalController@applications', ['roles' => ['team_admin']]);
$router->get('/team/operations/events/{id}', 'TeamPortalController@operations', ['roles' => ['team_admin']]);
$router->post('/team/operations/events/{id}/checkin', 'TeamPortalController@checkin', ['roles' => ['team_admin']]);
$router->post('/team/operations/events/{id}/send-location', 'TeamPortalController@sendLocation', ['roles' => ['team_admin']]);
$router->post('/team/operations/events/{id}/photo', 'TeamPortalController@uploadPhoto', ['roles' => ['team_admin']]);
$router->post('/team/operations/events/{id}/video', 'TeamPortalController@uploadVideo', ['roles' => ['team_admin']]);
$router->post('/team/operations/events/{id}/sos', 'TeamPortalController@sos', ['roles' => ['team_admin']]);
$router->post('/team/operations/events/{id}/message', 'TeamPortalController@sendTeamMessage', ['roles' => ['team_admin']]);
$router->post('/team/operations/events/{id}/status-ping', 'TeamPortalController@statusPing', ['roles' => ['team_admin']]);
$router->post('/team/operations/events/{id}/ack-order', 'TeamPortalController@ackOrder', ['roles' => ['team_admin']]);
$router->get('/team/operations/events/{id}/comms', 'TeamPortalController@commsFeed', ['roles' => ['team_admin']]);
$router->post('/team/operations/events/{id}/room', 'TeamPortalController@sendRoomMessage', ['roles' => ['team_admin']]);

/* Team debrief */
$router->get('/team/events/{id}/debrief',  'TeamPortalController@debrief', ['roles' => ['team_admin']]);
$router->post('/team/events/{id}/debrief', 'TeamPortalController@saveDebrief', ['roles' => ['team_admin']]);

/* Municipality admin: event debriefs overview */
$router->get('/events/{id}/debriefs', 'EventController@debriefs', ['roles' => ['municipality_admin', 'event_operator']]);
$router->post('/events/{id}/municipality-debrief', 'EventController@saveMunicipalityDebrief', ['roles' => ['municipality_admin']]);
$router->post('/team/operations/events/{id}/shortage', 'TeamPortalController@reportShortage', ['roles' => ['team_admin']]);
$router->post('/team/events/{id}/report', 'TeamPortalController@submitReport', ['roles' => ['team_admin']]);
$router->get('/team/statistics', 'TeamPortalController@statistics', ['roles' => ['team_admin']]);

/* Team admin: member roster */
$router->get('/team/members', 'TeamMemberController@index', ['roles' => ['team_admin']]);
$router->get('/team/members/create', 'TeamMemberController@create', ['roles' => ['team_admin']]);
$router->post('/team/members', 'TeamMemberController@store', ['roles' => ['team_admin']]);
$router->get('/team/members/{id}/edit', 'TeamMemberController@edit', ['roles' => ['team_admin']]);
$router->get('/team/members/{id}/stats', 'TeamMemberController@stats', ['roles' => ['team_admin']]);
$router->get('/team/members/{id}/certificate', 'TeamMemberController@certificate', ['roles' => ['team_admin']]);
$router->post('/team/members/{id}', 'TeamMemberController@update', ['roles' => ['team_admin']]);
$router->post('/team/members/{id}/toggle', 'TeamMemberController@toggle', ['roles' => ['team_admin']]);
$router->post('/team/members/{id}/assistant/promote', 'TeamMemberController@promoteAssistant', ['roles' => ['team_admin']]);
$router->post('/team/members/{id}/assistant/revoke', 'TeamMemberController@revokeAssistant', ['roles' => ['team_admin']]);

/* Municipality admin: settings */
$router->get('/settings', 'SettingsController@index', ['roles' => ['municipality_admin']]);
$router->post('/settings/mail', 'SettingsController@saveMail', ['roles' => ['municipality_admin']]);
$router->post('/settings/mail/test', 'SettingsController@testMail', ['roles' => ['municipality_admin']]);
$router->post('/settings/mail/history/clear', 'SettingsController@clearMailHistory', ['roles' => ['municipality_admin']]);
$router->post('/settings/map', 'SettingsController@saveMap', ['roles' => ['municipality_admin']]);
$router->post('/settings/awards', 'SettingsController@saveAwards', ['roles' => ['municipality_admin']]);
$router->post('/settings/notifications', 'SettingsController@saveNotifications', ['roles' => ['municipality_admin']]);
$router->post('/settings/fire-risk-map/sync', 'SettingsController@syncFireRiskMap', ['roles' => ['municipality_admin']]);
$router->post('/settings/fire-risk-map/upload', 'SettingsController@uploadFireRiskMap', ['roles' => ['municipality_admin']]);
$router->post('/settings/sms', 'SettingsController@saveSms', ['roles' => ['municipality_admin']]);
$router->post('/settings/sms/test', 'SettingsController@testSms', ['roles' => ['municipality_admin']]);
$router->post('/settings/telegram', 'SettingsController@saveTelegram', ['roles' => ['municipality_admin']]);
$router->post('/settings/telegram/test', 'SettingsController@testTelegram', ['roles' => ['municipality_admin']]);
$router->post('/settings/event-defaults', 'SettingsController@saveEventDefaults', ['roles' => ['municipality_admin']]);
$router->post('/settings/branding', 'SettingsController@saveBranding', ['roles' => ['municipality_admin']]);
$router->post('/settings/member-fields', 'SettingsController@saveMemberFields', ['roles' => ['municipality_admin']]);
$router->post('/settings/email-templates', 'SettingsController@saveEmailTemplates', ['roles' => ['municipality_admin']]);
$router->post('/settings/organisation',   'SettingsController@saveOrganisation', ['roles' => ['municipality_admin']]);

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
$router->get('/admin/dashboard', 'AdminController@dashboard', ['roles' => ['super_admin']]);
$router->get('/admin/municipalities', 'AdminController@municipalities', ['roles' => ['super_admin']]);
$router->post('/admin/municipalities/store', 'AdminController@storeMunicipality', ['roles' => ['super_admin']]);
$router->get('/admin/municipalities/{id}', 'AdminController@showMunicipality', ['roles' => ['super_admin']]);
$router->post('/admin/municipalities/{id}/update', 'AdminController@updateMunicipality', ['roles' => ['super_admin']]);
$router->post('/admin/municipalities/{id}/toggle', 'AdminController@toggleMunicipality', ['roles' => ['super_admin']]);
$router->get('/admin/teams', 'AdminController@teamsOverview', ['roles' => ['super_admin']]);
$router->get('/admin/users', 'AdminController@users', ['roles' => ['super_admin']]);
$router->post('/admin/users/store', 'AdminController@storeUser', ['roles' => ['super_admin']]);
$router->post('/admin/users/{id}/update', 'AdminController@updateUser', ['roles' => ['super_admin']]);
$router->post('/admin/users/{id}/reset-password', 'AdminController@resetUserPassword', ['roles' => ['super_admin']]);
$router->post('/admin/users/{id}/toggle', 'AdminController@toggleUser', ['roles' => ['super_admin']]);
$router->post('/admin/impersonate/{id}', 'AdminController@impersonate', ['roles' => ['super_admin']]);
$router->post('/admin/stop-impersonation', 'AdminController@stopImpersonation');
$router->get('/admin/settings', 'AdminController@settings', ['roles' => ['super_admin']]);
$router->post('/admin/settings', 'AdminController@saveSettings', ['roles' => ['super_admin']]);

/* Super admin: maintenance (cron) + self-update */
$router->post('/admin/maintenance/cleanup',    'MaintenanceController@cleanup', ['roles' => ['super_admin']]);
$router->post('/admin/maintenance/reset-data', 'MaintenanceController@resetData', ['roles' => ['super_admin']]);
$router->post('/admin/updates/backup',      'MaintenanceController@backup', ['roles' => ['super_admin']]);
$router->post('/admin/updates/check',       'MaintenanceController@checkUpdate', ['roles' => ['super_admin']]);
$router->post('/admin/updates/apply',       'MaintenanceController@applyUpdate', ['roles' => ['super_admin']]);
$router->post('/admin/migrations/run',      'MaintenanceController@runMigrations', ['roles' => ['super_admin']]);
$router->get('/admin/backups/download',      'MaintenanceController@downloadBackup', ['roles' => ['super_admin']]);
$router->post('/admin/backups/restore',      'MaintenanceController@restoreBackup', ['roles' => ['super_admin']]);
