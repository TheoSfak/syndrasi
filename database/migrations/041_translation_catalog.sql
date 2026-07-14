-- 041_translation_catalog.sql
-- Foundation for multi-language UI translation: a `languages` table plus a
-- normalized key/value translation catalog (translation_keys +
-- translation_values), so adding a language later is a data operation, not a
-- schema change. Also adds users.language_code so each user can pick their
-- own language independently (self-service; stored now, but the 73 views
-- don't render translated text yet — that's a separate follow-up project).

CREATE TABLE languages (
  code        VARCHAR(10) PRIMARY KEY,
  name        VARCHAR(64) NOT NULL,
  is_source   TINYINT(1) NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  sort_order  INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO languages (code, name, is_source, is_active, sort_order) VALUES
  ('el', 'Ελληνικά', 1, 1, 0),
  ('en', 'English',  0, 1, 1);

CREATE TABLE translation_keys (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  str_key     VARCHAR(190) NOT NULL,
  str_group   VARCHAR(120) NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_translation_keys_str_key (str_key),
  INDEX idx_translation_keys_group (str_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE translation_values (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  key_id         INT NOT NULL,
  language_code  VARCHAR(10) NOT NULL,
  value          TEXT NOT NULL,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_translation_values_key_lang (key_id, language_code),
  FOREIGN KEY (key_id) REFERENCES translation_keys(id) ON DELETE CASCADE,
  FOREIGN KEY (language_code) REFERENCES languages(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
  ADD COLUMN language_code VARCHAR(10) NULL DEFAULT NULL AFTER status,
  ADD FOREIGN KEY (language_code) REFERENCES languages(code);
