# Changelog

All notable changes to SynDrasi are documented here.
Format loosely follows [Keep a Changelog](https://keepachangelog.com/);
versioning is `MAJOR.MINOR.PATCH` (beta line until feature-complete).

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
