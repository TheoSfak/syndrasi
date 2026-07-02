# Feature Spec — Έξυπνη Διάθεση Πόρων (Smart Resource Dispatch)

**Status:** Draft v2 (delta) · **Target version:** 0.16.0-beta
**Αντικαθιστά** το αρχικό «Μητρώο Πόρων» spec: ΔΕΝ χτίζουμε νέο μητρώο εξοπλισμού —
χρησιμοποιούμε **ό,τι υπάρχει ήδη** και προσθέτουμε μόνο το κομμάτι που λείπει.

---

## 1. Τι υπάρχει ήδη (δεν ξαναφτιάχνεται)

| Υπάρχον | Πού |
|---|---|
| Δήλωση εξοπλισμού/ικανοτήτων ομάδας | `volunteer_teams.readiness_items_json`, `has_vehicle`, `has_medical_equipment` (+ UI στο team portal, options από playbooks μέσω `VolunteerTeam::readinessOptionsForMunicipality`) |
| Απαιτήσεις δράσης | `events.requested_items_json` + playbooks `requested_items` |
| Matching ομάδας ↔ δράσης | `TeamMissionMatcher` (score, matched/missing items) |
| Ελλείψεις | `shortage_reports` (`shortage_type` enum: people/equipment/medical_supplies/vehicle/other, severity, open→acknowledged→resolved) |
| Ειδοποιήσεις | `NotificationService` + Web Push, field hub `/f/{token}`, `/team/live/{id}` |
| War-room live | `OperationController` κοινό snapshot (ενοποιημένα queries από 0.15.12) |

## 2. Τι λείπει (το delta — αυτό χτίζουμε)

Όταν μια ομάδα αναφέρει έλλειψη, ο χειριστής σήμερα πρέπει να θυμάται/ψάχνει ποια άλλη
ομάδα έχει τον πόρο και να την ειδοποιήσει χειροκίνητα. Το feature:

1. **Πρόταση**: η κάρτα έλλειψης στο war-room δείχνει αυτόματα «💡 Η Ομάδα Χ έχει: Γεννήτρια».
2. **Αίτημα διάθεσης** με ένα κλικ → push + κάρτα στο κινητό της ομάδας.
3. **Απάντηση από το πεδίο**: Αποδοχή (+ETA) / Αδυναμία, χωρίς login (field link) ή από team live.
4. **Παρακολούθηση**: pending → accepted → delivered στο war-room· στο delivered προτείνεται
   επίλυση της έλλειψης με ένα κλικ.
5. **Μετρικές** χρόνου απόκρισης στο Story της δράσης.

---

## 3. Migration `039_resource_requests.sql` (μία μόνο)

```sql
CREATE TABLE resource_requests (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  municipality_id INT NOT NULL,
  event_id        INT NOT NULL,
  shortage_id     INT NULL,                    -- shortage_reports.id (πηγή)
  from_team_id    INT NOT NULL,                -- η ομάδα που έχει τον πόρο
  item_label      VARCHAR(255) NOT NULL,       -- π.χ. «Γεννήτρια» (readiness item ή ελεύθερο)
  requested_by    INT NULL,                    -- users.id (χειριστής)
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
```

Model `ResourceRequest`: `create()`, `respond($id, $status, $note, $eta)`,
`markDelivered($id)`, `cancel($id)`, `pendingForTeam($teamId, $eventId)`, `forEvent($eid)`.

---

## 4. `ResourceMatcher` (νέο service, ~100 γραμμές)

`ResourceMatcher::suggestForShortage(array $shortage): array`

- Υποψήφιες: ενεργές ομάδες του δήμου **εκτός** αυτής που ανέφερε την έλλειψη·
  προτεραιότητα σε ομάδες με εγκεκριμένη αίτηση στην ίδια δράση (είναι ήδη κοντά).
- Αντιστοίχιση ανά `shortage_type`:
  - `vehicle` → `has_vehicle = 1`
  - `medical_supplies` → `has_medical_equipment = 1`
  - `equipment` / `other` → keyword match: tokens από `title` + `description` της έλλειψης
    vs `VolunteerTeam::readinessItems()` (mb_strtolower, αμφίδρομο substring) + μικρός
    πίνακας συνωνύμων (π.χ. «ρεύμα/γεννήτρια», «νερό/βυτίο», «φως/προβολέας»).
  - `people` → εκτός scope (καλύπτεται από mobilizations).
- Επιστρέφει `[team_id, team_name, matched_items[], in_event(bool)]`, ταξινομημένο.
- Δεν προτείνει ομάδα με ήδη pending/accepted request για το ίδιο shortage.

---

## 5. War-room (`views/operations/event.php` + snapshot)

- Στο snapshot: `shortages[].suggestions` (από τον matcher, μόνο για open/acknowledged)
  και νέο array `resource_requests` (status, ομάδα, item, ETA, χρόνοι).
- Κάρτα έλλειψης: chips «💡 Ομάδα Χ — Γεννήτρια [Αίτημα]» → `POST` δημιουργίας αιτήματος.
- Νέο μικρό panel «Αιτήματα πόρων» (ίδιο μοτίβο με «Βίντεο πεδίου»): λίστα με status
  badge, ETA, κουμπί «Παραδόθηκε» (operator) και «Ακύρωση».
- `delivered` σε request δεμένο με shortage → εμφανίζεται πρόταση «Επίλυση έλλειψης;».
- Activity feed: εγγραφές για create/accept/decline/deliver.

## 6. Απάντηση ομάδας (πεδίο)

- **Field hub** `/f/{token}` και **team live** `/team/live/{id}`: κάρτα «Αίτημα πόρου»
  (item, δράση, ποιος το ζητά) με κουμπιά **Αποδοχή** (+ προαιρετικό ETA λεπτά, σχόλιο)
  / **Αδυναμία** (+ σχόλιο). Εμφανίζεται μέσω των υπαρχόντων polls (commsFeed pattern).
- Push: νέο template `resourceRequested` στο `NotificationService` (στους users της
  ομάδας)· αντίστοιχα `resourceAccepted`/`resourceDeclined` προς τον χειριστή (in-app +
  activity). Αν η ομάδα έχει `telegram_chat_id`, αξιοποιείται το υπάρχον κανάλι.

## 7. Routes (deny-by-default router — δηλωμένα roles ΠΑΝΤΑ)

```php
POST /operations/events/{id}/resource-request        roles: municipality_admin, event_operator
POST /operations/resource-requests/{id}/delivered     roles: municipality_admin, event_operator
POST /operations/resource-requests/{id}/cancel        roles: municipality_admin, event_operator
POST /team/resource-requests/{id}/respond             roles: team_admin
POST /f/{token}/resource-requests/{id}/respond        public: true (ταυτοποίηση μέσω token,
                                                      έλεγχος ότι το request ανήκει στην ομάδα του token)
```

## 8. Story & μετρικές

`StoryService::build()`: ενότητα «Αιτήματα πόρων» — ανά αίτημα created→responded→delivered
διάρκειες, ποσοστό αποδοχής· μπαίνει στον υπάρχοντα πίνακα μετρικών απόκρισης.

## 9. (Προαιρετική φάση) Εικόνα ετοιμότητας δήμου

Widget στο dashboard: άθροισμα readiness items σε όλες τις ενεργές ομάδες (πόσες ομάδες
έχουν όχημα / υγειονομικό / ανά item), από τα υπάρχοντα πεδία — καθόλου νέο schema.

---

## 10. Φάσεις υλοποίησης (κάθε φάση: commit + tag + GitHub Release)

| Φάση | Παραδοτέο | Έκδοση |
|---|---|---|
| 1 | Migration 039, `ResourceRequest`, `ResourceMatcher`, war-room suggestions + δημιουργία αιτήματος + panel | 0.16.0-beta |
| 2 | Απάντηση από field hub / team live + push templates + activity feed | 0.16.1-beta |
| 3 | Delivered → πρόταση resolve, Story μετρικές | 0.16.2-beta |
| 4 | (προαιρετικό) Dashboard widget ετοιμότητας | 0.16.3-beta |

## 11. Edge cases / κανόνες

- Tenancy: όλα φιλτράρουν `municipality_id`.
- Διπλά αιτήματα: unique έλεγχος (shortage_id, from_team_id) σε pending/accepted.
- Έλλειψη που επιλύεται χειροκίνητα → auto-cancel των pending requests της.
- Δράση που κλείνει → auto-cancel pending.
- PDO: μοναδικά named placeholders (`:eid1`, `:eid2`) — ποτέ το ίδιο δύο φορές.
- PHP edits με Write tool σε `C:\` paths (όχι bash heredoc)· sync σε Desktop repo **και**
  `C:\xampp\htdocs\syndrasi`· μεγάλα αρχεία → backup πρώτα στο `_backups/` (βλ. SESSION_HANDOFF).
