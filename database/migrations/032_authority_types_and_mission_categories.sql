-- SynDrasi Migration 032 - authority types and mission categories
-- Superadmin-owned authority identity and per-authority mission categories.

ALTER TABLE municipalities
  ADD COLUMN IF NOT EXISTS authority_type ENUM('municipality','civil_protection','fire_service','coast_guard') NOT NULL DEFAULT 'municipality' AFTER name,
  ADD COLUMN IF NOT EXISTS official_name VARCHAR(255) NULL AFTER authority_type,
  ADD COLUMN IF NOT EXISTS short_name VARCHAR(80) NULL AFTER official_name;

UPDATE municipalities m
LEFT JOIN municipality_settings s_type
  ON s_type.municipality_id = m.id AND s_type.setting_key = 'org_type'
LEFT JOIN municipality_settings s_name
  ON s_name.municipality_id = m.id AND s_name.setting_key = 'org_name'
LEFT JOIN municipality_settings s_short
  ON s_short.municipality_id = m.id AND s_short.setting_key = 'org_name_short'
SET m.authority_type = CASE
      WHEN s_type.setting_value IN ('civil_protection','fire_service','coast_guard') THEN s_type.setting_value
      ELSE 'municipality'
    END,
    m.official_name = NULLIF(s_name.setting_value, ''),
    m.short_name = NULLIF(s_short.setting_value, '');

ALTER TABLE event_categories
  ADD COLUMN IF NOT EXISTS authority_type ENUM('municipality','civil_protection','fire_service','coast_guard') NOT NULL DEFAULT 'municipality' AFTER id,
  ADD INDEX IF NOT EXISTS idx_event_categories_authority (authority_type);

CREATE TEMPORARY TABLE tmp_authority_event_categories (
  authority_type ENUM('municipality','civil_protection','fire_service','coast_guard') NOT NULL,
  name VARCHAR(150) NOT NULL
) ENGINE=Memory;

INSERT INTO tmp_authority_event_categories (authority_type, name) VALUES
('municipality', 'Πολιτιστική εκδήλωση'),
('municipality', 'Αθλητική δράση'),
('municipality', 'Κοινωνική δράση'),
('municipality', 'Περιβαλλοντική δράση'),
('municipality', 'Υποστήριξη εκδήλωσης'),
('municipality', 'Έκτακτη ανάγκη'),
('fire_service', 'Πυρκαγιά δασική'),
('fire_service', 'Πυρκαγιά αστική'),
('fire_service', 'Απεγκλωβισμός'),
('fire_service', 'Τροχαίο'),
('fire_service', 'Παροχή βοήθειας'),
('fire_service', 'Έρευνα αγνοουμένου'),
('fire_service', 'Πλημμυρικό συμβάν'),
('fire_service', 'Επιφυλακή / κάλυψη περιοχής'),
('civil_protection', 'Σεισμός'),
('civil_protection', 'Πλημμύρα'),
('civil_protection', 'Κακοκαιρία'),
('civil_protection', 'Εκκένωση'),
('civil_protection', 'Διανομή ειδών ανάγκης'),
('civil_protection', 'Υποστήριξη καταυλισμού'),
('civil_protection', 'Έλεγχος υποδομών'),
('civil_protection', 'Συντονισμός εθελοντών'),
('coast_guard', 'Θαλάσσια διάσωση'),
('coast_guard', 'Έρευνα στη θάλασσα'),
('coast_guard', 'Ρύπανση'),
('coast_guard', 'Ναυτικό ατύχημα'),
('coast_guard', 'Απεγκλωβισμός σκάφους'),
('coast_guard', 'Υποστήριξη λιμένα'),
('coast_guard', 'Μεταφορά / συνδρομή');

INSERT INTO event_categories (authority_type, name)
SELECT t.authority_type, t.name
FROM tmp_authority_event_categories t
LEFT JOIN event_categories ec
  ON ec.authority_type = t.authority_type AND ec.name = t.name
WHERE ec.id IS NULL;

DROP TEMPORARY TABLE tmp_authority_event_categories;
