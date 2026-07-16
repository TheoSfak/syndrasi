-- 045_sidebar_menu_translations.sql
-- The left sidebar menu labels live inside a PHP array in
-- views/layouts/sidebar.php (code, not markup), so the automated view-wiring
-- pass of v0.20.0-beta never saw them and the sidebar stayed Greek for
-- English users. Adds catalog keys for every static menu label
-- (layouts/sidebar.003–.021) plus the authority-terminology menu labels
-- (authority/<type>.event_plural / .team_plural) that feed the two dynamic
-- entries. Also corrects the stale auth/profile.017 hint which claimed the
-- language choice only applies "on the next update of the app" — it applies
-- immediately since v0.20.0-beta. Idempotent (ON DUPLICATE KEY UPDATE).

INSERT INTO translation_keys (str_key, str_group) VALUES ('layouts/sidebar.003', 'layouts/sidebar') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Πίνακας Ελέγχου' FROM translation_keys WHERE str_key = 'layouts/sidebar.003' ON DUPLICATE KEY UPDATE value = 'Πίνακας Ελέγχου';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Dashboard' FROM translation_keys WHERE str_key = 'layouts/sidebar.003' ON DUPLICATE KEY UPDATE value = 'Dashboard';
INSERT INTO translation_keys (str_key, str_group) VALUES ('layouts/sidebar.004', 'layouts/sidebar') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Κάλεσμα Έκτακτης Ανάγκης' FROM translation_keys WHERE str_key = 'layouts/sidebar.004' ON DUPLICATE KEY UPDATE value = 'Κάλεσμα Έκτακτης Ανάγκης';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Emergency Callout' FROM translation_keys WHERE str_key = 'layouts/sidebar.004' ON DUPLICATE KEY UPDATE value = 'Emergency Callout';
INSERT INTO translation_keys (str_key, str_group) VALUES ('layouts/sidebar.005', 'layouts/sidebar') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Κέντρο Επιχειρήσεων' FROM translation_keys WHERE str_key = 'layouts/sidebar.005' ON DUPLICATE KEY UPDATE value = 'Κέντρο Επιχειρήσεων';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Operations Center' FROM translation_keys WHERE str_key = 'layouts/sidebar.005' ON DUPLICATE KEY UPDATE value = 'Operations Center';
INSERT INTO translation_keys (str_key, str_group) VALUES ('layouts/sidebar.006', 'layouts/sidebar') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Κέντρο Συντονισμού' FROM translation_keys WHERE str_key = 'layouts/sidebar.006' ON DUPLICATE KEY UPDATE value = 'Κέντρο Συντονισμού';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Coordination Center' FROM translation_keys WHERE str_key = 'layouts/sidebar.006' ON DUPLICATE KEY UPDATE value = 'Coordination Center';
INSERT INTO translation_keys (str_key, str_group) VALUES ('layouts/sidebar.007', 'layouts/sidebar') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Συμβάντα Πυροσβεστικής' FROM translation_keys WHERE str_key = 'layouts/sidebar.007' ON DUPLICATE KEY UPDATE value = 'Συμβάντα Πυροσβεστικής';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Fire Service Incidents' FROM translation_keys WHERE str_key = 'layouts/sidebar.007' ON DUPLICATE KEY UPDATE value = 'Fire Service Incidents';
INSERT INTO translation_keys (str_key, str_group) VALUES ('layouts/sidebar.008', 'layouts/sidebar') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Έλεγχος Ειδοποιήσεων' FROM translation_keys WHERE str_key = 'layouts/sidebar.008' ON DUPLICATE KEY UPDATE value = 'Έλεγχος Ειδοποιήσεων';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Notification Center' FROM translation_keys WHERE str_key = 'layouts/sidebar.008' ON DUPLICATE KEY UPDATE value = 'Notification Center';
INSERT INTO translation_keys (str_key, str_group) VALUES ('layouts/sidebar.009', 'layouts/sidebar') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Ημερολόγιο' FROM translation_keys WHERE str_key = 'layouts/sidebar.009' ON DUPLICATE KEY UPDATE value = 'Ημερολόγιο';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Calendar' FROM translation_keys WHERE str_key = 'layouts/sidebar.009' ON DUPLICATE KEY UPDATE value = 'Calendar';
INSERT INTO translation_keys (str_key, str_group) VALUES ('layouts/sidebar.010', 'layouts/sidebar') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Δηλώσεις Συμμετοχής' FROM translation_keys WHERE str_key = 'layouts/sidebar.010' ON DUPLICATE KEY UPDATE value = 'Δηλώσεις Συμμετοχής';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Applications' FROM translation_keys WHERE str_key = 'layouts/sidebar.010' ON DUPLICATE KEY UPDATE value = 'Applications';
INSERT INTO translation_keys (str_key, str_group) VALUES ('layouts/sidebar.011', 'layouts/sidebar') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Στατιστικά & Τάσεις' FROM translation_keys WHERE str_key = 'layouts/sidebar.011' ON DUPLICATE KEY UPDATE value = 'Στατιστικά & Τάσεις';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Statistics & Trends' FROM translation_keys WHERE str_key = 'layouts/sidebar.011' ON DUPLICATE KEY UPDATE value = 'Statistics & Trends';
INSERT INTO translation_keys (str_key, str_group) VALUES ('layouts/sidebar.012', 'layouts/sidebar') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Επιβράβευση Ομάδων' FROM translation_keys WHERE str_key = 'layouts/sidebar.012' ON DUPLICATE KEY UPDATE value = 'Επιβράβευση Ομάδων';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Team Awards' FROM translation_keys WHERE str_key = 'layouts/sidebar.012' ON DUPLICATE KEY UPDATE value = 'Team Awards';
INSERT INTO translation_keys (str_key, str_group) VALUES ('layouts/sidebar.013', 'layouts/sidebar') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Αναφορές' FROM translation_keys WHERE str_key = 'layouts/sidebar.013' ON DUPLICATE KEY UPDATE value = 'Αναφορές';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Reports' FROM translation_keys WHERE str_key = 'layouts/sidebar.013' ON DUPLICATE KEY UPDATE value = 'Reports';
INSERT INTO translation_keys (str_key, str_group) VALUES ('layouts/sidebar.014', 'layouts/sidebar') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Ρυθμίσεις' FROM translation_keys WHERE str_key = 'layouts/sidebar.014' ON DUPLICATE KEY UPDATE value = 'Ρυθμίσεις';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Settings' FROM translation_keys WHERE str_key = 'layouts/sidebar.014' ON DUPLICATE KEY UPDATE value = 'Settings';
INSERT INTO translation_keys (str_key, str_group) VALUES ('layouts/sidebar.015', 'layouts/sidebar') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Ετοιμότητα Ομάδας' FROM translation_keys WHERE str_key = 'layouts/sidebar.015' ON DUPLICATE KEY UPDATE value = 'Ετοιμότητα Ομάδας';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Team Readiness' FROM translation_keys WHERE str_key = 'layouts/sidebar.015' ON DUPLICATE KEY UPDATE value = 'Team Readiness';
INSERT INTO translation_keys (str_key, str_group) VALUES ('layouts/sidebar.016', 'layouts/sidebar') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Οι Δηλώσεις μας' FROM translation_keys WHERE str_key = 'layouts/sidebar.016' ON DUPLICATE KEY UPDATE value = 'Οι Δηλώσεις μας';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Our Applications' FROM translation_keys WHERE str_key = 'layouts/sidebar.016' ON DUPLICATE KEY UPDATE value = 'Our Applications';
INSERT INTO translation_keys (str_key, str_group) VALUES ('layouts/sidebar.017', 'layouts/sidebar') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Μέλη Ομάδας' FROM translation_keys WHERE str_key = 'layouts/sidebar.017' ON DUPLICATE KEY UPDATE value = 'Μέλη Ομάδας';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Team Members' FROM translation_keys WHERE str_key = 'layouts/sidebar.017' ON DUPLICATE KEY UPDATE value = 'Team Members';
INSERT INTO translation_keys (str_key, str_group) VALUES ('layouts/sidebar.018', 'layouts/sidebar') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Στατιστικά Ομάδας' FROM translation_keys WHERE str_key = 'layouts/sidebar.018' ON DUPLICATE KEY UPDATE value = 'Στατιστικά Ομάδας';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Team Statistics' FROM translation_keys WHERE str_key = 'layouts/sidebar.018' ON DUPLICATE KEY UPDATE value = 'Team Statistics';
INSERT INTO translation_keys (str_key, str_group) VALUES ('layouts/sidebar.019', 'layouts/sidebar') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Φορείς' FROM translation_keys WHERE str_key = 'layouts/sidebar.019' ON DUPLICATE KEY UPDATE value = 'Φορείς';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Organizations' FROM translation_keys WHERE str_key = 'layouts/sidebar.019' ON DUPLICATE KEY UPDATE value = 'Organizations';
INSERT INTO translation_keys (str_key, str_group) VALUES ('layouts/sidebar.020', 'layouts/sidebar') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Ομάδες & Εθελοντές' FROM translation_keys WHERE str_key = 'layouts/sidebar.020' ON DUPLICATE KEY UPDATE value = 'Ομάδες & Εθελοντές';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Teams & Volunteers' FROM translation_keys WHERE str_key = 'layouts/sidebar.020' ON DUPLICATE KEY UPDATE value = 'Teams & Volunteers';
INSERT INTO translation_keys (str_key, str_group) VALUES ('layouts/sidebar.021', 'layouts/sidebar') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Χρήστες' FROM translation_keys WHERE str_key = 'layouts/sidebar.021' ON DUPLICATE KEY UPDATE value = 'Χρήστες';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Users' FROM translation_keys WHERE str_key = 'layouts/sidebar.021' ON DUPLICATE KEY UPDATE value = 'Users';

INSERT INTO translation_keys (str_key, str_group) VALUES ('authority/municipality.event_plural', 'authority') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Δράσεις' FROM translation_keys WHERE str_key = 'authority/municipality.event_plural' ON DUPLICATE KEY UPDATE value = 'Δράσεις';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Events' FROM translation_keys WHERE str_key = 'authority/municipality.event_plural' ON DUPLICATE KEY UPDATE value = 'Events';
INSERT INTO translation_keys (str_key, str_group) VALUES ('authority/municipality.team_plural', 'authority') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Εθελοντικές Ομάδες' FROM translation_keys WHERE str_key = 'authority/municipality.team_plural' ON DUPLICATE KEY UPDATE value = 'Εθελοντικές Ομάδες';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Volunteer Teams' FROM translation_keys WHERE str_key = 'authority/municipality.team_plural' ON DUPLICATE KEY UPDATE value = 'Volunteer Teams';
INSERT INTO translation_keys (str_key, str_group) VALUES ('authority/civil_protection.event_plural', 'authority') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Αποστολές' FROM translation_keys WHERE str_key = 'authority/civil_protection.event_plural' ON DUPLICATE KEY UPDATE value = 'Αποστολές';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Missions' FROM translation_keys WHERE str_key = 'authority/civil_protection.event_plural' ON DUPLICATE KEY UPDATE value = 'Missions';
INSERT INTO translation_keys (str_key, str_group) VALUES ('authority/civil_protection.team_plural', 'authority') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Ομάδες Πολιτικής Προστασίας' FROM translation_keys WHERE str_key = 'authority/civil_protection.team_plural' ON DUPLICATE KEY UPDATE value = 'Ομάδες Πολιτικής Προστασίας';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Civil Protection Teams' FROM translation_keys WHERE str_key = 'authority/civil_protection.team_plural' ON DUPLICATE KEY UPDATE value = 'Civil Protection Teams';
INSERT INTO translation_keys (str_key, str_group) VALUES ('authority/fire_service.event_plural', 'authority') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Αποστολές' FROM translation_keys WHERE str_key = 'authority/fire_service.event_plural' ON DUPLICATE KEY UPDATE value = 'Αποστολές';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Missions' FROM translation_keys WHERE str_key = 'authority/fire_service.event_plural' ON DUPLICATE KEY UPDATE value = 'Missions';
INSERT INTO translation_keys (str_key, str_group) VALUES ('authority/fire_service.team_plural', 'authority') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Ομάδες / Κλιμάκια' FROM translation_keys WHERE str_key = 'authority/fire_service.team_plural' ON DUPLICATE KEY UPDATE value = 'Ομάδες / Κλιμάκια';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Teams / Units' FROM translation_keys WHERE str_key = 'authority/fire_service.team_plural' ON DUPLICATE KEY UPDATE value = 'Teams / Units';
INSERT INTO translation_keys (str_key, str_group) VALUES ('authority/coast_guard.event_plural', 'authority') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Αποστολές' FROM translation_keys WHERE str_key = 'authority/coast_guard.event_plural' ON DUPLICATE KEY UPDATE value = 'Αποστολές';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Missions' FROM translation_keys WHERE str_key = 'authority/coast_guard.event_plural' ON DUPLICATE KEY UPDATE value = 'Missions';
INSERT INTO translation_keys (str_key, str_group) VALUES ('authority/coast_guard.team_plural', 'authority') ON DUPLICATE KEY UPDATE str_group = str_group;
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Ομάδες Διάσωσης' FROM translation_keys WHERE str_key = 'authority/coast_guard.team_plural' ON DUPLICATE KEY UPDATE value = 'Ομάδες Διάσωσης';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'Rescue Teams' FROM translation_keys WHERE str_key = 'authority/coast_guard.team_plural' ON DUPLICATE KEY UPDATE value = 'Rescue Teams';

INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'el', 'Η αλλαγή γλώσσας εφαρμόζεται άμεσα σε όλες τις σελίδες.' FROM translation_keys WHERE str_key = 'auth/profile.017' ON DUPLICATE KEY UPDATE value = 'Η αλλαγή γλώσσας εφαρμόζεται άμεσα σε όλες τις σελίδες.';
INSERT INTO translation_values (key_id, language_code, value) SELECT id, 'en', 'The language change applies immediately across all pages.' FROM translation_keys WHERE str_key = 'auth/profile.017' ON DUPLICATE KEY UPDATE value = 'The language change applies immediately across all pages.';
