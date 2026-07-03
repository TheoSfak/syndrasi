-- SynDrasi Migration 039 - Smart Resource Dispatch (Φάση 1)
-- Αιτήματα διάθεσης πόρων: ο χειριστής ζητά από ομάδα Χ έναν πόρο (π.χ. γεννήτρια)
-- που λείπει σε δράση. Δένεται προαιρετικά με shortage_reports. Η δήλωση του τι
-- έχει κάθε ομάδα ΔΕΝ αλλάζει — μένει στα volunteer_teams.readiness_items_json /
-- has_vehicle / has_medical_equipment (βλ. docs/RESOURCE_DISPATCH_SPEC.md).

CREATE TABLE IF NOT EXISTS resource_requests (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id        INT NOT NULL,
  shortage_id     INT NULL,
  from_team_id    INT NOT NULL,
  item_label      VARCHAR(255) NOT NULL,
  requested_by    INT NULL,
  status          ENUM('pending','accepted','declined','delivered','cancelled')
                  NOT NULL DEFAULT 'pending',
  response_note   VARCHAR(255) NULL,
  eta_minutes     INT NULL,
  responded_at    DATETIME NULL,
  delivered_at    DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rr_event (event_id, status),
  INDEX idx_rr_team (from_team_id, status),
  INDEX idx_rr_shortage (shortage_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
