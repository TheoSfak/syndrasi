# Changelog

All notable changes to SynDrasi are documented here.
Format loosely follows [Keep a Changelog](https://keepachangelog.com/);
versioning is `MAJOR.MINOR.PATCH` (beta line until feature-complete).

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
  ειδοποιείται η ομάδα (in-app + push)