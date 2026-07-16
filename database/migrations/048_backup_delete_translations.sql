-- 048_backup_delete_translations.sql
-- Translation keys for the new backup delete / bulk-delete feature
-- (Platform Settings -> Updates -> Αντίγραφα ασφαλείας): per-row delete
-- button + confirm, and a "select rows -> delete selected" bulk action.
-- Idempotent (ON DUPLICATE KEY UPDATE).

INSERT INTO translation_keys (str_key, str_group) VALUES ('controllers/MaintenanceController.012', 'controllers/MaintenanceController') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Το backup «%s» διαγράφηκε.' FROM translation_keys WHERE str_key = 'controllers/MaintenanceController.012' ON DUPLICATE KEY UPDATE value = 'Το backup «%s» διαγράφηκε.';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'The backup "%s" was deleted.' FROM translation_keys WHERE str_key = 'controllers/MaintenanceController.012' ON DUPLICATE KEY UPDATE value = 'The backup "%s" was deleted.';
INSERT INTO translation_keys (str_key, str_group) VALUES ('controllers/MaintenanceController.013', 'controllers/MaintenanceController') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Αποτυχία διαγραφής: %s' FROM translation_keys WHERE str_key = 'controllers/MaintenanceController.013' ON DUPLICATE KEY UPDATE value = 'Αποτυχία διαγραφής: %s';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Deletion failed: %s' FROM translation_keys WHERE str_key = 'controllers/MaintenanceController.013' ON DUPLICATE KEY UPDATE value = 'Deletion failed: %s';
INSERT INTO translation_keys (str_key, str_group) VALUES ('controllers/MaintenanceController.014', 'controllers/MaintenanceController') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Δεν επιλέξατε κανένα backup.' FROM translation_keys WHERE str_key = 'controllers/MaintenanceController.014' ON DUPLICATE KEY UPDATE value = 'Δεν επιλέξατε κανένα backup.';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'No backup was selected.' FROM translation_keys WHERE str_key = 'controllers/MaintenanceController.014' ON DUPLICATE KEY UPDATE value = 'No backup was selected.';
INSERT INTO translation_keys (str_key, str_group) VALUES ('controllers/MaintenanceController.015', 'controllers/MaintenanceController') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Διαγράφηκαν %s backups. Απέτυχαν: %s' FROM translation_keys WHERE str_key = 'controllers/MaintenanceController.015' ON DUPLICATE KEY UPDATE value = 'Διαγράφηκαν %s backups. Απέτυχαν: %s';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', '%s backups were deleted. Failed: %s' FROM translation_keys WHERE str_key = 'controllers/MaintenanceController.015' ON DUPLICATE KEY UPDATE value = '%s backups were deleted. Failed: %s';
INSERT INTO translation_keys (str_key, str_group) VALUES ('controllers/MaintenanceController.016', 'controllers/MaintenanceController') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Διαγράφηκαν %s backups.' FROM translation_keys WHERE str_key = 'controllers/MaintenanceController.016' ON DUPLICATE KEY UPDATE value = 'Διαγράφηκαν %s backups.';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', '%s backups were deleted.' FROM translation_keys WHERE str_key = 'controllers/MaintenanceController.016' ON DUPLICATE KEY UPDATE value = '%s backups were deleted.';
INSERT INTO translation_keys (str_key, str_group) VALUES ('settings/index.091', 'settings/index') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Διαγραφή επιλεγμένων' FROM translation_keys WHERE str_key = 'settings/index.091' ON DUPLICATE KEY UPDATE value = 'Διαγραφή επιλεγμένων';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Delete selected' FROM translation_keys WHERE str_key = 'settings/index.091' ON DUPLICATE KEY UPDATE value = 'Delete selected';
INSERT INTO translation_keys (str_key, str_group) VALUES ('settings/index.092', 'settings/index') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Οριστική διαγραφή αυτού του backup; Δεν μπορεί να αναιρεθεί.' FROM translation_keys WHERE str_key = 'settings/index.092' ON DUPLICATE KEY UPDATE value = 'Οριστική διαγραφή αυτού του backup; Δεν μπορεί να αναιρεθεί.';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Permanently delete this backup? This cannot be undone.' FROM translation_keys WHERE str_key = 'settings/index.092' ON DUPLICATE KEY UPDATE value = 'Permanently delete this backup? This cannot be undone.';
INSERT INTO translation_keys (str_key, str_group) VALUES ('settings/index.093', 'settings/index') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Διαγραφή' FROM translation_keys WHERE str_key = 'settings/index.093' ON DUPLICATE KEY UPDATE value = 'Διαγραφή';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Delete' FROM translation_keys WHERE str_key = 'settings/index.093' ON DUPLICATE KEY UPDATE value = 'Delete';
INSERT INTO translation_keys (str_key, str_group) VALUES ('settings/index.094', 'settings/index') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Οριστική διαγραφή %d επιλεγμένων backups; Δεν μπορεί να αναιρεθεί.' FROM translation_keys WHERE str_key = 'settings/index.094' ON DUPLICATE KEY UPDATE value = 'Οριστική διαγραφή %d επιλεγμένων backups; Δεν μπορεί να αναιρεθεί.';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Permanently delete %d selected backups? This cannot be undone.' FROM translation_keys WHERE str_key = 'settings/index.094' ON DUPLICATE KEY UPDATE value = 'Permanently delete %d selected backups? This cannot be undone.';
