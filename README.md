# SynDrasi

Πλατφόρμα συντονισμού δημοτικών δράσεων και εθελοντικών ομάδων.

SynDrasi είναι μια απλή, εγκαταστάσιμη (PWA) web εφαρμογή που βοηθά τους δήμους να συντονίζουν εθελοντικές/διασωστικές/υγειονομικές ομάδες σε δημόσιες εκδηλώσεις: δημοσίευση δράσεων, δηλώσεις συμμετοχής, εγκρίσεις, επιχειρησιακός χάρτης με στίγματα ομάδων, δηλώσεις παρουσίας, αναφορές ελλείψεων, στατιστικά και επιβράβευση προσφοράς.

## Τεχνολογίες

- Pure PHP 8.1+ (χωρίς framework)
- MySQL 8 / MariaDB 10.5+
- Bootstrap 5 (CDN), Leaflet.js (χάρτες), Chart.js (γραφήματα)
- PWA: manifest + service worker (εγκατάσταση σε Android/iPhone)

## Εγκατάσταση

1. **Βάση δεδομένων**

   ```bash
   mysql -u root -p < database/schema.sql
   mysql -u root -p syndrasi < database/seed.sql   # προαιρετικά demo δεδομένα
   ```

2. **Ρυθμίσεις**

   Επεξεργαστείτε το `config/database.php` (ή ορίστε μεταβλητές περιβάλλοντος `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).

   Email: στο `config/mail.php` ο προεπιλεγμένος driver είναι `log` (τα email γράφονται στο `storage/logs/mail.log`). Για πραγματική αποστολή ορίστε `mail` ή `smtp` (το `smtp` απαιτεί `composer require phpmailer/phpmailer`).

3. **Web server**

   Το web root πρέπει να δείχνει στον φάκελο `/public`.

   - **Apache:** υπάρχουν έτοιμα `.htaccess`. Αν το vhost δείχνει στη ρίζα του project, το root `.htaccess` προωθεί στο `/public`.
   - **Τοπική δοκιμή:**

     ```bash
     php -S localhost:8080 -t public
     ```

4. **Δικαιώματα**

   Ο φάκελος `storage/` πρέπει να είναι εγγράψιμος από τον web server.

## Demo λογαριασμοί (seed.sql)

Όλοι οι κωδικοί: `Syndrasi!2026`

| Ρόλος | Email |
|---|---|
| Super Admin | superadmin@syndrasi.gr |
| Διαχειριστής Δήμου | admin@dimos.gr |
| Χειριστής Δράσεων | operator@dimos.gr |
| Υπεύθυνος Ομάδας 1 | omada1@syndrasi.gr |
| Υπεύθυνος Ομάδας 2 | omada2@syndrasi.gr |
| Υπεύθυνος Ομάδας 3 | omada3@syndrasi.gr |
| Υπεύθυνος Ομάδας 4 | omada4@syndrasi.gr |

Τα demo δεδομένα περιλαμβάνουν ολοκληρωμένες δράσεις (για στατιστικά), μια **ενεργή δράση σήμερα** (για δοκιμή της Επιχειρησιακής Σελίδας), ανοιχτές δράσεις και εκκρεμείς δηλώσεις.

## Βασικές ροές

1. Ο δήμος δημιουργεί και δημοσιεύει δράση → οι ομάδες ειδοποιούνται (in-app + email).
2. Η ομάδα δηλώνει συμμετοχή (άτομα, όχημα, εξοπλισμός).
3. Ο δήμος εγκρίνει/απορρίπτει και ορίζει εγκεκριμένα άτομα.
4. Την ημέρα της δράσης (κατάσταση «Ενεργή»): η ομάδα στέλνει στίγμα **χειροκίνητα**, δηλώνει παρουσία (πλήρη/μερική/αποχώρηση) και αναφέρει ελλείψεις.
5. Ο δήμος βλέπει τα πάντα στην Επιχειρησιακή Σελίδα (χάρτης, παρουσίες, ελλείψεις, σημειώσεις).
6. Μετά την ολοκλήρωση: αναφορές ομάδων, στατιστικά, ετήσια κατάταξη και Επιβράβευση Ομάδων, εξαγωγές CSV.

## Δομή project

```
/app          Controllers, Models, Services, Middleware, Helpers
/config       config.php, database.php, mail.php
/database     schema.sql, seed.sql
/public       index.php (front controller), assets, manifest, service worker
/routes       web.php
/storage      logs, uploads, exports
/views        templates ανά ενότητα
```

## Ασφάλεια

PDO prepared statements παντού, CSRF token σε όλα τα POST, `htmlspecialchars()` στην έξοδο, `password_hash()`/`password_verify()`, role-based access control, απομόνωση δεδομένων ανά δήμο (multi-tenant), audit log, ασφαλή session cookies.

---

Πρόταση από Σφακιανάκη Θεόδωρο | email: theodore.sfakianakis@gmail.com | κιν. 6945139015
