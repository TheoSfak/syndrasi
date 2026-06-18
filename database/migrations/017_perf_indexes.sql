-- Performance indexes: avoid full-table scans on high-frequency queries
ALTER TABLE location_pings ADD INDEX idx_loc_event_created (event_id, created_at);
ALTER TABLE notifications  ADD INDEX idx_notif_user_read  (user_id, is_read);
