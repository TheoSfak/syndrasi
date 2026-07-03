# Changelog

All notable changes to SynDrasi are documented here.
Format loosely follows [Keep a Changelog](https://keepachangelog.com/);
versioning is `MAJOR.MINOR.PATCH` (beta line until feature-complete).

## [0.16.3-beta] — 2026-07-02

### Feature — Έξυπνη Διάθεση Πόρων, Φάση 4 (Εικόνα Ετοιμότητας)

- Νέο widget «Εικόνα Ετοιμότητας» στον Πίνακα Ελέγχου: πόσες ενεργές ομάδες διαθέτουν
  όχημα / υγειονομικό και πόσες έχουν δηλώσει κάθε πόρο/ικανότητα (π.χ. «Γεννήτρια ×2»),
  από τα υπάρχοντα Προφίλ Ετοιμότητας — κανένα νέο schema.
- Τα διπλότυπα items ανά ομάδα (διαφορά κεφαλαίων/κενών) μετρούν μία φορά.
- Αν καμία ομάδα δεν έχει συμπληρώσει προφίλ, το widget προτρέπει να ζητηθεί από τους
  αρχηγούς ομάδων.

Ολοκληρώνεται το Smart Resource Dispatch (Φάσεις 1–4, βλ. docs/RESOURCE_DISPATCH_SPEC.md).

---

## [0.16.2-beta] — 2026-07-02

### Feature — Έξυπνη Διάθεση Πόρων, Φάση 3 (επίλυση με ένα κλικ + μετρικές Story)

- **Παράδοση → πρόταση επίλυσης**: όταν ένα αίτημα πόρου δεμένο με έλλειψη σημανθεί
  «Παραδόθηκε», η κάρτα του στο war-room δείχνει κουμπί «Επίλυση έλλειψης;» που κλείνει
  τη συνδεδεμένη έλλειψη με ένα κλικ (εφόσον δεν έχει ήδη επιλυθεί).
- **Story — νέα ενότητα «Αιτήματα πόρων»**: ανά αίτημα χρόνος απόκρισης
  (δημιουργία→απάντηση) και παράδοσης (δημιουργία→παράδοση), ETA, σχόλιο ομάδας
  (μόνο στην εσωτερική προβολή), συνολικό ποσοστό αποδοχής και μέσοι χρόνοι.
- Το timeline του Story δείχνει πλέον τον πλήρη κύκλο κάθε αιτήματος (αίτημα /
  αποδοχή με διάρκεια απόκρισης και ETA / αδυναμία / παράδοση με συνολικό χρόνο).
- Ο απολογισμός (summary) μετρά και τα αιτήματα πόρων.

---

## [0.16.1-beta] — 2026-07-02

### Feature — Έξυπνη Διάθεση Πόρων, Φάση 2 (απάντηση ομάδας από το πεδίο)

- Οι ομάδες απαντούν πλέον στα αιτήματα πόρων — κλείνει ο κύκλος της Φάσης 1:
  **Αποδοχή** (με προαιρετικό ETA σε λεπτά και σχόλιο) ή **Αδυναμία** (με προαιρετικό
  σχόλιο), από το **field link** `/f/{token}` (χωρίς login) και από το **team live**.
- Νέες κάρτες «Αίτημα πόρου» και στις δύο οθόνες, μέσω των υπαρχόντων polls· στο field
  hub με ηχητική ειδοποίηση/δόνηση σε νέο αίτημα (ίδιο μοτίβο με αίτημα φωτογραφίας/GPS).
- Νέα routes: `POST /team/resource-requests/{id}/respond` (team_admin) και
  `POST /f/{token}/resource-requests/{id}/respond` (ταυτοποίηση μέσω token — το αίτημα
  πρέπει να ανήκει στην ομάδα ΚΑΙ στη δράση του token). Μόνο εκκρεμή αιτήματα αλλάζουν
  κατάσταση (409 αλλιώς).
- Νέα notifications `resourceAccepted` / `resourceDeclined` προς όλο το προσωπικό
  διοίκησης (in-app + Web Push), με το ETA/σχόλιο στο μήνυμα.
- Το activity feed του war-room δείχνει πλέον όλο τον κύκλο ζωής του αιτήματος
  (δημιουργία / αποδοχή με ETA / αδυναμία / παράδοση).

---

## [0.16.0-beta] — 2026-07-02

### Feature — Έξυπνη Διάθεση Πόρων, Φάση 1 (Smart Resource Dispatch)

- Νέο migration `039_resource_requests.sql`: πίνακας `resource_requests`
  (pending→accepted/declined→delivered/cancelled), δεμένος προαιρετικά με `shortage_reports`.
- `ResourceMatcher` (νέο service): σε κάθε ανοιχτή έλλειψη προτείνει ποιες ομάδες του δήμου
  διαθέτουν τον πόρο, με βάση τα υπάρχοντα `readiness_items_json` / `has_vehicle` /
  `has_medical_equipment` — keyword match με συνώνυμα και ελληνικό stemming, προτεραιότητα
  σε ομάδες ήδη εγκεκριμένες στη δράση.
- War-room: οι κάρτες ελλείψεων δείχνουν προτάσεις «💡 Ομάδα Χ — Γεννήτρια» με κουμπί
  «Αίτημα» (ένα κλικ)· νέο panel «Αιτήματα πόρων» με status, κουμπιά «Παραδόθηκε»/«Ακύρωση».
  Τα δεδομένα μπαίνουν στο κοινό snapshot (SSE + polling fallback).
- Νέο notification `resourceRequested` (in-app + Web Push στην ομάδα).
- Χειροκίνητη επίλυση έλλειψης ακυρώνει αυτόματα τα pending αιτήματά της· δεν προτείνονται
  ομάδες με ήδη ανοιχτό αίτημα για την ίδια έλλειψη.
- Φάση 2 (επόμενη): απάντηση Αποδοχή/Αδυναμία από field hub / team live.

## [0.15.13-beta] — 2026-07-02

### Docs — Spec «Έξυπνη Διάθεση Πόρων» (Smart Resource Dispatch)

- Νέο `docs/RESOURCE_DISPATCH_SPEC.md`: σχεδιασμός για αυτόματη πρόταση ομάδας-πόρου σε
  κάθε έλλειψη (πάνω στο υπάρχον readiness σύστημα — `readiness_items_json`,
  `TeamMissionMatcher`, `shortage_reports`), αιτήματα διάθεσης με απάντηση Αποδοχή/Αδυναμία
  από field hub / team live, παρακολούθηση pending→accepted→delivered στο war-room και
  μετρικές απόκρισης στο Story. Migration 039, target 0.16.x. Χωρίς αλλαγές κώδικα.

## [0.15.12-beta] — 2026-07-02

### Security — Deny-by-default route middleware

- Το `Router::dispatch()` πλέον επιβάλλει έλεγχο πρόσβασης πριν αρχικοποιήσει οποιονδήποτε controller: κάθε route δηλώνει ρητά `['public' => true]` ή `['roles' => [...]]`, αλλιώς απαιτείται συνδεδεμένος χρήστης εξ ορισμού.
- Πριν, η ασφάλεια εξαρτιόταν αποκλειστικά από το να θυμηθεί κάθε action να καλέσει `requireRole()`/`requireLogin()` — ένα νέο route χωρίς αυτή τη γραμμή έμενε ανοιχτό σιωπηλά. Ελέγχθηκαν και σχολιάστηκαν και τα 227 υπάρχοντα routes.
- Εντοπίστηκε και διορθώθηκε ένα route (`/mobilizations` και σχετικά) που δεν είχε κανέναν έλεγχο σε επίπεδο router.
- Άγνωστος controller σε route πλέον επιστρέφει καθαρό 500 αντί για fatal error.

### Technical — Πρώτο δίχτυ ασφαλείας: PHPStan + PHPUnit + CI

- Προστέθηκε `composer.json` (μόνο dev dependencies· η εφαρμογή παραμένει χωρίς runtime dependencies), PHPStan (level 5) και PHPUnit, με GitHub Actions να τρέχει lint + phpstan + phpunit σε κάθε push/PR.
- Το PHPStan εντόπισε άμεσα δύο πραγματικά bugs (πιθανώς μη-ορισμένες μεταβλητές μετά από redirect() μέσα σε catch block) — διορθώθηκαν προσθέτοντας `never` return type στα `redirect()`, `json_out()`, `render()`, `abort()`.
- 22 αρχικά unit tests για την καθαρή λογική χωρίς side-effects (μηχανή καταστάσεων `Event::canTransition()`, ελληνικά labels/μορφοποίηση, ορολογία φορέων).

### Cleanup — Αφαίρεση διπλότυπου κώδικα ανεβάσματος φωτογραφιών/βίντεο

- Νέα κλάση `MediaUploader` συγκεντρώνει τον έλεγχο μεγέθους/τύπου αρχείου, αποθήκευση και lat/lng clamping που ήταν αντιγραμμένος σχεδόν αυτούσιος σε 4 σημεία (`FieldController::photo/video`, `TeamPortalController::uploadPhoto/uploadVideo`).
- Διορθώθηκαν 4 άδεια `catch (Throwable $e) {}` γύρω από ειδοποιήσεις — πλέον καταγράφονται στο error log αντί να καταπίνονται σιωπηλά.

### Cleanup — Ενοποίηση ερωτημάτων live-ops

- Τα `status()`, `stream()` και `locations()` του `OperationController` είχαν το καθένα δικό του αντίγραφο των ερωτημάτων για ελλείψεις/σημειώσεις/αιτήσεις/στίγματα — είχαν ήδη αποκλίνει (η ζωντανή προβολή δεν έδειχνε ποιος επιβεβαίωσε/έλυσε μια έλλειψη, ενώ η χειροκίνητη ανανέωση ναι). Ενοποιήθηκαν σε κοινές συναρτήσεις.

### Cleanup — Έξοδος rate-limit και σημαιών cron από τα app_settings

- Νέος πίνακας `rate_limits` για τους μετρητές rate-limit σύνδεσης/επαναφοράς κωδικού (migration 037).
- Το `event_shifts` αποκτά στήλη `reminded_at` (migration 038) αντί για μία εγγραφή `app_settings` ανά βάρδια για πάντα, που απαιτούσε περιοδικό καθαρισμό.

## [0.15.11-beta] — 2026-07-01

### Fix — Telegram supergroup migration

- Διορθώθηκε αποτυχία Telegram όταν ένα group έχει μετατραπεί σε supergroup (`migrate_to_chat_id`).
- Το σύστημα ξαναδοκιμάζει αυτόματα με το νέο supergroup Chat ID και ενημερώνει τις αποθηκευμένες ρυθμίσεις command/team chat όπου γίνεται.
- Το δοκιμαστικό Telegram ενημερώνει πλέον το κοινό Chat ID ομάδων όταν εντοπίζεται migration και εμφανίζει καθαρό μήνυμα στον admin.

---

## [0.15.9-beta] — 2026-06-30

### Feature — Team readiness matching

- Προστέθηκε migration `036_team_readiness_profiles.sql`.
- Οι ομάδες αποκτούν Προφίλ Επιχειρησιακής Ετοιμότητας με τυπική δύναμη, όχημα, υγειονομικό και checklist δυνατοτήτων/εξοπλισμού από τα playbooks.
- Προστέθηκε σελίδα αρχηγού ομάδας `Ετοιμότητα Ομάδας` για self-service ενημέρωση δυνατοτήτων.
- Προστέθηκε `TeamMissionMatcher` που βγάζει match score με βάση άτομα, όχημα, υγειονομικό και ζητούμενα αντικείμενα αποστολής.
- Ο admin βλέπει προτεινόμενες ομάδες μέσα στην αποστολή και match score στις δηλώσεις συμμετοχής.
- Η ομάδα βλέπει το δικό της match στην αποστολή και τι λείπει/δεν έχει δηλωθεί.
- Διορθώθηκε το sidebar icon του `Έλεγχος Ειδοποιήσεων`.

---

## [0.15.8-beta] — 2026-06-30

### Feature — Playbook requested items

- Προστέθηκε migration `035_playbook_requested_items.sql`.
- Τα αντικείμενα/μέσα που προτείνει κάθε playbook γίνονται πλέον δομημένα «Ζητούμενα αντικείμενα» στην αποστολή.
- Η φόρμα αποστολής τα προγεμίζει ενεργά από το πρωτόκολλο, επιτρέπει απενεργοποίηση ανά αντικείμενο και δέχεται έξτρα αντικείμενα από τον admin.
- Τα τελικά ζητούμενα αντικείμενα εμφανίζονται στα στοιχεία αποστολής για admin και team portal.

---

## [0.15.7-beta] — 2026-06-30

### Data — Complete non-municipality mission playbooks

- Προστέθηκε migration `034_complete_non_municipality_playbooks.sql`.
- Καλύπτονται πλέον όλες οι κατηγορίες αποστολών για Πυροσβεστική, Πολιτική Προστασία και Λιμενικό.
- Το verification δείχνει 8/8 playbooks για Πυροσβεστική, 8/8 για Πολιτική Προστασία και 7/7 για Λιμενικό.
- Οι κατηγορίες Δήμου παραμένουν χωρίς playbooks, όπως ζητήθηκε.

---

## [0.15.6-beta] — 2026-06-30

### Feature — Mission Playbooks

- Προστέθηκε migration `033_event_playbooks.sql` με operational playbooks ανά κατηγορία αποστολής.
- Η φόρμα νέας αποστολής εμφανίζει playbook preview και μπορεί να εφαρμόσει αυτόματα προτεινόμενα άτομα, όχημα, υγειονομικό εξοπλισμό και οδηγίες.
- Το Επιχειρησιακό Κέντρο εμφανίζει checklist, απαιτούμενες δυνατότητες και έτοιμα μηνύματα playbook.
- Το checklist αποθηκεύει την κατάσταση ανά αποστολή στο browser για γρήγορη επιχειρησιακή χρήση.
- Το team debrief δείχνει ερωτήσεις προσαρμοσμένες στο playbook της αποστολής.

---

## [0.15.5-beta] — 2026-06-30

### Feature — Multi-agency authority types and mission terminology

- Προστέθηκε superadmin-owned τύπος φορέα: Δήμος, Πολιτική Προστασία, Πυροσβεστική ή Λιμενικό.
- Η ονομασία/σύντομη ονομασία φορέα μεταφέρθηκε από τις Ρυθμίσεις Δήμου στη διαχείριση Φορέων του superadmin.
- Οι βασικές λίστες, φόρμες, sidebar labels, notifications και email defaults χρησιμοποιούν δυναμικά «Δράσεις» ή «Αποστολές» ανά τύπο φορέα.
- Προστέθηκαν ξεχωριστές default κατηγορίες αποστολών για Πυροσβεστική, Πολιτική Προστασία και Λιμενικό.
- Προστέθηκε migration `032_authority_types_and_mission_categories.sql` και ενημερώθηκε το base schema για fresh installs.

---

## [0.15.4-beta] — 2026-06-30

### Feature — Notification Control Center

- Προστέθηκε νέο admin menu item «Έλεγχος Ειδοποιήσεων» στο `/notification-center`.
- Προστέθηκε migration `031_notification_delivery_log.sql` με ενιαίο delivery log για email, SMS, Telegram και push.
- Το κέντρο δείχνει στατιστικά, φίλτρα ανά κανάλι/κατάσταση/ημερομηνία, παραλήπτη, μήνυμα, προσπάθειες και σφάλματα.
- Το email queue συνδέεται πλέον με delivery status: queued, sent, failed, μαζί με retry για email που δεν έφυγαν.
- Καταγράφονται νέες προσπάθειες SMS, Telegram και push, συμπεριλαμβανομένων παραλείψεων όταν λείπει push subscription ή Telegram chat.
- Προστέθηκε διαγραφή ιστορικού για όλα τα κανάλια ή μόνο in-app ειδοποιήσεις, scoped στον τρέχοντα δήμο και με επιβεβαίωση `DELETE`.

---

## [0.15.3-beta] — 2026-06-30

### Data safety — deterministic demo team emails/passwords

- Προστέθηκε migration `030_demo_team_email_scheme_and_password.sql`.
- Οι αρχηγοί ομάδων/team admins αποκτούν demo email μορφής `omada1@syndrasi.local`, `omada2@syndrasi.local` κ.λπ., με εξαίρεση τα whitelisted πραγματικά emails.
- Τα μέλη roster αποκτούν demo email ανά ομάδα και σειρά, π.χ. `omada1_1@syndrasi.local`, `omada1_2@syndrasi.local`, `omada2_1@syndrasi.local`.
- Τα login accounts αρχηγών/linked members παίρνουν password `Syndrasi2026`.
- Ενημερώθηκε το demo seed ώστε οι αρχηγοί ομάδων να χρησιμοποιούν το ίδιο scheme.

---

## [0.15.2-beta] — 2026-06-30

### Feature — Municipality email history dashboard

- Προστέθηκε νέα καρτέλα «Ιστορικό Email» στις Ρυθμίσεις Δήμου.
- Εμφανίζει στατιστικά αποστολών από το `mail_queue`: σύνολο, επιτυχημένα, pending, αποτυχημένα, τελευταίο 24ωρο και τελευταίες 7 ημέρες.
- Προστέθηκαν πίνακες με πρόσφατα email, ημερήσια σύνολα και συχνότερους παραλήπτες.
- Προστέθηκε κουμπί διαγραφής όλου του ιστορικού email για τον συγκεκριμένο δήμο με επιβεβαίωση `DELETE`.

---

## [0.15.1-beta] — 2026-06-30

### Data safety — sanitize demo volunteer emails

- Προστέθηκε migration `029_sanitize_demo_volunteer_emails.sql` που αντικαθιστά email εθελοντών, μελών ομάδων, team admins, team contact emails, mail queue recipients και reset emails με μη παραδοτέες demo διευθύνσεις.
- Διατηρούνται ανέπαφα μόνο τα `theodore.sfakianakis@gmail.com` και `irmaiden@gmail.com`.
- Ενημερώθηκε και το `database/seed.sql` ώστε μελλοντικά demo imports να μη φέρνουν πραγματικά/παραδοτέα volunteer emails.

---

## [0.15.0-beta] — 2026-06-30

### Feature — Fire Service incident mobilization

- Προστέθηκε άμεση «Κινητοποίηση» στα τρέχοντα συμβάντα Πυροσβεστικής.
- Από ένα συμβάν δημιουργείται ενεργό κάλεσμα έκτακτης ανάγκης με προσυμπληρωμένο τίτλο, περιγραφή, τοποθεσία και σοβαρότητα βάσει κατάστασης/κατηγορίας.
- Πριν την αποστολή εμφανίζεται οθόνη ελέγχου όπου ο admin επιλέγει συγκεκριμένες ομάδες και δυνατότητες όπως όχημα ή ιατρικό εξοπλισμό.
- Το κάλεσμα χρησιμοποιεί τον υπάρχοντα μηχανισμό mobilizations για προσωπικούς συνδέσμους απάντησης, SMS/email/push fan-out και live board επιβεβαιώσεων.
- Αν υπάρχει ήδη ενεργό ίδιο κάλεσμα, το κουμπί ανοίγει τον υπάρχοντα live πίνακα αντί να ξαναστείλει ειδοποιήσεις.
- Παραμένει διαθέσιμη η πιο ήπια ροή «Δημιουργία δράσης» για πρόχειρη επιχειρησιακή δράση.

---

## [0.14.20-beta] — 2026-06-30

### Docs — Deploy snapshot

- Προστέθηκε `docs/deploy.md` με σύνοψη των πρόσφατων features, routes, migrations, cron commands, production settings και smoke tests.
- Το αρχείο λειτουργεί ως πρακτικό handoff/deploy checklist για Story, Πυροσβεστική, Telegram, βοηθούς αρχηγού, superadmin overview και fire-risk map fallback.

---

## [0.14.19-beta] — 2026-06-30

### Revert — Remove GitHub fire-risk fetcher

- Αφαιρέθηκε το GitHub Actions workflow για λήψη/ingest του ημερήσιου χάρτη κινδύνου πυρκαγιάς, επειδή δεν ήταν αξιόπιστη λύση στο production setup.
- Αφαιρέθηκαν οι οδηγίες GitHub secrets από τις Ρυθμίσεις → Ειδοποιήσεις.
- Παραμένουν διαθέσιμα το χειροκίνητο upload χάρτη και το protected generic ingest endpoint για μελλοντικό non-GitHub fetcher.

## [0.14.17-beta] — 2026-06-30

### Hotfix — Fire-risk map manual/external fallback

- Προστέθηκε fallback upload στις Ρυθμίσεις → Ειδοποιήσεις ώστε ο admin να ανεβάζει χειροκίνητα την εικόνα του ημερήσιου χάρτη όταν η Πολιτική Προστασία μπλοκάρει το production server με `403 Forbidden`.
- Το uploaded image αναλύεται με τον ίδιο μηχανισμό χρώματος για Χανιά, Ρέθυμνο, Ηράκλειο και Λασίθι, αποθηκεύεται τοπικά και στέλνεται στο Telegram με το υπάρχον dedupe ανά δήμο/ημερομηνία.
- Προστέθηκε protected endpoint `POST /cron/fire-risk-map/ingest` για εξωτερικό fetcher που ανεβάζει multipart `map_date` + `fire_risk_map` με το ίδιο Bearer token των cron.
- Προστέθηκε δημόσιο read-only URL `/public/fire-risk-map/{YYYYMMDD}` για να περιλαμβάνεται στα Telegram μηνύματα το τοπικά αποθηκευμένο image link.

---

## [0.14.16-beta] — 2026-06-30

### Hotfix — Fire-risk map archive 403 fallback

- Αν η σελίδα αρχείου της Πολιτικής Προστασίας επιστρέψει `403 Forbidden`, το service δοκιμάζει πλέον απευθείας τα προβλέψιμα URLs εικόνας για αύριο, σήμερα, χθες και προχθές.
- Κάθε candidate εικόνα ελέγχεται με την ίδια ανάλυση χρώματος Κρήτης πριν χρησιμοποιηθεί.

---

## [0.14.15-beta] — 2026-06-30

### Hotfix — Civil Protection fetch reliability

- Το fire-risk map service χρησιμοποιεί πλέον cURL με browser-like headers και redirects πριν κάνει fallback σε `file_get_contents`, για πιο αξιόπιστη λήψη από την Πολιτική Προστασία στο production.
- Τα σφάλματα λήψης περιλαμβάνουν πλέον HTTP/cURL λεπτομέρεια ώστε να είναι διαγνώσιμα.

---

## [0.14.14-beta] — 2026-06-30

### UX — Manual fire-risk map check

- Προστέθηκε κουμπί «Έλεγχος τώρα» στις Ρυθμίσεις → Ειδοποιήσεις για χειροκίνητη εκτέλεση του χάρτη κινδύνου πυρκαγιάς Κρήτης.
- Το χειροκίνητο trigger χρησιμοποιεί το ίδιο dedupe με το cron, άρα δεν στέλνει δεύτερη ειδοποίηση για την ίδια ημερομηνία χάρτη.
- Προστέθηκε έτοιμη εντολή cron για `/cron/fire-risk-map` μέσα στις Ρυθμίσεις.

---

## [0.14.13-beta] — 2026-06-30

### Feature — Civil Protection fire-risk map Telegram alerts

- Προστέθηκε νέο cron endpoint `/cron/fire-risk-map` για έλεγχο ανά 60 λεπτά του ημερήσιου χάρτη πρόβλεψης κινδύνου πυρκαγιάς της Πολιτικής Προστασίας.
- Προστέθηκε service που βρίσκει την τελευταία εικόνα χάρτη, αναγνωρίζει το επίπεδο κινδύνου για Χανιά, Ρέθυμνο, Ηράκλειο και Λασίθι από το χρώμα της Κρήτης, και στέλνει κείμενο με link στην εικόνα.
- Προστέθηκε Telegram-only toggle στις Ρυθμίσεις για «Χάρτης κινδύνου πυρκαγιάς Κρήτης».
- Οι ειδοποιήσεις αποστέλλονται και στο command chat και στο κοινό chat ομάδων/εθελοντών, με dedupe όταν τα δύο chats είναι ίδια.
- Προστέθηκε migration `028_fire_risk_map_notifications.sql` ώστε να στέλνεται μόνο μία ειδοποίηση ανά δήμο και ημερομηνία χάρτη.

---

## [0.14.12-beta] — 2026-06-29

### Feature — Telegram alerts for Fire Service incidents in Crete

- Προστέθηκε Telegram-only toggle στις Ρυθμίσεις για «Συμβάντα Πυροσβεστικής Κρήτης».
- Το sync της Πυροσβεστικής στέλνει ένα Telegram μήνυμα ανά νέο συμβάν της Περιφέρειας Κρήτης με κατάσταση `ΣΕ ΕΞΕΛΙΞΗ` ή `ΜΕΡΙΚΟΣ ΕΛΕΓΧΟΣ`.
- Στέλνει ξανά μόνο όταν αλλάξει σχετική κατάσταση συμβάντος, όχι σε κάθε cron/manual refresh.
- Οι ειδοποιήσεις αποστέλλονται και στο command chat και στο κοινό chat ομάδων/εθελοντών, με dedupe όταν τα δύο chats είναι ίδια.
- Προστέθηκε migration `027_fire_service_telegram_notifications.sql` για per-municipality dedupe ανά συμβάν και κατάσταση.

---

## [0.14.11-beta] — 2026-06-29

### Hotfix — Telegram on completed archive flow

- Διορθώθηκε το πραγματικό κουμπί «Ολοκλήρωση» της κλειστής δράσης, το οποίο περνά από `/events/{id}/archive`, ώστε να καλεί τις `event_completed` ειδοποιήσεις και το Telegram.

---

## [0.14.10-beta] — 2026-06-29

### Feature — Shared Telegram group for teams

- Προστέθηκε πεδίο «Κοινό Chat ID ομάδων / εθελοντών» στις ρυθμίσεις Telegram, ώστε ένας δήμος να μπορεί να χρησιμοποιεί ένα ενιαίο Telegram group για όλες τις εθελοντικές ομάδες και τους admins.
- Τα team-facing Telegram notifications χρησιμοποιούν πρώτα το ειδικό Chat ID ομάδας, αλλιώς κάνουν fallback στο κοινό Chat ID ομάδων.
- Προστέθηκε dedupe ανά request ώστε broadcast ειδοποιήσεις προς πολλές ομάδες να μην εμφανίζονται πολλές φορές στο ίδιο κοινό Telegram group.
- Προστέθηκαν ξεχωριστά test buttons για Command group και κοινό group ομάδων.
- Διορθώθηκαν οι οδηγίες ώστε να εξηγούν ότι μπορεί να χρησιμοποιηθεί το ίδιο `chat.id` και στα δύο πεδία όταν υπάρχει ένα ενιαίο group.

---

## [0.14.9-beta] — 2026-06-29

### Fix — Notification channel settings coverage

- Οι επιχειρησιακές ειδοποιήσεις στις Ρυθμίσεις έχουν πλέον πλήρη επιλογή Email/SMS/Email+SMS/Καμία, εκτός από το ξεχωριστό Telegram toggle.
- Τα operational flows για αιτήματα φωτογραφίας/βίντεο/GPS, uploads, μηνύματα επιχειρήσεων, GPS received, team silent, shortage update και SOS acknowledgment σέβονται πλέον τα αποθηκευμένα κανάλια Email/SMS/Telegram.
- Τα operational Email/SMS defaults είναι πλέον `off` όταν δεν υπάρχει explicit ρύθμιση, ώστε να μη σταλούν μαζικά email/SMS από παλιές εγκαταστάσεις χωρίς επιλογή admin.
- Διορθώθηκε mapping ώστε η εντολή μετακίνησης να γράφεται ως `ops_geo` αντί για `ops_incident`.

---

## [0.14.8-beta] — 2026-06-29

### Hotfix — Telegram completion notifications

- Διορθώθηκε η ολοκλήρωση δράσης ώστε να καλεί κανονικά τις ειδοποιήσεις `event_completed`.
- Όταν είναι ενεργό το Telegram για «Ολοκλήρωση δράσης», αποστέλλεται πλέον και σύνοψη στο command group του δήμου, πέρα από τις ειδοποιήσεις προς τις εγκεκριμένες ομάδες.

---

## [0.14.7-beta] — 2026-06-29

### Docs — Telegram getUpdates setup

- Βελτιώθηκε η οδηγία για το Telegram `getUpdates` ώστε να δείχνει παράδειγμα πραγματικής μορφής Bot Token και να ξεκαθαρίζει ότι δεν μπαίνουν τα σύμβολα `<` και `>`.

---

## [0.14.6-beta] — 2026-06-29

### Hotfix — Telegram settings production fallback

- Διορθώθηκε πιθανό production crash στη σελίδα Ρυθμίσεων όταν το νέο `config/telegram.php` δεν υπάρχει στο live επειδή ο self-updater διατηρεί το υπάρχον `config/`.
- Το `TelegramService` έχει πλέον εσωτερικά fallback defaults και συνεχίζει να λειτουργεί με env variables ή με τις ρυθμίσεις δήμου από τη βάση.

---

## [0.14.5-beta] — 2026-06-29

### Feature — Superadmin πλήρης εικόνα ομάδων και εθελοντών

- Προστέθηκε νέα σελίδα superadmin “Ομάδες & Εθελοντές” με συνολική εικόνα όλων των εθελοντικών/διασωστικών ομάδων της πλατφόρμας.
- Ο superadmin βλέπει όλες τις ομάδες ανά δήμο με υπεύθυνο, επικοινωνία, δυνατότητες, Telegram ένδειξη, πλήθος μελών, ενεργά μέλη, βοηθούς αρχηγού και login admins.
- Προστέθηκε πλήρης πίνακας εθελοντών με όλα τα στοιχεία roster που περνά ο αρχηγός: επικοινωνία, διεύθυνση, ρόλος, ΑΜ Πολιτικής Προστασίας, ημερομηνία γέννησης, ΑΔΤ, ΑΜΚΑ, ομάδα αίματος, δίπλωμα, πιστοποιήσεις, σημειώσεις και linked login status.
- Προστέθηκαν φίλτρα για δήμο, ομάδα, κατάσταση και αναζήτηση.

---

## [0.14.4-beta] — 2026-06-29

### UX — Telegram setup guidance

- Προστέθηκαν αναλυτικές οδηγίες στο tab Telegram για BotFather, προσθήκη bot σε group/channel, εύρεση `chat_id` με `getUpdates`, test αποστολής και ρύθμιση group ανά εθελοντική ομάδα.
- Προστέθηκαν επίσημα links για Telegram Bots tutorial, `sendMessage` και `getUpdates`.

---

## [0.14.3-beta] — 2026-06-29

### Feature — Telegram notification channel

- Προστέθηκε νέο Telegram Bot integration με per-municipality Bot Token, command chat ID και δοκιμαστικό μήνυμα.
- Προστέθηκε `telegram_chat_id` στις εθελοντικές ομάδες για αποστολή ειδοποιήσεων σε group/channel ομάδας.
- Οι ρυθμίσεις ειδοποιήσεων υποστηρίζουν ανεξάρτητο Telegram switch ανά τύπο, δίπλα στα υπάρχοντα Email/SMS κανάλια.
- Προστέθηκε Telegram αποστολή για επιχειρησιακές ειδοποιήσεις όπως αιτήματα GPS/φωτό/βίντεο, λήψη υλικού, σίγη ομάδας, μηνύματα και ενημερώσεις ελλείψεων.
- SOS, περιστατικό και εντολή αποστέλλονται forced και σε Telegram όταν υπάρχει ενεργό Telegram setup.

---

## [0.14.2-beta] — 2026-06-29

### Feature — Βοηθοί Αρχηγού ομάδας

- Προστέθηκε δυνατότητα στον αρχηγό ομάδας να ορίζει ενεργά μέλη ως “Βοηθούς Αρχηγού”.
- Οι βοηθοί αποκτούν login ως `team_admin` με ίδια επιχειρησιακά δικαιώματα ομάδας, αλλά δεν μπορούν να ορίσουν ή να αφαιρέσουν άλλους βοηθούς.
- Η πρόσκληση βοηθού δημιουργεί/συνδέει λογαριασμό χρήστη και στέλνει email reset/invite για ορισμό κωδικού.
- Προστέθηκαν προστασίες για μέλη χωρίς έγκυρο email και για email που ανήκουν ήδη σε άλλον λογαριασμό.
- Η αφαίρεση βοηθού ή η απενεργοποίηση βοηθού απενεργοποιεί και το linked login account.
- Οι διαχειριστές δήμου μπορούν να βλέπουν τους βοηθούς κάθε ομάδας και να αφαιρούν πρόσβαση σε περίπτωση ανάγκης.

---

## [0.14.1-beta] — 2026-06-29

### Docs — Fire Service incidents integration guide

- Added a full technical Markdown handoff for the “Συμβάντα Πυροσβεστικής” integration, including architecture, schema, parser strategy, cron/manual sync, UI, dashboard alert, create-action flow, safety notes, and porting checklist.

---

## [0.14.0-beta] — 2026-06-29

### Feature — Συμβάντα Πυροσβεστικής

- Προστέθηκε νέα σελίδα “Συμβάντα Πυροσβεστικής” για municipality admins.
- Η εφαρμογή τραβά τα επίσημα ενεργά συμβάντα του Πυροσβεστικού Σώματος από την iframe πηγή και τα αποθηκεύει ως 7ήμερο ιστορικό.
- Προστέθηκαν φίλτρα για Περιφέρεια, Νομό/Π.Ε., κατηγορία, κατάσταση, αναζήτηση και τρέχον snapshot/ιστορικό.
- Προστέθηκε χειροκίνητο κουμπί “Άμεση ενημέρωση” και cron endpoint `/cron/fire-service` για εκτέλεση κάθε 5 λεπτά.
- Το dashboard των διαχειριστών δήμου δείχνει κόκκινη ειδοποίηση για τρέχοντα συμβάντα στην Περιφέρεια Κρήτης.
- Από κάθε συμβάν μπορεί να δημιουργηθεί πρόχειρη δράση SynDrasi για έλεγχο και δημοσίευση από τον admin.

---

## [0.13.9-beta] — 2026-06-29

### Feature — Overview μετακινήσεων στο Story map

- Προστέθηκε κουμπί “Μετακινήσεις” στον χάρτη του Story που εμφανίζει όλες τις εντολές μετακίνησης χωρίς να χρειάζεται replay slider.
- Οι μετακινήσεις σχεδιάζονται ως dashed βέλη από το τελευταίο γνωστό στίγμα της ομάδας προς τον προορισμό.
- Κάθε μετακίνηση εμφανίζει time badge πάνω στον χάρτη με ώρα και ομάδα.
- Προστέθηκε scope φίλτρο για “Επιλεγμένες ομάδες” ή “Όλες οι ομάδες”.

---

## [0.13.8-beta] — 2026-06-29

### Feature — Επικοινωνίες πεδίου στο Story

- Προστέθηκε ξεχωριστή ενότητα “Επικοινωνίες πεδίου” στη σελίδα Παρουσίασης Δράσης.
- Οι διάλογοι Δήμου/φορέα και ομάδων εμφανίζονται ως καθαρό transcript με ώρα, ρόλο, ομάδα, τύπο μηνύματος και ACK όπου υπάρχει.
- Προστέθηκαν φίλτρα για Όλα, Δήμο/φορέα, Ομάδες, Εντολές, Ενημερώσεις, Μετακινήσεις και Μηνύματα.
- Τα email και οι τηλεφωνικοί αριθμοί μασκάρονται στα επιχειρησιακά κείμενα του Story.

---

## [0.13.7-beta] — 2026-06-29

### Feature — Βελάκια μετακίνησης ομάδων στο replay

- Τα replay events για εντολές μετάβασης κρατούν πλέον την αφετηρία από το τελευταίο γνωστό στίγμα της ομάδας πριν την εντολή.
- Στον χάρτη του απολογισμού εμφανίζεται dashed βέλος από την αφετηρία προς το σημείο προορισμού της μετακίνησης.
- Το current replay panel και τα popups εξηγούν καθαρά ότι η γραμμή δείχνει τη μετακίνηση από τελευταίο στίγμα προς νέο σημείο.

---

## [0.13.6-beta] — 2026-06-29

### Feature — Διαδραστικό replay χάρτη απολογισμού

- Προστέθηκε χρονολογικό replay στη σελίδα Παρουσίασης Δράσης με Play/Pause, reset, slider και επιλογή ταχύτητας.
- Ο χάρτης δείχνει πλέον αιτήματα GPS, απαντήσεις GPS, στίγματα, φωτογραφίες, βίντεο, εντολές, περιστατικά, ελλείψεις και SOS με σειρά χρόνου.
- Τα αιτήματα GPS συνδέονται οπτικά με την απάντηση όταν υπάρχει διαθέσιμο στίγμα, ώστε να φαίνεται καθαρά ποιος ζητήθηκε και πού απάντησε.
- Προστέθηκαν φίλτρα ανά ομάδα και ανά τύπο γεγονότος για πιο καθαρή παρουσίαση σε δημόσιο απολογισμό.

---

## [0.13.5-beta] — 2026-06-29

### Feature — Wow απολογισμός δράσης

Η σελίδα Παρουσίασης/Απολογισμού δράσης έγινε public-facing story page:

- Νέο full-screen hero με πραγματική φωτογραφία δράσης όταν υπάρχει, ισχυρό τίτλο, impact sentence και βασικούς μετρητές.
- Sticky πλοήγηση σε Σύνοψη / Χάρτη / Ομάδες / Χρονολόγιο / Υλικό.
- Νέα σύνοψη με narrative απολογισμό και impact cards.
- Μεγαλύτερος χάρτης με φίλτρα ομάδων, πιο καθαρά route markers και σημεία φωτογραφιών/βίντεο/περιστατικών.
- Κάρτες αναγνώρισης ομάδων με παρόντα άτομα, ώρες προσφοράς, άφιξη και αναχώρηση.
- Χρονολόγιο χωρισμένο σε φάσεις (έναρξη, επιχειρησιακή ροή, περιστατικά/ελλείψεις, ολοκλήρωση).
- Νέο masonry gallery με lightbox για φωτογραφίες και βίντεο.
- Καλύτερη mobile και print/PDF συμπεριφορά.

---

## [0.13.4-beta] — 2026-06-29

### Security / Performance / UX audit fixes

- Hardened self-update and backup restore ZIP extraction against path traversal, absolute paths, stream wrappers, Windows drive paths, and symbolic links.
- Added war-room JSON fallback polling when SSE stalls, with visible LIVE POLL/reconnect state for operators.
- Streamed protected operation photos through the buffered file streamer instead of direct `readfile()` output.
- Replaced `StatsService` yearly filters with indexed date ranges and added performance indexes for event lists, stats, notifications, pings, check-ins, and shortages (`023_more_perf_indexes.sql`).
- Removed correlated application-count subqueries from event list queries in favor of aggregate joins.
- Added forgot-password throttling without email enumeration (`3` requests per IP+email per 30 minutes).
- Stopped loading Leaflet and Chart.js globally on every layout page; only pages that need them request them.
- Improved field SOS UX: no native confirm dialog, two-tap inline confirmation, accessible alert banner, and distinct GPS/network/server failure messages.
- Bumped PWA cache to `syndrasi-v6`.

---

## [0.13.3-beta] — 2026-06-27

### Cleanup — Αφαίρεση προσωρινού diagnostic στο Story

Το query fix του 0.13.2 (`:eid1`/`:eid2`) επιβεβαιώθηκε. Αφαιρέθηκε το προσωρινό admin-only diagnostic try/catch από το `EventController@story` — η σελίδα Απολογισμού κάνει πλέον καθαρό render χωρίς το debug block.

---

## [0.13.2-beta] — 2026-06-27

### Fix — 500 στη σελίδα Απολογισμού (reused named param)

Το query παρουσιών στο `StoryService` χρησιμοποιούσε το named placeholder `:eid` δύο φορές, που με `PDO::ATTR_EMULATE_PREPARES=false` (real prepared statements) σκάει «Invalid parameter number» → «Σφάλμα συστήματος». Διορθώθηκε σε δύο διακριτά params (`:eid1`/`:eid2`).

- Επίσης: admin-only diagnostic στο `EventController@story` (δείχνει το ακριβές σφάλμα μόνο σε municipality_admin).

---

## [0.13.1-beta] — 2026-06-27

### Fix — Ανθεκτικότητα Story όταν λείπει η migration 021

Αν δεν έχει τρέξει ακόμα η στήλη `kept` (migration 021), η σελίδα Story έβγαζε «Σφάλμα συστήματος». Πλέον το `markKeptForEvent` και το cron cleanup αγνοούν με ασφάλεια την απουσία της στήλης (try/catch), ώστε η σελίδα να δουλεύει.

---

## [0.13.0-beta] — 2026-06-27

### Feature — Δημόσιος σύνδεσμος Απολογισμού (Φάση 4)

Κουμπί «Δημόσιος σύνδεσμος» στη σελίδα Story → δημιουργεί token και δίνει shareable URL `/public/story/{token}` που ανοίγει χωρίς login (δημόσια εκδοχή, κρυμμένα προσωπικά), με φωτό/βίντεο να σερβίρονται μέσω του token.

- Migration `022_story_token.sql` (`story_token`, `story_published_at`).
- `EventController@publishStory` + `PublicEventController@story/storyPhoto/storyVideo` (public media, range για βίντεο).
- Ολοκληρώνει το storytelling (Φάσεις 1-4).

---

## [0.12.2-beta] — 2026-06-27

### Feature — Λήψη Απολογισμού ως αυτόνομο HTML (Φάση 3)

Κουμπί «Λήψη HTML» στη σελίδα Story → κατεβάζει self-contained αρχείο: οι φωτογραφίες ενσωματώνονται **base64** (offline), τα βίντεο ως απόλυτα links. Route `/events/{id}/story/download`.

---

## [0.12.1-beta] — 2026-06-27

### Feature — Διατήρηση media του Story (Φάση 2)

Τα βίντεο μιας δράσης που έχει Απολογισμό/Story δεν διαγράφονται πια από το auto-purge των 7 ημερών.

- Migration `021_event_video_kept.sql` (στήλη `kept`).
- `EventVideo::markKeptForEvent()` — καλείται όταν ανοίγει το Story.
- `MaintenanceService::cleanup()` εξαιρεί `kept = 1`.

---

## [0.12.0-beta] — 2026-06-27

### Feature — Απολογισμός / Παρουσίαση Δράσης (storytelling)

Σε κλειστή/ολοκληρωμένη δράση, κουμπί «Παρουσίαση Δράσης» ανοίγει μια όμορφη standalone σελίδα (`/events/{id}/story`) με όλη την ιστορικότητα: σύνοψη, χάρτη με διαδρομές ομάδων, χρόνους απόκρισης ανά ομάδα (στίγμα/φωτό/βίντεο + ACK εντολών), χρονολόγιο όλων των γεγονότων, παρουσίες/ώρες, ελλείψεις και gallery. Με print/PDF και διακόπτη Εσωτερική/Δημόσια. (Φάση 1 — δεν χρειάστηκε νέα συλλογή δεδομένων.)

- `StoryService::build()` μαζεύει δεδομένα + υπολογίζει μετρικές απόκρισης (created_at→fulfilled_at/acknowledged_at).
- `EventController@story` + route + κουμπί στο `/events/{id}` + `views/events/story.php` (Leaflet + Chart.js).

---

## [0.11.8-beta] — 2026-06-27

### Fix — Live κατάσταση ελλείψεων στις «Επιχειρησιακές Ενέργειες» (team)

Στη σελίδα `/team/operations/events/{id}` η λίστα «Οι αναφορές μας για αυτή τη δράση» έμενε «Εκκρεμεί» μέχρι refresh, παρότι ο δήμος είχε επιλύσει την έλλειψη. Πλέον ανανεώνεται ζωντανά μέσω του υπάρχοντος poll (~5s). (Συμπληρώνει το ίδιο fix στο Team Live hub.)

- `commsFeed`: προστέθηκαν op-friendly πεδία κατάστασης/χρώματος στις ελλείψεις.
- `operations.php`: η κάρτα αναφορών ξαναζωγραφίζεται σε κάθε poll (`renderShortages`).

### Fix — Play/Delete βίντεο + geo-modal χάρτης

Αφαιρέθηκε λάθος έλεγχος `typeof bootstrap === 'undefined'` (το bootstrap φορτώνει στο footer) που εμπόδιζε τα κουμπιά play/delete βίντεο και την αρχικοποίηση του χάρτη στο geo-modal.

---

## [0.11.7-beta] — 2026-06-27

### Fix — Live ενημέρωση ελλείψεων στο Team Live

Όταν ο δήμος επέλυε/λάμβανε μια έλλειψη (πρόβλημα), το Team Live το έδειχνε ακόμη «Ανοιχτό/εκκρεμεί» μέχρι χειροκίνητο refresh. Πλέον η κατάσταση των αναφορών ανανεώνεται **ζωντανά** μέσω του poll (~5s).

- `commsFeed` επιστρέφει τις ελλείψεις της ομάδας με έτοιμα labels/χρώματα κατάστασης.
- Το `views/team/live.php` ξαναζωγραφίζει τη λίστα αναφορών σε κάθε poll (`renderShortages`).

---

## [0.11.6-beta] — 2026-06-27

### UX/Feature — Βίντεο σε modal + διαγραφή

- Το βίντεο ομάδας ανοίγει πλέον σε **popup modal** (ξεχωριστός player) — δεν διακόπτεται πια από το auto-refresh της σελίδας. Στη λίστα φαίνεται ελαφριά κάρτα με κουμπί ▶ (χωρίς live video element που ξαναφόρτωνε σε κάθε poll).
- **Διαγραφή βίντεο** από admin (κουμπί στην κάρτα και στο modal) → route `POST /operations/videos/{id}/delete` (διαγράφει αρχείο + εγγραφή).

---

## [0.11.5-beta] — 2026-06-27

### Feature — Popup χάρτη για εντολή σημείου (μετάβαση/περιστατικό/σημείο)

Στην Επιχειρησιακή Σελίδα, το κουμπί «Νέα εντολή στον χάρτη» ανοίγει popup με χάρτη όπου ορίζεις σημείο είτε με **κλικ για πινέζα** είτε **γράφοντας διεύθυνση** (αναζήτηση OpenStreetMap/Nominatim, προτεραιότητα Ελλάδα). Μέσα στο popup διαλέγεις τύπο, ομάδα-παραλήπτη και σχόλιο, και με το πράσινο **«Σημείο»** φεύγει η εντολή.

- Πλήρης εντολή μέσα στο popup (χάρτης + διεύθυνση + τύπος + ομάδα + σχόλιο + αποστολή).
- Δωρεάν geocoding (Nominatim, χωρίς API key), bias σε `countrycodes=gr`.
- Αντικατέστησε το παλιό inline «Χάρτη/Σημείο» (ίδιο endpoint `/message` με `point_kind`).

---

## [0.11.4-beta] — 2026-06-27

### Fix — Αίτημα βίντεο στο Team Live (Mobile Action Hub)

Το αίτημα βίντεο εμφανιζόταν μόνο στο field hub (`/f/{token}`), όχι στο `/team/live` που χρησιμοποιεί ο υπεύθυνος ομάδας από κινητό — γι' αυτό «δεν ερχόταν τίποτα». Προστέθηκε κάρτα «Αποστολή Βίντεο» στο team live, με banner όταν ο δήμος ζητά βίντεο (instructions), εγγραφή/επιλογή από κινητό, geotag και λεζάντα.

- **commsFeed:** επιστρέφει `video_request` (poll κάθε ~5s, ανάβει το banner).
- **TeamPortalController::uploadVideo** + route `POST /team/operations/events/{id}/video`.
- **live.php:** κάρτα βίντεο + JS (toggle/submit/renderVideoRequest).

---

## [0.11.3-beta] — 2026-06-27

### UI — Σαφέστερες ονομασίες στη ροή κλεισίματος

- «Αρχειοθέτηση» → **«Απολογισμός-Στοιχεία»** (φόρμα καταχώρησης πραγματικών στοιχείων).
- «Οριστική Αρχειοθέτηση» → **«Ολοκλήρωση»** (οριστικοποίηση → Ολοκληρωμένες).

---

## [0.11.2-beta] — 2026-06-27

### Fix — Ροή αρχειοθέτησης κλειστών δράσεων

- **500 στο reconcile:** το `VolunteerParticipation` τραβούσε ανύπαρκτη στήλη `tm.specialty` — διορθώθηκε σε `tm.role_in_team` (η σελίδα «Αρχειοθέτηση» άνοιγε με «Σφάλμα συστήματος»).
- **Εξαφάνιση δράσης:** η «Οριστική Αρχειοθέτηση» έβαζε status `cancelled` (που δεν φαίνεται πουθενά)· πλέον βάζει `completed`, οπότε η δράση μετακινείται σωστά στις **Ολοκληρωμένες**.
- **Αναγνωσιμότητα:** το κουμπί «Αρχειοθέτηση» έγινε `btn-warning` (πορτοκαλί με λευκό κείμενο) αντί για το δυσανάγνωστο προηγούμενο.

---

## [0.11.1-beta] — 2026-06-27

### UI — Μετρητές σε όλα τα tabs δράσεων

Τα tabs «Ενεργές / Κλειστές / Ολοκληρωμένες» στη λίστα δράσεων δείχνουν πλέον τον αριθμό τους και τα τρία, σε κάθε σελίδα — χωρίς να χρειάζεται να μπεις σε καθένα.

- **Model:** `Event::statusCounts()` — ένα query μετράει τις τρεις κατηγορίες.
- **Controller/Views:** `tabCounts` περνά στις σελίδες· badges σε όλα τα tabs (Ενεργές/Κλειστές/Ολοκληρωμένες).

---

## [0.11.0-beta] — 2026-06-27

### Feature — Προαιρετικό PIN στον σύνδεσμο πεδίου

Ο σύνδεσμος του Mission Υπευθύνου (`/f/{token}`) μπορεί πλέον να προστατεύεται με 4ψήφιο PIN. Όταν υπάρχει PIN, η πρώτη επίσκεψη σε κάθε συσκευή ζητά τον κωδικό· μετά τη σωστή εισαγωγή η συσκευή «θυμάται» (signed cookie 180 ημερών) και δεν ξαναρωτά. Πλήρως συμβατό προς τα πίσω: χωρίς PIN, καμία αλλαγή στη ροή.

- **DB:** migration `020_field_pin.sql` — στήλη `event_applications.field_pin` (NULL = χωρίς gate).
- **Model:** `EventApplication::ensureFieldPin()` (παράγει 4ψήφιο PIN όταν χρειαστεί).
- **FieldController:** PIN gate στο `hub()` (cookie remember-device), handler `pin()` (επαλήθευση + cookie), helpers.
- **View:** `views/field/pin.php` — οθόνη εισαγωγής PIN (standalone, mobile).
- **Sharing:** το «Αποστολή με SMS» στέλνει πλέον σύνδεσμο **+ PIN**· το PIN εμφανίζεται και στον πίνακα συνδέσμου πεδίου της δράσης.
- **Route:** `POST /f/{token}/pin`.
- **Regenerate:** κουμπί «Νέο PIN» στον πίνακα συνδέσμου — περιστρέφει το PIN και ακυρώνει αυτόματα όλες τις θυμημένες συσκευές (route `POST /team/applications/{id}/regenerate-pin`).

> Σημείωση: το PIN ενεργοποιείται τη στιγμή που ο υπεύθυνος ομάδας ανοίγει/στέλνει τον σύνδεσμο (όπου παράγεται). Επικοινωνήστε το PIN μαζί με τον σύνδεσμο.

---

## [0.10.0-beta] — 2026-06-26

### Feature — Σύντομο βίντεο από το πεδίο (live ή gallery)

Ο χειριστής μπορεί να ζητήσει από μία ομάδα — ή με broadcast από όλες — ένα σύντομο βίντεο (προεπιλογή 40\'\'), με προαιρετικές οδηγίες. Ο Υπεύθυνος Ομάδας λαμβάνει Web Push, ανοίγει το token link του και είτε τραβάει ζωντανά μέσα από την εφαρμογή (με όριο χρόνου που επιβάλλεται αυτόματα, 720p) είτε επιλέγει βίντεο από το gallery. Το κλιπ ανεβαίνει geotagged και εμφανίζεται στην Επιχειρησιακή Σελίδα με player, ώρα και αντίστροφη μέτρηση διαγραφής.

- **DB:** migration `019_event_videos.sql` — πίνακες `video_requests` (με `batch_id` για broadcast) και `event_videos`.
- **Models:** `VideoRequest`, `EventVideo`.
- **Routes:** `POST /operations/events/{id}/request-video`, `GET /operations/videos/{id}`, `GET /operations/videos/{id}/download`, `POST /f/{token}/video`.
- **OperationController:** `requestVideo` (single/broadcast), `serveVideo` (inline streaming με HTTP Range), `downloadVideo` (αρχειοθέτηση).
- **FieldController:** `video()` upload (mime whitelist mp4/webm/mov, όριο 60MB, geotag, λεζάντα).
- **NotificationService:** `videoRequested`, `videoUploaded` (in-app + Web Push).
- **UI:** κάρτα λήψης βίντεο στο field hub (live + gallery, preview, progress) και κουμπί «Ζήτησε βίντεο» + panel «Βίντεο πεδίου» στην Επιχειρησιακή Σελίδα.
- **Διατήρηση:** auto-purge βίντεο > 7 ημερών μέσω `MaintenanceService::cleanup()` (file + row).

> Σημείωση deploy: χρειάζονται αυξημένα όρια PHP στον production server (`upload_max_filesize`/`post_max_size` ≥ 64M/80M). Web Push σε iPhone απαιτεί iOS 16.4+ και «Add to Home Screen».

---

## [0.9.52-beta] — 2026-06-23

### Fix — Διαδρομές υπενθύμισης/ακύρωσης και σωστό κλείσιμο δράσης

- Προστέθηκαν οι missing handlers `EventController::remind()` και `EventController::cancel()`, ώστε τα κουμπιά υπενθύμισης και ακύρωσης να μην οδηγούν σε router crash.
- Το κουμπί «Κλείσιμο δράσης» οδηγεί πλέον στο `/close`, ώστε να περνά από τη ροή `closed → debrief/reconcile → completed`.
- Το base `schema.sql` περιλαμβάνει πλέον το status `closed` στο enum των δράσεων, για συμβατότητα με fresh installs.

---

## [0.9.51-beta] — 2026-06-19

### Fix — Διόρθωση timezone MySQL/PHP (UTC vs Αθήνα)

**Πρόβλημα:** Η σύνδεση PDO δεν έκανε `SET time_zone`, οπότε η MySQL αποθήκευε/επέστρεφε ώρες UTC ενώ η PHP τις ερμήνευε ως ώρα Αθήνας (+3). Αποτέλεσμα:
- Ώρες check-in στη λίστα «Παρόντες» έδειχναν 3 ώρες πίσω (π.χ. 06:47 αντί 09:47)
- Ο μετρητής «Xλ» ήταν λανθασμένος κατά +180 λεπτά

**Λύση:** Μία γραμμή στο `db()`: `SET time_zone = '+03:00'` (δυναμικά μέσω `DateTime::format('P')` για σωστό DST χειμώνα/καλοκαίρι). Πλέον MySQL session = PHP timezone = Αθήνα.

---

## [0.9.50-beta] — 2026-06-19

### Fix — Σωστή ένδειξη χρόνου ομάδας στο επιχειρησιακό κέντρο

**Πρόβλημα:** Ο μετρητής "Xλ" στον πίνακα ομάδων έδειχνε λεπτά από το τελευταίο **GPS ping**, όχι από το check-in. Ομάδα που μόλις δήλωσε παρούσα εμφανιζόταν με 180+ λεπτά (κόκκινο) αντί για ~0 (πράσινο).

**Λύση:** Ο δείκτης χρησιμοποιεί πλέον την **πιο πρόσφατη δραστηριότητα** — είτε GPS ping είτε check-in, όποιο είναι νεότερο. Το ίδιο και η λογική silent-team alert.

---

## [0.9.49-beta] — 2026-06-19

### Fix — Αυτόματη δημιουργία cron_secret · βελτίωση Cron Jobs tab

**Πρόβλημα:** Το async loopback dispatch (αποστολή emails χωρίς cron) δεν δούλευε αν δεν υπήρχε `cron_secret` στη βάση, κάτι που δεν ρυθμίζεται πουθενά αυτόματα.

**Λύση:**
- `dispatchAsync()` τώρα **auto-generates** το `cron_secret` αν δεν υπάρχει (πρώτη αποστολή email) — χωρίς καμία χειροκίνητη ρύθμιση
- Το **Cron Jobs tab** στο `/admin/settings` εμφανίζει τώρα:
  - Το τρέχον `cron_secret` με κουμπί αντιγραφής
  - Κουμπί "Αναγέννηση κλειδιού"
  - Έτοιμη εντολή curl για το `/cron/mail-queue` (με το κλειδί συμπληρωμένο)
  - Έτοιμη εντολή curl για το `/cron/cleanup`

---

## [0.9.48-beta] — 2026-06-19

### Fix — Αριθμός εθελοντών επαναφερόταν κατά το live refresh

Στο Επιχειρησιακό Κέντρο, ο admin δεν μπορούσε να αλλάξει τον αριθμό ατόμων στο input "Εγκρίνω Χ άτομα" γιατί κάθε ~9 δευτερόλεπτα το SSE/poll ξανά-render την κάρτα Αιτήσεων και επανέφερε την τιμή στο `offered_people` της ομάδας.

Fix: `renderPendingApps()` τώρα αποθηκεύει τις τιμές που έχει ήδη επεξεργαστεί ο admin πριν το `innerHTML` rebuild και τις επαναφέρει αμέσως μετά.

---

## [0.9.47-beta] — 2026-06-19

### Fix — Email dispatch: cron job no longer required

Improved `MailService::flushQueue()` with a **fire-and-forget loopback HTTP dispatch** so emails are sent asynchronously on any server without needing a cron job.

**How it works (shutdown handler, runs after the user's redirect is issued):**
1. **PHP-FPM** (`fastcgi_finish_request` available): browser gets the redirect instantly; emails sent by the same worker in the background.
2. **Apache/mod_php** (most shared hosting, incl. 1stop.gr): opens a TCP socket to the current server, writes `GET /cron/mail-queue` with the cron secret, and closes the socket immediately **without reading the response**. The server spawns a separate PHP worker to process and send the emails. The original request exits right away.
3. **Loopback blocked** (rare): falls back to synchronous send — emails block the response (old behaviour) but still get delivered.

**No cron job required.** The `/cron/mail-queue` route and `CronController::processMailQueue()` remain available for environments that want cron for extra reliability (guaranteed delivery even if no incoming HTTP traffic).

**Prerequisite:** a `cron_secret` must be set in app_settings (Ρυθμίσεις → Συντήρηση). If missing, falls back to synchronous.

---

## [0.9.46-beta] — 2026-06-19

### Fix — Email delays (10-15 s) on event publish / team participation

`sendDeferred()` previously relied on `fastcgi_finish_request()` to send emails after the HTTP response — but this only works on PHP-FPM. On Apache/mod_php hosts (including the production server on 1stop.gr) every SMTP connection blocked the user's redirect for ~2-3 s per email. With member-assignment notifications, selecting 5 members produced up to 15 s of waiting.

**Solution: DB-backed mail queue.** `sendDeferred()` now does an instant `INSERT INTO mail_queue` (~0 ms) and returns immediately. A new cron endpoint `/cron/mail-queue` sends the queued messages asynchronously (up to 50 per run, retries 3 times on failure).

**To activate on production:**
1. Run migration `database/migrations/018_mail_queue.sql` on the production database
2. Add a cron job: `* * * * * curl -s -H "Authorization: Bearer <cron_secret>" "https://yoursite/cron/mail-queue" > /dev/null`

Until the migration runs, the old shutdown-function approach stays as a fallback (no breakage).

Technical:
- New table `mail_queue` (id, municipality_id, to_email, to_name, subject, body, attempts, sent_at, error_msg)
- `MailService::sendDeferred()` — INSERT to DB, catches exception and falls back to array
- `MailService::flushQueue()` — now only the fallback path (kept for backward compat)
- `CronController::processMailQueue()` — new `GET /cron/mail-queue` endpoint
- Migration: `database/migrations/018_mail_queue.sql`

---

## [0.9.45-beta] — 2026-06-19

### Feature — Inline team approval from operational command centre

Municipality admins can now approve or reject pending team applications **directly from the operational event page** (`/operations/events/{id}`) while the event is already active — no need to navigate to the applications review screen.

A full-width amber card appears automatically at the top of the command centre grid whenever there are pending applications. For each pending request it shows:
- Team name and number of people offered
- Optional comment left by the team
- An editable "Εγκρίνω X άτομα" number input (pre-filled with the team's offer)
- **Έγκριση** (green) / **Απόρριψη** (red) buttons

Clicking Έγκριση immediately approves the application, sends the standard approval notification to the team, logs an audit entry, and refreshes the board. Clicking Απόρριψη shows a confirmation dialog before rejecting. The card hides itself when no pending applications remain.

Technical:
- Two new routes: `POST /operations/events/{id}/applications/{appId}/approve` and `.../reject` → `OperationController@approveApplication/rejectApplication`
- Restricted to `municipality_admin` role only (event operators cannot approve)
- `pending_apps` array added to both the SSE snapshot (`buildStreamSnapshot`) and the manual poll (`status`) so the panel stays live

---

## [0.9.44-beta] — 2026-06-19

### Fix — Bootstrap modal greyed-out in danger zone

The "Διαγραφή όλων των δεδομένων…" modal was showing as an inaccessible grey overlay. Root cause: Bootstrap 5 does not teleport modals to `<body>` — the modal lived inside `<main>`, while the backdrop is always appended to `<body>`. This placed them in different stacking contexts, making the backdrop render above the dialog. Fixed by moving the modal to `document.body` on `DOMContentLoaded` via an inline script, so both the modal (z-index 1055) and backdrop (z-index 1050) share the same stacking context.

---

## [0.9.43-beta] — 2026-06-19

### Feature — High-priority notification system for emergency operations

Complete overhaul of the notification pipeline for civil protection / fire department scenarios where no alert can be missed:

**Web Push urgency (RFC 8030):** `WebPushService::send()` now accepts an `$urgency` parameter (`very-high | high | normal | low`) and sets the `Urgency:` HTTP header. `very-high` bypasses Android Doze mode so the device wakes even when battery-optimised. SOS alerts and geo-incident orders now send at `very-high`; GPS-arrival and silent-team alerts at `high`.

**GPS arrival notification:** When command staff requests a team's GPS and the team submits it, command now receives an immediate in-app + push notification with a Google Maps link. Implemented via `NotificationService::gpsArrived()` called from `FieldController::location()` when a pending GPS request is fulfilled.

**Silent team alert:** `OperationController::checkSilentTeams()` runs on every SSE snapshot. If a team has not pinged for more than the configured threshold (default 20 min), command staff receive a warning notification. A dedup check on the `notifications` table prevents re-alerting within the same silence window. Threshold is now a per-municipality setting (`ops_silent_team_minutes`, 0 = disabled) under Settings → Municipality → Notifications.

**Field hub (Mission Commander) audio + banner:** When a new order or message arrives on the `/f/{token}` hub page, the browser plays a short beep via Web Audio API and vibrates (mobile). An amber fixed-position flash banner shows the new message text for 6 seconds. First-load seeding avoids false alerts on page open.

---

## [0.9.42-beta] — 2026-06-19

### Security — photo serving MIME whitelist

`servePhoto()` now validates the detected MIME type against an explicit allowlist (`image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/heic`, `image/heif`) before streaming the file. Any file whose MIME type doesn't match returns `HTTP 415 Unsupported Media Type` instead of being served blindly.

### Fix — `autoCloseExpired` throttle to once per 60 seconds

The auto-close check was firing a `UPDATE events …` query on every SSE reconnect (~every 3 s) and every war-room page load. It now uses a session timestamp to skip the DB write if it was already executed within the last 60 seconds per municipality — reducing unnecessary writes by ~95% during active monitoring sessions.

---

## [0.9.41-beta] — 2026-06-18

### Feature — Automatic debrief flow on event close

**Admin side:** When a municipality admin closes an event from the operational page, the system now:
1. Sends an in-app notification (+ email if configured) to every approved team: "Debrief δράσης: … — Συμπληρώστε το Post-Event Debrief".
2. Redirects the admin directly to `/events/{id}/debriefs` instead of back to operations, so they can immediately fill in the Municipality After-Action report.

**Team side:** When a team admin visits `/team/events/{id}` for a closed or completed event they participated in, a prominent indigo call-to-action card appears at the top of the page prompting them to fill the debrief. Once submitted, the card turns into a success banner with a link to edit the submission.

---

## [0.9.40-beta] — 2026-06-18

### Feature — Silent team warning on operational map

When a team's last GPS ping is 20+ minutes old, their map marker now visually signals silence: the dot turns **grey** (last known position, not current), a red **!** badge appears beside it, and the marker pulses with a red glow animation. Clicking the marker shows "⚠ Σε σίγη — τελευταίο στίγμα X λεπτά πριν" in the popup instead of the normal age text. Thresholds unchanged: < 5 min = green, 5–20 min = yellow, 20+ min = grey + alarm badge.

### Feature — Audio SOS alert

When a new SOS arrives via the SSE stream or status poll, the browser plays a triple-beep (880 Hz, 3 × 270 ms, 380 ms gap) using the Web Audio API — no audio file needed. The beep is suppressed on the initial page load so it doesn't fire when the operator first opens the page. Subsequent SOS events while the page is open trigger the sound immediately.

---

## [0.9.39-beta] — 2026-06-18

### Feature — SuperAdmin danger zone: erase all operational data

New **"Επικίνδυνη Ζώνη"** tab in SuperAdmin → Platform Settings. A red-bordered card lists exactly what will be deleted and what stays. Clicking the button opens a Bootstrap modal that requires typing `ΔΙΑΓΡΑΦΗ` before the form can be submitted — prevents accidental wipes.

On confirm, 23 operational tables are truncated (`FOREIGN_KEY_CHECKS` disabled for the duration): events, applications, shifts, checkins, notes, shortages, SOS, GPS pings, geo-requests, photos, messages, room messages, reports, debriefs, participations, mobilisations, notifications, audit logs, password reset tokens. **Users, teams, team members, municipalities, categories, templates and platform settings are untouched.**

---

## [0.9.38-beta] — 2026-06-18

### Fixed — map auto-pan on SSE update

`fitBounds` was called on every SSE update (~3 s), snapping the map back to the teams' current GPS coordinates whenever the user had manually panned (e.g. to pick a geo-order target in Heraklion while teams were elsewhere). Map now auto-fits **once** on the first ping update, then never again. Picking mode (`Χάρτη` button) also disables auto-fit immediately so the SSE cannot interrupt mid-selection.

## [0.9.37-beta] — 2026-06-18

### Feature — Geo-order markers on operational map

When command sends a move / incident / poi order with a GPS point, the target now appears on the map as a distinct shape (never a circle):

- **Μετάβαση** → orange downward triangle ▼
- **Περιστατικό** → red rotated diamond ◆
- **Σημείο ενδιαφέροντος** → purple rounded square ★

A dashed coloured line connects the team's current GPS dot to the target. The marker and line disappear automatically once the team's next ping arrives within 100 m (no manual "arrived" step). Broadcast and targeted orders both supported; newest order per team wins. Works across SSE stream, status poll, and manual map refresh.

### Feature — Collapsible symbol legend

A small **"ⓘ Υπόμνημα συμβόλων χάρτη"** toggle sits below the operational map — collapsed by default, zero JS, native `<details>`/`<summary>`. Expands to show all 5 map symbols (GPS circle, move triangle, incident diamond, POI star, dashed connector) with their exact colours.

## [0.9.35-beta] — 2026-06-18

### Security
- `AuthController`: rate-limit keys upgraded from MD5 → SHA256; password-reset tokens now stored as SHA256 hash (DB compromise cannot replay tokens)
- `AdminController`: `stopImpersonation()` now enforces `requireLogin()` and calls `session_regenerate_id(true)`
- `CronController`: cron secret moved from `?secret=` query-string (logged in web server access logs) to `Authorization: Bearer` header only
- `storage/.htaccess`: new file — blocks direct HTTP access to the storage directory

### Fixed
- `maps.js`: critical live-map bug — `data.success` → `data.ok` (operational map was never updating)
- `ApplicationController` / `EventController`: notification calls wrapped in `try/catch(Throwable)` — a mail failure no longer rolls back the approve/reject action
- `ApplicationController` bulk-approve: replaced N+1 `EventApplication::find()` per loop with a single batch `SELECT … IN (…)` query
- `public/index.php`: global exception handler, maintenance-mode 503 check, security headers (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`)
- `UpdateService`: writes `storage/maintenance.lock` before update; `register_shutdown_function` removes it on finish
- `views/events/show.php`: "Κλείσιμο δράσης" button uses `btn-danger`; section label translated to "Απολογισμός Δράσης"; cancel button uses `btn-outline-secondary`; `btn-xs` replaced with `btn-sm py-0 px-1`
- `views/dashboard/municipality.php`: duplicate chart.js script tag removed

### Performance
- `DashboardController`: 13+ DB queries → ~6 using `GROUP BY status` consolidation (monthly trend, per-status year counts, team counts, approval rate)
- `MunicipalitySetting::all()`: per-municipality static cache; `setMany()` wrapped in DB transaction
- `database/migrations/017_perf_indexes.sql`: composite indexes on `location_pings(event_id, created_at)` and `notifications(user_id, is_read)`

### UI / Accessibility
- `views/layouts/flash.php`: Bootstrap Icons per alert type; non-danger alerts auto-dismiss after 5 s
- Toast zone (`footer.php`): `aria-live="polite"`, switches to `assertive` for SOS toasts
- Impersonation banner (`header.php`): `role="alert" aria-live="assertive"`
- Sidebar logo `alt` now shows dynamic org name from settings
- `war-room.php` + `analytics/index.php`: inline `json_encode()` uses `JSON_HEX_TAG | JSON_HEX_AMP` (CSP-safe)
- `service-worker.js`: local assets (`/assets/js/`, `/assets/css/`) switched to network-first → updated CSS/JS loads immediately after deploy without version strings
- `field/hub.php`: Wake Lock API keeps screen on; offline banner appears after 2 consecutive poll failures
- `public/assets/css/app.css`: `.btn-xs` utility, `.no-hover:hover`, `@media (prefers-reduced-motion: reduce)` block

## [0.9.29-beta] — 2026-06-18

### Feature — Επιχειρησιακό Κέντρο: Live Photo Wall

Νέα κάρτα «Φωτογραφίες Live» στη δεξιά στήλη (μεταξύ Πίνακα Ομάδων και Ελλείψεων):

- Grid 3×3 με τις τελευταίες 9 φωτογραφίες (newest first), κρυμμένο αν δεν υπάρχουν φωτό
- Overlay σε κάθε thumbnail: όνομα ομάδας, ώρα αποστολής, λεζάντα (αν υπάρχει)
- Κλικ → άνοιγμα στο υπάρχον fullscreen modal (`#photoModal`)
- Νέες φωτό εμφανίζονται με scale-in animation (`.wall-new`)
- Χρησιμοποιεί τον υπάρχοντα delegated click handler (`.photo-thumb`)
- Ενημερώνεται μαζί με `updatePhotos()` από το SSE/polling

## [0.9.28-beta] — 2026-06-18

### Feature — Επιχειρησιακό Κέντρο: Live Teams Board

Νέο panel «Πίνακας Ομάδων» στη δεξιά στήλη του Επιχειρησιακού Κέντρου:

- **3 summary counters** πάνω: Παρόντες / Αδήλωτοι / Εγκεκριμένοι (από server stats)
- **Compact rows** για κάθε ομάδα: χρωματιστή κουκκίδα status, όνομα, άτομα (παρόντες/εγκεκριμένοι), ηλικία τελευταίου GPS ping
- **Αυτόματο sorting**: SOS πρώτοι (με badge + animation), μετά παρόντες (πλήρεις → μερικοί), μετά αδήλωτοι, τελευταίοι αποχωρήσαντες
- **Ping age color**: πράσινο <5λ / πορτοκαλί <20λ / κόκκινο >20λ
- **SOS flash**: το boardList κάνει flash όταν υπάρχει ενεργό SOS
- Ενημερώνεται από την ήδη υπάρχουσα SSE/polling ροή — χωρίς νέο endpoint

## [0.9.27-beta] — 2026-06-18

### Fixed — Team Live Hub: blank green page

**🔴 Critical:** `/team/live/{id}` εμφάνιζε κενή πράσινη σελίδα λόγω PHP fatal error.

**Bug 1 (Critical):** `get_flash()` → `flash_get()` (η `get_flash()` δεν υπάρχει) — ίδιο bug που είχε διορθωθεί στο `hub.php` (v0.9.24). Το fatal error εκτελούνταν αφού το CSS είχε ήδη σταλεί στον browser (πράσινο background), κόβοντας όλο το body content.

**Bug 2:** `$isActive` έλεγχε μόνο `status = 'active'` — ίδιο root cause με Field Hub + Team Dashboard. Χρησιμοποιεί πλέον την κοινή λογική (open/confirmed/review past start_datetime).

## [0.9.26-beta] — 2026-06-18

### Fixed — Ops Centre: GPS/χάρτης 2 bugs

**Bug 1 (Visual — camera badge ποτέ δεν εμφανιζόταν):**  
Στο `updateMap()` η μεταβλητή `ph` (τελευταία φωτογραφία της ομάδας) χρησιμοποιούνταν στο `html` και `iconSize` ΠΡΙΝ οριστεί (`var ph = lastPhotosByTeam[...]` ήταν από κάτω). Λόγω JS var hoisting, `ph` ήταν πάντα `undefined` → το camera badge badge στους δείκτες ομάδων δεν εμφανιζόταν ποτέ. Λύση: μετακίνηση `var ph` πριν το `html`.

**Bug 2 (Functional — χάρτης παγώνει αν κολλήσει το SSE):**  
Το 15s fallback timer (για shared hosting χωρίς SSE) καλούσε μόνο `pollStatus()`, το οποίο δεν επιστρέφει `pings`. Έτσι `updateMap()` δεν εκτελούνταν και ο χάρτης σταματούσε να ενημερώνεται. Λύση: προστέθηκε `pollLocations()` στο fallback timer.

## [0.9.25-beta] — 2026-06-18

### Fixed & Enhanced — Team Dashboard

**Bug fix:** Ενεργή δράση (confirmed/open past start_datetime) εμφανιζόταν ως «0 Δράσεις σε Εξέλιξη» στο dashboard της ομάδας, γιατί το query έλεγχε μόνο `e.status = 'active'` — ίδιο root cause με το Field Hub (v0.9.24). Διορθώθηκε με την ίδια λογική.

**Redesign dashboard** (`/team/dashboard`):
- Νέα gradient stat cards (ds-card): Δράσεις σε Εξέλιξη / Ολοκληρωμένες φέτος / Ώρες Εθελοντισμού / Ενεργά Μέλη
- LIVE banner με pulse animation όταν τρέχουν δράσεις (+ Field Hub link αν υπάρχει token)
- Γρήγορες Ενέργειες με badges για νέες δράσεις / εκκρεμείς δηλώσεις
- Κατάσταση Ομάδας: αξιοπιστία (progress bar), μέσος χρόνος δήλωσης, μέλη, alert για εκκρεμείς
- Επερχόμενες εγκεκριμένες ως cards (με ημερομηνία, ώρα, τοποθεσία, άτομα)
- Πρόσφατη Δραστηριότητα: τελευταίες 4 ολοκληρωμένες δράσεις με date badge

## [0.9.24-beta] — 2026-06-18

### Fixed — Field Hub: κουμπιά disabled παρόλο που η δράση τρέχει

- Το Field Hub (`/f/{token}`) έδειχνε όλα τα κουμπιά (GPS, status pings, SOS, έλλειψη) ως greyed-out/disabled επειδή ελέγχε αυστηρά `event_status === 'active'`.
- Πρόβλημα: το Operations Command Centre εμφανίζει/επιτρέπει δράσεις ακόμα και με status `'open'/'confirmed'/'review'` αν έχει παρέλθει η `start_datetime` — ο admin έβλεπε τη δράση ζωντανή στο dashboard αλλά ο mission commander είχε κλειδωμένα κουμπιά.
- Λύση: `$isActive` και `requireActive()` ευθυγραμμίστηκαν με τη λογική του OperationController — ενεργό θεωρείται το event με `status = 'active'` Ή με `status IN (open, confirmed, review)` εφόσον `NOW() >= start_datetime`.

## [0.9.23-beta] — 2026-06-18

### Fixed — Settings: bootstrap is not defined / preset buttons broken
- Το hash routing script στις ρυθμίσεις έτρεχε πριν φορτωθεί το Bootstrap JS, προκαλώντας `ReferenceError: bootstrap is not defined` που τερμάτιζε ολόκληρο το script block — με αποτέλεσμα τα preset buttons Οργανισμού να μην λειτουργούν.
- Λύση: μετακίνηση σε `document.addEventListener('DOMContentLoaded', ...)` που τρέχει αφού φορτωθούν όλα τα scripts της σελίδας.

## [0.9.22-beta] — 2026-06-18

### Fixed — Οργανισμός: preset buttons auto-save
- Κλικ σε preset button (Δήμος / Πολ.Προστ. / Πυροσβεστική / Λιμενικό / Άλλο) κάνει πλέον αυτόματο submit της φόρμας — δεν χρειάζεται χωριστό κλικ στο «Αποθήκευση».

## [0.9.21-beta] — 2026-06-18

### Fixed — Security & reliability hardening (code smell pass)
- **🔴 XSS** `hub.php`: `sender_name` στο `renderMsgs` wrapped με `esc()` · `m.id` σε onclick cast με `parseInt` · coordinates σε Google Maps URLs με `parseFloat`
- **🔴 Fatal error** `hub.php`: `get_flash()` → `flash_get()` (λάθος function name προκαλούσε fatal error σε flash messages στο field hub)
- **🔴 PHP-in-JS injection** `hub.php`: coordinates (`evLat`/`evLng`/`tLat`/`tLng`) πλέον εξάγονται μέσω `json_encode()` αντί raw PHP output
- **🟠 Silent failures** `FieldController` & `OperationController`: όλα τα `catch (Throwable $e) {}` πλέον κάνουν `error_log()` ώστε να εντοπίζονται αποτυχίες notification service
- **🟠 Shortage state machine** `OperationController`: το resolve επιτρέπεται μόνο από `open` ή `acknowledged`, όχι από οποιοδήποτε state · προστέθηκε `else { abort(422) }` για invalid actions
- **🟡 Duplicate stats logic** `OperationController`: εξαγωγή σε `calcTeamStats()` helper — `status()` και `buildStreamSnapshot()` χρησιμοποιούν πλέον κοινή μέθοδο

## [0.9.20-beta] — 2026-06-18

### Added — Προφίλ Οργανισμού (Ρυθμίσεις → Οργανισμός)
- Νέα καρτέλα **Οργανισμός** στις ρυθμίσεις δήμου — ο super admin επιλέγει τον τύπο οργανισμού με preset κουμπιά: 🏛️ Δήμος / 🛡️ Πολ. Προστασία / 🚒 Πυροσβεστική / ⚓ Λιμενικό / 🏢 Άλλο.
- Ζωντανή προεπισκόπηση του πλήρους ονόματος και του σύντομου ονόματος (που εμφανίζεται στα μηνύματα επιχείρησης).
- Το σύντομο όνομα οργανισμού (`ORG_LABEL`) αντικαθιστά όλες τις hardcoded αναφορές σε «Δήμος» στο Επιχειρησιακό Κέντρο και το Field Hub.
- Νέα settings keys: `org_type`, `org_name`, `org_name_short` στον πίνακα `municipality_settings`.

## [0.9.19-beta] — 2026-06-18

### Fixed — Εικονίδιο κάμερας στο GPS σήμα: από κάτω → δίπλα + pulse
- Το μπλε badge κάμερας εμφανίζεται πλέον **δίπλα** (οριζόντια) στην κουκκίδα GPS αντί κάτω της, ώστε να μην καλύπτει το χρώμα ομάδας.
- Προστέθηκε pulse animation (μπλε glow) στο badge κάμερας.

## [0.9.18-beta] — 2026-06-18

### Added — Χρωματική κωδικοποίηση ομάδων στον χάρτη
- Κάθε ομάδα αποκτά αυτόματα μοναδικό χρώμα (παλέτα 10 χρωμάτων) για όλη τη διάρκεια της συνεδρίας.
- **GPS σήμα**: η κουκκίδα φιλοξενεί το χρώμα της ομάδας ως γέμισμα· το περίγραμμα δείχνει την παλαιότητα (πράσινο < 5λ / κίτρινο < 20λ / κόκκινο παλαιότερο).
- **Κάρτες ομάδων**: το αριστερό περίγραμμα + μικρή κουκκίδα δίπλα στο όνομα υιοθετούν το χρώμα της ομάδας.
- **Υπόμνημα**: εμφανίζεται αυτόματα κάτω από τον χάρτη με χρωματιστά chips και ονόματα ομάδων.

## [0.9.17-beta] — 2026-06-18

### Added — Χάρτης: badge φωτογραφίας πάνω στο GPS σήμα
- Αν μια ομάδα έχει στείλει φωτογραφία, το GPS σήμα της στον χάρτη εμφανίζει τώρα ένα μικρό μπλε κυκλικό badge με εικονίδιο κάμερας κάτω από την έγχρωμη κουκκίδα — άμεσα ορατή ένδειξη ότι υπάρχει φωτό.

## [0.9.16-beta] — 2026-06-18

### Added — Φωτογραφίες: ετικέτα ομάδας + ώρα · φωτό στο GPS popup
- Κάτω από κάθε thumbnail στην ενότητα «Φωτογραφίες ομάδων» εμφανίζεται τώρα το όνομα της ομάδας και η ώρα αποστολής.
- Κλικ στο GPS σήμα ομάδας (έγχρωμη κουκκίδα στον χάρτη) ανοίγει popup με το τελευταίο φωτό που έστειλε η ομάδα (αν υπάρχει) — κλικ στο φωτό ανοίγει το πλήρες modal.

## [0.9.15-beta] — 2026-06-17

### Fixed — Επιχειρήσεις: Ομάδες παρούσες πάντα 0
- Η σελίδα `/operations` έδειχνε πάντα `0/N Ομάδες παρούσες` γιατί ψάχνε `status='present'` που δεν υπάρχει — οι πραγματικές τιμές είναι `present_full` και `present_partial`. Διορθώθηκε με `IN ('present_full','present_partial')`.

## [0.9.14-beta] — 2026-06-17

### Fixed — Δραστηριότητα: raw κωδικοί check-in στα ελληνικά
- Η λίστα Δραστηριότητας έδειχνε ωμούς κωδικούς (`present_full`, `present_partial`, κτλ.) αντί για ελληνικές ετικέτες. Διορθώθηκε με `CASE WHEN` στο SQL του `buildActivityFeed()`.

## [0.9.13-beta] — 2026-06-17

### Fixed — Επιχειρησιακό Κέντρο: διπλό όνομα ομάδας στο chat
- Τα μηνύματα ομάδας εμφάνιζαν `Ομάδα Α → Ομάδα Α · 20:38` γιατί το `tag` (που προοριζόταν να δείχνει την *αποδέκτρια* ομάδα σε εντολή δήμου) εφαρμοζόταν και στα εισερχόμενα μηνύματα ομάδας. Τώρα το `tag` εμφανίζεται μόνο για μηνύματα `sender_role === 'command'`.

## [0.9.12-beta] — 2026-06-17

### Fixed — War Room: χάρτης έδειχνε Αθήνα με μία δράση
- Ο χάρτης έκανε auto-fit μόνο όταν υπήρχαν **2+ δράσεις** (`bounds.length > 1`). Με μία μόνο δράση έμενε στις default συντεταγμένες (Αθήνα). Πλέον με 1 δράση κάνει `setView` στις συντεταγμένες της με zoom 13.

## [0.9.11-beta] — 2026-06-17

### Fixed — Δήλωση συμμετοχής σε ενεργή δράση
- Ομάδες δεν μπορούσαν να υποβάλουν δήλωση όταν η δράση ήταν ήδη `active` (π.χ. ενεργοποιήθηκε χωρίς να περάσει από φάση `open`). Προστέθηκε το `active` στους επιτρεπτούς statuses τόσο στη φόρμα (`event_show.php`) όσο και στον controller (`TeamPortalController::apply()`).

## [0.9.10-beta] — 2026-06-17

### Added — Field Hub: αποστολή μηνύματος & αναφορά έλλειψης
- **Σύνθεση μηνύματος στο Field Hub** — ο υπεύθυνος αποστολής μπορεί πλέον να στέλνει
  ιδιωτικά μηνύματα προς τον δήμο απευθείας από το `/f/{token}` (νέο compose input στην
  κάρτα «Επικοινωνία με τον δήμο»). Νέο endpoint `POST /f/{token}/message`.
- **Αναφορά Έλλειψης από πεδίο** — νέα κάρτα «Αναφορά Έλλειψης» με επιλογή τύπου
  (άτομα/εξοπλισμός/ιατρικό/όχημα/άλλο), σοβαρότητα (χαμηλή/μεσαία/υψηλή/κρίσιμη),
  τίτλο και περιγραφή. Αποστέλλει στον πίνακα `shortage_reports` και ειδοποιεί τον δήμο.
  Νέο endpoint `POST /f/{token}/shortage`.

## [0.9.9-beta] — 2026-06-17

### Fixed — Field Hub: upload φωτό δεν έκλεινε το αίτημα
- **`FieldController::photo()` δεν καλούσε `PhotoRequest::fulfillForEventTeam()`** —
  μετά από upload φωτό μέσω του Field Hub (`/f/{token}`), το εκκρεμές αίτημα έμενε για πάντα
  `pending`. Αποτέλεσμα: banner φωτό δεν εξαφανιζόταν και το Επιχειρησιακό Κέντρο
  έδειχνε «Ζητήθηκε φωτό» ακόμη κι αφού ο υπεύθυνος είχε στείλει. Τώρα το αίτημα
  κλείνει αυτόματα, ακριβώς όπως στο `TeamPortalController::uploadPhoto`.

## [0.9.8-beta] — 2026-06-17

### Fixed — Mobile Hub: αιτήματα φωτό & GPS δεν εμφανίζονταν
- **`commsFeed()` δεν έδινε `photo_request` / `gps_request`** — το endpoint `/team/operations/events/{id}/comms`
  επέστρεφε μόνο μηνύματα/SOS/room. Τώρα περιλαμβάνει και τα δύο εκκρεμή αιτήματα.
- **Mobile Hub (`/team/live/{id}`) δεν είχε UI για αιτήματα** — προστέθηκε banner GPS στην κάρτα
  τοποθεσίας και νέα κάρτα φωτό (`#photoCard`) με κουμπί toggle, αίτηση εικόνας, auto-GPS, και
  banner που αναβοσβήνει όταν έχει εκκρεμές αίτημα φωτό.
- **`PhotoRequest::fulfillForEventTeam()` έλειπε** — κατά upload χωρίς `request_id`, η upload
  δεν έκλεινε το εκκρεμές αίτημα. Προστέθηκε η method (mirror του `GpsRequest`) και ο controller
  την καλεί πλέον πάντα αυτόματα.

## [0.9.7-beta] — 2026-06-17

### Added — Αίτημα στίγματος GPS (command → ομάδα)
- **«Ζήτησε στίγμα GPS»** — νέο κουμπί δίπλα στο «Φωτό» σε κάθε κάρτα ομάδας στο
  Επιχειρησιακό Κέντρο. Στέλνει αίτημα στην ομάδα (όπως το αίτημα φωτογραφίας)· η κάρτα
  δείχνει «Ζητήθηκε» μέχρι να σταλεί τοποθεσία. Στο πεδίο (field hub) και στο team portal
  εμφανίζεται banner· μόλις η ομάδα στείλει στίγμα, το αίτημα **κλείνει αυτόματα**.
- **Dropdown στόχου + μαζικά αιτήματα** — πάνω από τη λίστα ομάδων, ο διαχειριστής
  επιλέγει «📢 Όλες οι ομάδες» ή συγκεκριμένη ομάδα και ζητά **φωτό** ή **στίγμα** με μία
  κίνηση. Τα ανά-ομάδα κουμπιά παραμένουν.

### Technical
- Migration `016_gps_requests.sql`: νέος πίνακας `gps_requests` (mirror του
  `photo_requests`). Νέο model `GpsRequest`. Endpoint `OperationController::requestGps`
  + route `POST /operations/events/{id}/request-gps`. Flag `gps_pending` στο `status()`
  και στο SSE snapshot. Ειδοποίηση `NotificationService::gpsRequested`. Το
  `FieldController::location` και το `TeamPortalController::sendLocation` εκπληρώνουν
  (`fulfill`) τυχόν εκκρεμές αίτημα GPS· το `comms`/hub εκθέτουν `gps_request`.
- **Χρειάζεται εκτέλεση migration** — σε update μέσω GitHub Release τρέχει **αυτόματα**
  μέσω του `schema_migrations` / `MigrationRunner` (ή Platform Settings → Updates → run
  pending, ή import του `016_gps_requests.sql`).

## [0.9.6-beta] — 2026-06-17

### Fixed
- **Το badge «↺ Επανασύνδεση…» στο Επιχειρησιακό Κέντρο.** Το live stream στέλνει
  ένα snapshot και κλείνει (φιλικό σε shared hosting), οπότε ο browser
  επανασυνδέεται κάθε λίγα δευτερόλεπτα· αυτό το φυσιολογικό κλείσιμο εμφανιζόταν
  λανθασμένα ως «Επανασύνδεση». Πλέον το badge δείχνει **◉ LIVE** όσο φτάνουν
  snapshots (έλεγχος φρεσκάδας), και «Επανασύνδεση» μόνο αν δεν έρθει δεδομένο για
  >12s. Προστέθηκε και **polling fallback** (κάθε 10s) ώστε ο πίνακας να ανανεώνεται
  ακόμη κι αν κάποιος host μπλοκάρει το streaming.
- **Προβολή φωτογραφίας ομάδας (popup).** Το modal «έβγαινε εκτός οθόνης» με γκρι
  layer γιατί το `position:fixed` έσπαγε μέσα σε ancestor με CSS `transform`· τώρα το
  modal μεταφέρεται στο `<body>` πριν εμφανιστεί, είναι `scrollable` και χωράει πάντα
  στην οθόνη. Επιπλέον φαίνεται **καθαρά η ομάδα προέλευσης** (τίτλος + caption με
  ομάδα και ώρα λήψης) ώστε ο διαχειριστής να ξέρει ποια ομάδα έστειλε τη φωτογραφία.

## [0.9.5-beta] — 2026-06-17

### Fixed
- **Site-wide 500 από κατεστραμμένο release.** Το v0.9.4 zip περιείχε truncated
  `routes/web.php` (αστοχία συγχρονισμού). Το release ξαναχτίστηκε από ακέραιη πηγή
  (lint σε όλα τα PHP πριν τη δημοσίευση). Χωρίς αλλαγές κώδικα πέρα από αυτό.

## [0.9.4-beta] — 2026-06-17

### Fixed
- **Επιχειρησιακό Κέντρο: τα panels κολλούσαν στο «Φόρτωση…» & τα μηνύματα δεν
  εμφανίζονταν (production).** Διπλή αρχικοποίηση του χάρτη: το `views/operations/event.php`
  φτιάχνει inline τον επιχειρησιακό χάρτη, αλλά και το global `public/assets/js/maps.js`
  έτρεχε `initOperationalMap()` στο ίδιο container → Leaflet `Map container is already
  initialized` → uncaught error που σταματούσε το script ΠΡΙΝ τρέξουν τα
  `connectSSE()` / `pollStatus()`, οπότε κανένα panel δεν φόρτωνε και οι αποστολές
  μηνυμάτων δεν ανανέωναν το feed. Το `maps.js` δεν αγγίζει πλέον το `#operationalMap`
  (το διαχειρίζεται αποκλειστικά το event.php), με αμυντικό guard (`_leaflet_id`) και
  στις δύο πλευρές. Cache → `syndrasi-v5` ώστε να φτάσει το νέο `maps.js` στους clients.

## [0.9.3-beta] — 2026-06-17

### Fixed
- **Packaging fix — αρχεία που έλειπαν από το release.** Το v0.9.2-beta zip δεν
  περιείχε τα models `EventRoomMessage`, `PhotoRequest`, `EventPhoto`, το view
  `views/statistics/_overview.php`, ούτε τις migrations `011_event_photos.sql` και
  `015_event_room_messages.sql` (αστοχία συγχρονισμού htdocs→repo). Όλα μπήκαν πλέον
  στο αποθετήριο, ώστε ο in-app updater να τα εγκαθιστά και να τρέχει αυτόματα την
  migration `015`. Καμία αλλαγή κώδικα — μόνο πληρότητα πακέτου.

## [0.9.2-beta] — 2026-06-17

### Added
- **Δωμάτιο Επιχείρησης (κοινό κανάλι)** — ένα live, event-wide chat όπου γράφουν και
  βλέπουν όλοι μαζί: δήμος, operators, όλες οι ομάδες και οι Mission Υπεύθυνοι (field
  link). Ξεχωριστό από τα ιδιωτικά per-team threads (αυτά μένουν καθαρά για
  εντολές/SOS). Εμφανίζεται στο Επιχειρησιακό Κέντρο, στο Mobile Action Hub, στη σελίδα
  Επιχειρησιακών Ενεργειών και στο field hub, με live ανανέωση (~5s / SSE). Νέος πίνακας
  `event_room_messages` (migration `015_event_room_messages.sql`) + model
  `EventRoomMessage` (defensive — δεν σπάει αν λείπει η migration).

### Notes
- Χρειάζεται εκτέλεση της migration `015_event_room_messages.sql` (Updates → run pending).
  Χωρίς push για τα μηνύματα δωματίου (live κανάλι· οι κρίσιμες ειδοποιήσεις παραμένουν
  στα per-team threads/SOS).

## [0.9.1-beta] — 2026-06-17

### Added
- **Σύνδεσμος πεδίου για τον Mission Υπεύθυνο (χωρίς login).** Ο αρχηγός ορίζει
  υπεύθυνο δράσης (μέλος χωρίς λογαριασμό)· τώρα παράγεται προσωπικό token link
  `/f/{token}` όπου ο υπεύθυνος στέλνει **GPS στίγμα, status pings, SOS, φωτογραφία**
  και **βλέπει/επιβεβαιώνει εντολές** του δήμου. Όλα εμφανίζονται στο Επιχειρησιακό
  Κέντρο κάτω από το όνομα της ομάδας. Ο αρχηγός μοιράζεται τον σύνδεσμο με **SMS** ή
  **αντιγραφή** από τη σελίδα της δράσης. Νέα migration `013_field_token.sql`
  (`event_applications.field_token`), νέος `FieldController` + view `field/hub`.
- **Ειδοποιήσεις σε όλη την εφαρμογή** — global poller (~15s) που ενημερώνει ζωντανά
  το καμπανάκι 🔔 και βγάζει **toast popup** για κάθε νέα ειδοποίηση (αίτημα φωτο,
  εντολή/μήνυμα, SOS, ελλείψεις), σε όποια σελίδα κι αν είναι ο χρήστης. Νέο endpoint
  `/notifications/poll`.
- **Χάρτης στην πλευρά της ομάδας** (σελίδα Επιχειρησιακών Ενεργειών): σημείο δράσης +
  θέση ομάδας (τελευταίο στίγμα).
- **Καρφιτσωμένο banner ΕΝΤΟΛΗΣ** στην κορυφή των σελίδων της ομάδας (& field hub) για
  κάθε ανεπιβεβαίωτη εντολή — παραμένει μέχρι «Επιβεβαίωση λήψης».

- **Απολογισμός Δήμου (After-Action)** — στη σελίδα Debriefs της δράσης ο διαχειριστής
  γράφει δική του σύνοψη + συμπεράσματα/βελτιώσεις (`municipality_report`), με
  συγκεντρωτικά τα σύνολα από τις αναφορές ομάδων. Χωρίς migration.

- **Χάρτης και στο Mobile Action Hub (Live) και στο field hub** του Mission Υπευθύνου
  (σημείο δράσης + θέση ομάδας).
- **Background web push** — προστέθηκαν `push` + `notificationclick` handlers στον service
  worker (έλειπαν), ώστε να εμφανίζονται popup ειδοποιήσεις και με κλειστή/background
  εφαρμογή. Τα VAPID keys δημιουργούνται αυτόματα· ο χρήστης ενεργοποιεί push με το 🔔.
  Cache → `syndrasi-v4`.
- **Αίτημα φωτογραφίας στον Mission Υπεύθυνο** — banner στο field hub (live polling),
  ώστε το αίτημα να φτάνει στον υπεύθυνο πεδίου, όχι μόνο στον αρχηγό.
- **Geo-εντολές / σημεία δήμου→ομάδα** — ο δήμος στέλνει **σημείο στον χάρτη**
  (κλικ στον χάρτη ή συντεταγμένες) σε μία ομάδα ή σε όλες, με τύπο **Μετάβαση /
  Περιστατικό / Σημείο ενδιαφέροντος**. Η ομάδα το βλέπει ως **pin στον χάρτη** (Live +
  field hub + ops page) και καρφιτσωμένο banner με κουμπί **«Οδηγίες (Google Maps)»** +
  ACK (για Μετάβαση/Περιστατικό). Το **Περιστατικό** φεύγει με **forced push + SMS**
  (team admins + Mission Υπεύθυνος). Migration `014_event_message_geo.sql`.

### Fixed
- Η σελίδα λεπτομερειών δράσης ομάδας (`/team/events/{id}`) δεν «έσκαγε» (500) όταν
  έλειπε η migration του field link — ο έλεγχος είναι πλέον σε try/catch.

### Notes
- Χρειάζεται εκτέλεση της migration `013_field_token.sql` (Platform Settings → Updates
  → run pending, ή import). Οι υπόλοιπες αλλαγές δεν χρειάζονται migration.

## [0.9.0-beta] — 2026-06-16

### Added — Active-event communications (Κέντρο Επιχειρήσεων)
- **SOS / Man-down** — one-tap πλήκτρο SOS στο Mobile Action Hub και στη σελίδα
  Επιχειρησιακών Ενεργειών της ομάδας. Στέλνει αυτόματα GPS, και ειδοποιεί **forced
  push + SMS** όλο το command staff (admins + operators) ανεξάρτητα από ρυθμίσεις.
  Στο Επιχειρησιακό Κέντρο εμφανίζεται κόκκινο alarm banner που αναβοσβήνει, με
  **Επιβεβαίωση** (κλείνει τον loop πίσω στην ομάδα) και **Κλείσιμο**.
- **Αμφίδρομο chat** ανά ομάδα μέσα στη δράση — η ομάδα και ο δήμος ανταλλάσσουν
  μηνύματα· εμφανίζονται live (SSE στο command, ~5s polling στην ομάδα).
- **Broadcast / Εντολή από το command** — ο δήμος στέλνει μήνυμα ή **εντολή** σε μία
  ομάδα ή σε όλες (broadcast). Οι εντολές ζητούν **ACK** από την ομάδα, που φαίνεται
  στο board.
- **Loop επιβεβαίωσης ελλείψεων** — όταν ο δήμος κάνει «Σε γνώση»/«Λύθηκε» μια έλλειψη,
  ειδοποιείται η ομάδα (in-app + push). Υλοποιήθηκαν τα `acknowledgeShortage` /
  `resolveShortage` (τα routes υπήρχαν αλλά έδειχναν σε ανύπαρκτες μεθόδους).
- **Γρήγορα status pings** — ένα-tap: «Φτάσαμε στο σημείο», «Ολοκληρώθηκε», «Χρειαζόμαστε
  ενίσχυση», «Επιστροφή στη βάση», «Έχουμε περιστατικό».

### Technical
- Migration `012_ops_comms.sql`: `sos_alerts` + `event_messages`. Νέα models
  `SosAlert`, `EventMessage`· `User::commandStaff()`. Νέα endpoints σε
  `TeamPortalController` (sos/message/status-ping/ack-order/comms) και
  `OperationController` (sosAck/sosResolve/sendMessage + shortage ack/resolve). Το SSE
  snapshot και το `status()` μεταφέρουν πλέον `sos` + `messages`.
- **Χρειάζεται εκτέλεση migration** (Platform Settings → Updates → run pending, ή import
  του `012_ops_comms.sql`).

## [0.8.4-beta] — 2026-06-16

### Added
- **SMS gateway ρυθμίσεις ανά δήμο** — νέα καρτέλα «SMS» στις Ρυθμίσεις Δήμου όπου ο
  διαχειριστής ορίζει driver / sender / endpoint / **API key** του παρόχου. Αποθηκεύονται
  στο `municipality_settings` (με τις env μεταβλητές του `config/sms.php` ως platform
  default). Νέο `SmsService::resolveConfig()`· το `SmsService::send()` δέχεται πλέον
  προαιρετικό `$municipalityId`.
- **Επιλογέας καναλιού ανά τύπο ειδοποίησης** — η καρτέλα «Ειδοποιήσεις» αντικαθιστά τα
  on/off email toggles με επιλογή **Καμία / Μόνο Email / Μόνο SMS / Email+SMS** ανά τύπο
  (`notify_channel_<type>`). Το `NotificationService` στέλνει πλέον SMS στους διαχειριστές
  με τηλέφωνο όταν το κανάλι το περιλαμβάνει· το κάλεσμα έκτακτης ανάγκης χρησιμοποιεί τα
  per-δήμο SMS credentials. Διατηρείται backward-compat με το legacy `notify_email_<type>`.

### Notes
- Χωρίς migration — χρησιμοποιεί το υπάρχον `municipality_settings` (key/value) και
  `users.phone`. Τα credits SMS αγοράζονται απευθείας από τον πάροχο· η εφαρμογή κρατά
  μόνο το API key και στέλνει.

## [0.8.3-beta] — 2026-06-16

### Added
- **PWA install banner (κάτω δεξιά)** — διακριτικό, dismissible banner που προτρέπει
  τον χρήστη να εγκαταστήσει την εφαρμογή στο κινητό. Σε Android/Chromium πιάνει το
  `beforeinstallprompt` και ενεργοποιεί το native install prompt· σε iOS Safari (που
  δεν στέλνει το event) εμφανίζει οδηγίες «Κοινή χρήση → Προσθήκη στην αρχική οθόνη».
  Κρύβεται σε standalone mode και επανεμφανίζεται 7 μέρες μετά από απόρριψη
  (`localStorage`). Υλοποίηση εξ ολοκλήρου στο `pwa.js` — δεν χρειάστηκε αλλαγή layouts.
- **iOS standalone meta tags** στο `header.php` (`apple-mobile-web-app-capable`,
  status-bar style, app title) ώστε η εγκατεστημένη εφαρμογή να ανοίγει full-screen
  σε iPhone.

### Fixed
- **Άκυρο `manifest.json`** — αφαιρέθηκε ένα τελικό NUL byte που έκανε το αρχείο
  αυστηρά μη-έγκυρο JSON.
- **Service worker με απόλυτα paths** — το `service-worker.js` χρησιμοποιούσε
  leading-slash paths (`/offline.html`, `/assets/...`) που έσπαγαν το offline
  fallback και το precache όταν η εφαρμογή σερβίρεται σε υποφάκελο. Τώρα υπολογίζει
  το BASE από το `self.location` και δουλεύει είτε στη ρίζα είτε σε υποφάκελο.

### Changed
- Βελτιωμένο `manifest.json`: προστέθηκαν `id`, `scope`, `categories`, `dir`, και
  ξεχωριστά `any`/`maskable` icons για σωστή installability. Cache bumped σε
  `syndrasi-v3` (παλιά caches καθαρίζονται αυτόματα στο activate).

## [0.8.2-beta] — 2026-06-15

### Added
- **Platform Settings → Cron Jobs tab** — a one-click "Run cleanup now" button
  (super admin) that triggers the same housekeeping as `/cron/cleanup`, via a new
  shared `MaintenanceService`. No scheduler needed for demos.
- **Platform Settings → Updates tab** — self-update from **GitHub Releases**:
  configure repo owner/name (+ optional token for private repos), check the latest
  release against the local `VERSION`, and apply it. `UpdateService` downloads the
  release ZIP, backs up current code to `storage/backups/`, extracts over the app
  while **preserving `config/` and `storage/`**, and logs to `storage/logs/update.log`.
- **Automatic DB migrations** — new `MigrationRunner` + `schema_migrations` table.
  Updates run any new migration files automatically. Existing migrations (001–010)
  are baselined as applied so they never re-run; the tab shows applied/pending counts
  with a manual "run pending" button.
- Added a `VERSION` file as the source of truth for the running version.

## [0.8.1-beta] — 2026-06-15

### Added
- **SMS channel for mobilization** — new `SmsService` + `config/sms.php`. Default
  `log` driver writes to `storage/logs/sms.log` (zero-setup, testable); set
  `SMS_DRIVER=http` + `SMS_ENDPOINT`/`SMS_API_KEY` for a real gateway.
  `NotificationService::mobilize()` now SMSes targeted volunteers their response link.
- **Member ↔ account link** — migration `010_team_member_user_link.sql` adds
  `team_members.user_id` (backfilled by email). Call-outs now web-push members who
  hold an account directly, instead of only matching by email.

### Note
- Migration `002` (the `team_members` / `event_application_members` roster tables)
  had not been applied to the working database; it has now been run so the
  mobilization and roster features work.

## [0.8.0-beta] — 2026-06-15

### Added
- **Emergency Mobilization (Κάλεσμα Έκτακτης Ανάγκης)** — instant call-out for
  unplanned incidents. A municipality admin/operator creates a call-out (title,
  severity, location, target = whole municipality or selected teams); every targeted
  volunteer gets a personal **no-login token link** (`/m/{token}`) to reply
  *Έρχομαι / Δεν μπορώ / Ίσως* with an ETA and to mark on-site arrival/departure. A
  live command board (`/mobilizations/{id}`) polls a JSON stream and shows
  **Confirmed → En route → On-site → Departed → Declined → No-reply** in real time.
  - New tables via migration `009_mobilizations.sql`: `mobilizations`,
    `mobilization_responses`.
  - New `Mobilization` / `MobilizationResponse` models, `MobilizationController`,
    and `NotificationService::mobilize()` (push to members with accounts, email link
    fallback, in-app + push awareness ping to command staff). SMS hook left as a TODO.
  - Sidebar entry for municipality admins and operators.

### Fixed
- **Router could never match string tokens** — every `{param}` was compiled to a
  numeric `(\d+)` pattern, so the existing `/public/events/{token}` share links (32-char
  hex) silently 404'd. `{token}` now matches `[A-Za-z0-9]+`; other params stay numeric.

## [0.7.3-beta] — 2026-06-14

### Fixed
- **CSRF JSON path crash:** `public/index.php` called the undefined `json_response()`
  when a JSON POST failed CSRF validation, causing a fatal error instead of a clean
  419 response. Now calls `json_out()`.
- **Output escaping:** municipality dashboard category distribution now escapes the
  label/colour values with `e()`.

### Changed
- **Environment is now config-driven:** `config/config.php` reads `APP_ENV` via a new
  `env()` helper and **defaults to `production`** (fail-safe — no error leakage if the
  var is unset). Set `APP_ENV=development` locally to see errors. `database.php` and
  `mail.php` reuse the same `env()` helper.
- **Performance — removed N+1 queries:** event reconciliation and the users admin page
  pre-load related rows in batched queries (`TeamMember::forApplications()`,
  `VolunteerParticipation::forApplications()`, grouped team lookup) instead of querying
  per row inside the view.
- **De-duplicated password validation** behind a single `password_error()` helper used
  by reset, change-password, and admin user flows.

### Added
- **`GET /cron/cleanup`** (token-protected) purges accumulated transient `app_settings`
  rows (login throttling, shift-reminder flags) and spent password-reset tokens.
  Migration `008_app_settings_timestamp.sql` gives `app_settings.updated_at` a default
  so rows can be aged out. Recommend a daily cron.

## [0.7.2-beta] — 2026-06-14

### Changed
- **Operations auto-live:** published (`open`) events now appear automatically in
  Κέντρο Επιχειρήσεων and the War Room once the current time is within their
  start/end window — no manual "Έναρξη δράσης" required (manual start still works).
  Also surfaced in "Ξεκινά σύντομα" within 60 minutes of start.

## [0.7.1-beta] — 2026-06-14

### Fixed
- **Operations visibility:** published (`open`) events could not be started — the
  "Έναρξη δράσης" button was shown but the state machine forbade `open → active`,
  so activation silently failed and the event never appeared in Κέντρο Επιχειρήσεων.
  Added `active` to the allowed transitions from `open`.

## [0.7.0-beta] — 2026-06-14

### Added
- **Event templates** (core fields + shifts): save an event as a reusable template
  ("Ως πρότυπο" on the event page) and spin up new events from it ("Νέα από πρότυπο"
  on the events list). Shifts are stored as offsets and re-created with concrete
  datetimes on the new event. New `event_templates` table (migration 007) + `EventTemplate` model.

## [0.6.0-beta] — 2026-06-14

### Added
- **QR check-in** (optional alternative to the normal flow): operators open a
  full-screen **QR Πύλης** (Gate QR) from the operations command center; team
  leaders scan it on their phones to reach a one-tap check-in page
  (`/team/qr-checkin/{id}`) that reuses the existing check-in endpoint and
  `operational_checkins` table. Present-full / partial / departed supported.

## [0.5.0-beta] — 2026-06-14

First versioned release / initial GitHub sync.

### Added
- **Κέντρο Συντονισμού** (multi-event War Room): municipality-wide live map of all
  active events at once, colour-coded by coverage/shortages, live event list with
  coverage bars and countdowns, global totals, SSE-driven, drill-down to each
  command center.
- **Αναλύσεις & Τάσεις** (Advanced reporting): multi-year year-over-year analytics —
  KPI cards with YoY deltas, diachronic trend chart, monthly comparison,
  response-time trend, top-team hours per year, and CSV export (yearly/category/teams).

### Changed
- Service worker now uses **stale-while-revalidate** for static assets (was
  cache-first) and the cache was bumped to `syndrasi-v2`, fixing stale CSS/JS that
  required a hard refresh (Ctrl+F5) to load the dashboard cards.

### Baseline (pre-0.5.0)
- MVC core, auth, roles, multi-tenancy; municipality dashboard with charts.
- Volunteer teams & members, event lifecycle, applications, approvals, shifts,
  reconciliation, debriefs.
- Single-event operations command center (SSE live map, check-ins, shortages,
  notes, activity), Mobile Action Hub.
- Statistics, awards, CSV exports, PDF reports.
- Per-municipality settings, notifications (in-app + email + Web Push), public
  event pages, super-admin panel, PWA.
