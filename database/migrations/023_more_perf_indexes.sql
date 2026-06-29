-- Additional composite indexes for event lists, statistics, live polling, and notifications.
ALTER TABLE events ADD INDEX IF NOT EXISTS idx_events_muni_status_start (municipality_id, status, start_datetime);
ALTER TABLE event_applications ADD INDEX IF NOT EXISTS idx_apps_event_status (event_id, status);
ALTER TABLE event_applications ADD INDEX IF NOT EXISTS idx_apps_team_status (team_id, status);
ALTER TABLE operational_checkins ADD INDEX IF NOT EXISTS idx_checkins_event_team_id (event_id, team_id, id);
ALTER TABLE shortage_reports ADD INDEX IF NOT EXISTS idx_shortages_event_status_created (event_id, status, created_at);
ALTER TABLE notifications ADD INDEX IF NOT EXISTS idx_notif_user_read_created (user_id, is_read, created_at);
ALTER TABLE location_pings ADD INDEX IF NOT EXISTS idx_loc_event_created_id (event_id, created_at, id);
