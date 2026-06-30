-- ============================================================
-- SynDrasi - Demo seed data
-- Run AFTER schema.sql:  mysql -u root -p syndrasi < seed.sql
--
-- Demo accounts:
--   superadmin@syndrasi.gr  -> Super Admin
--   admin@dimos.gr          -> Municipality Admin (Δήμος Ηρακλείου)
--   operator@dimos.gr       -> Event Operator
--   omada1@syndrasi.local   -> Team Admin (password: Syndrasi2026)
--   omada2@syndrasi.local   -> Team Admin (password: Syndrasi2026)
--   omada3@syndrasi.local   -> Team Admin (password: Syndrasi2026)
--   omada4@syndrasi.local   -> Team Admin (password: Syndrasi2026)
-- ============================================================

USE syndrasi;

-- ------------------------------------------------------------ Municipality
INSERT INTO municipalities (id, name, city, address, email, phone, status) VALUES
(1, 'Δήμος Ηρακλείου', 'Ηράκλειο', 'Αγίου Τίτου 1, Ηράκλειο Κρήτης', 'info@heraklion.gr', '2813409000', 'active');

-- ------------------------------------------------------------ Teams
INSERT INTO volunteer_teams
(id, municipality_id, name, type, contact_person, email, phone, address, has_vehicle, has_medical_equipment, default_people_capacity, notes, status) VALUES
(1, 1, 'Ελληνική Ομάδα Διάσωσης Κρήτης', 'Διασωστική', 'Γιώργος Παπαδάκης', 'omada1@syndrasi.local', '6940000001', 'Ηράκλειο', 1, 1, 15, 'Έμπειρη ομάδα με πλήρη εξοπλισμό.', 'active'),
(2, 1, 'Εθελοντές Σαμαρείτες Ερυθρού Σταυρού', 'Υγειονομική', 'Μαρία Βλαχάκη', 'omada2@syndrasi.local', '6940000002', 'Ηράκλειο', 1, 1, 12, 'Εξειδίκευση σε πρώτες βοήθειες.', 'active'),
(3, 1, 'Ομάδα Εθελοντών Πολιτικής Προστασίας', 'Πολιτική Προστασία', 'Νίκος Μανωλάκης', 'omada3@syndrasi.local', '6940000003', 'Ηράκλειο', 1, 0, 20, NULL, 'active'),
(4, 1, 'Διασώστες Κρήτης', 'Διασωστική', 'Ελένη Σταυρακάκη', 'omada4@syndrasi.local', '6940000004', 'Ηράκλειο', 0, 1, 8, NULL, 'active');

-- ------------------------------------------------------------ Users
-- password for all: Syndrasi!2026
INSERT INTO users (id, municipality_id, team_id, name, email, phone, password_hash, role, status) VALUES
(1, NULL, NULL, 'Διαχειριστής Πλατφόρμας', 'superadmin@syndrasi.gr', '6945139015', '$2y$10$36mrwix3K3wjYsTs7ZRIbO6NlLnZBy/T69VzZ1YwOp47K9P/pm/72', 'super_admin', 'active'),
(2, 1, NULL, 'Κώστας Δημητρίου', 'admin@dimos.gr', '2813409100', '$2y$10$36mrwix3K3wjYsTs7ZRIbO6NlLnZBy/T69VzZ1YwOp47K9P/pm/72', 'municipality_admin', 'active'),
(3, 1, NULL, 'Άννα Καραγιάννη', 'operator@dimos.gr', '2813409101', '$2y$10$36mrwix3K3wjYsTs7ZRIbO6NlLnZBy/T69VzZ1YwOp47K9P/pm/72', 'event_operator', 'active'),
(4, 1, 1, 'Γιώργος Παπαδάκης', 'omada1@syndrasi.local', '6940000001', '$2y$10$e2ElMBvn3UKMUcM2mW082.bVCLOwXe3PAD4oECkLWtStbvFS1HjkW', 'team_admin', 'active'),
(5, 1, 2, 'Μαρία Βλαχάκη', 'omada2@syndrasi.local', '6940000002', '$2y$10$e2ElMBvn3UKMUcM2mW082.bVCLOwXe3PAD4oECkLWtStbvFS1HjkW', 'team_admin', 'active'),
(6, 1, 3, 'Νίκος Μανωλάκης', 'omada3@syndrasi.local', '6940000003', '$2y$10$e2ElMBvn3UKMUcM2mW082.bVCLOwXe3PAD4oECkLWtStbvFS1HjkW', 'team_admin', 'active'),
(7, 1, 4, 'Ελένη Σταυρακάκη', 'omada4@syndrasi.local', '6940000004', '$2y$10$e2ElMBvn3UKMUcM2mW082.bVCLOwXe3PAD4oECkLWtStbvFS1HjkW', 'team_admin', 'active');

-- ------------------------------------------------------------ Events
-- Past completed events, one active today, open future events, one draft.
INSERT INTO events
(id, municipality_id, category_id, title, description, location_name, address, latitude, longitude, start_datetime, end_datetime, requested_people, requested_vehicle, requested_medical_equipment, instructions, status, published_at, created_by) VALUES
(1, 1, 1, 'Φεστιβάλ Παραδοσιακών Χορών', 'Μεγάλη πολιτιστική εκδήλωση με συμμετοχή χορευτικών συλλόγων.', 'Πλατεία Ελευθερίας', 'Πλατεία Ελευθερίας, Ηράκλειο', 35.3387000, 25.1442000,
 TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 60 DAY), '18:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 60 DAY), '23:00:00'),
 10, 1, 1, 'Προσέλευση 30 λεπτά πριν την έναρξη.', 'completed', DATE_SUB(NOW(), INTERVAL 75 DAY), 2),

(2, 1, 3, 'Ημιμαραθώνιος Ηρακλείου', 'Αγώνας δρόμου 21χλμ στο παραλιακό μέτωπο.', 'Παραλιακή Λεωφόρος', 'Λεωφ. Σοφοκλή Βενιζέλου, Ηράκλειο', 35.3416000, 25.1290000,
 TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 35 DAY), '07:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 35 DAY), '14:00:00'),
 12, 1, 1, 'Κάλυψη σταθμών ανεφοδιασμού και τερματισμού.', 'completed', DATE_SUB(NOW(), INTERVAL 50 DAY), 2),

(3, 1, 2, 'Συναυλία Δημοτικού Θερινού Κινηματογράφου', 'Μουσική βραδιά με τοπικά συγκροτήματα.', 'Κηποθέατρο Ν. Καζαντζάκης', 'Κηποθέατρο, Ηράκλειο', 35.3329000, 25.1306000,
 TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '20:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '23:30:00'),
 8, 0, 1, NULL, 'completed', DATE_SUB(NOW(), INTERVAL 25 DAY), 2),

(4, 1, 5, 'Εορταστική Δράση Λιμανιού', 'Εορταστική δράση με πλήθος επισκεπτών στο ενετικό λιμάνι.', 'Ενετικό Λιμάνι', 'Λιμάνι Ηρακλείου', 35.3445000, 25.1352000,
 TIMESTAMP(CURDATE(), '08:00:00'), TIMESTAMP(CURDATE(), '23:00:00'),
 12, 1, 1, 'Σημείο συνάντησης: είσοδος Κούλε.', 'active', DATE_SUB(NOW(), INTERVAL 10 DAY), 2),

(5, 1, 1, 'Γιορτή Κρητικής Γαστρονομίας', 'Εκθέματα και γευσιγνωσία τοπικών προϊόντων.', 'Πάρκο Γεωργιάδη', 'Πάρκο Γεωργιάδη, Ηράκλειο', 35.3350000, 25.1400000,
 TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 7 DAY), '17:00:00'), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 7 DAY), '22:00:00'),
 8, 0, 1, NULL, 'open', DATE_SUB(NOW(), INTERVAL 3 DAY), 2),

(6, 1, 4, 'Δράση Αιμοδοσίας και Ενημέρωσης', 'Εθελοντική αιμοδοσία και ενημέρωση πολιτών.', 'Δημαρχείο Ηρακλείου', 'Αγίου Τίτου 1, Ηράκλειο', 35.3394000, 25.1330000,
 TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 15 DAY), '09:00:00'), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 15 DAY), '15:00:00'),
 6, 0, 1, 'Απαιτείται υγειονομικός εξοπλισμός.', 'open', DATE_SUB(NOW(), INTERVAL 1 DAY), 2),

(7, 1, 6, 'Χριστουγεννιάτικο Χωριό (προσχέδιο)', 'Προγραμματισμός εορταστικών δράσεων.', 'Πλατεία Ελευθερίας', 'Πλατεία Ελευθερίας, Ηράκλειο', NULL, NULL,
 TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 30 DAY), '10:00:00'), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 30 DAY), '22:00:00'),
 15, 1, 1, NULL, 'draft', NULL, 2);

-- ------------------------------------------------------------ Applications
INSERT INTO event_applications
(id, municipality_id, event_id, team_id, offered_people, offered_vehicle, offered_medical_equipment, comment, status, approved_people, admin_comment, submitted_at, reviewed_at, reviewed_by) VALUES
-- event 1 (completed)
(1, 1, 1, 1, 8, 1, 1, 'Διαθέσιμοι με πλήρη εξοπλισμό.', 'approved', 6, 'Ευχαριστούμε για τη διαθεσιμότητα.', DATE_SUB(NOW(), INTERVAL 74 DAY), DATE_SUB(NOW(), INTERVAL 72 DAY), 2),
(2, 1, 1, 2, 5, 1, 1, NULL, 'approved', 4, NULL, DATE_SUB(NOW(), INTERVAL 73 DAY), DATE_SUB(NOW(), INTERVAL 72 DAY), 2),
(3, 1, 1, 3, 6, 1, 0, NULL, 'rejected', NULL, 'Καλύφθηκαν οι ανάγκες της δράσης.', DATE_SUB(NOW(), INTERVAL 71 DAY), DATE_SUB(NOW(), INTERVAL 70 DAY), 2),
-- event 2 (completed)
(4, 1, 2, 1, 6, 1, 1, NULL, 'approved', 6, NULL, DATE_SUB(NOW(), INTERVAL 49 DAY), DATE_SUB(NOW(), INTERVAL 48 DAY), 2),
(5, 1, 2, 4, 4, 0, 1, 'Κάλυψη τερματισμού.', 'approved', 3, NULL, DATE_SUB(NOW(), INTERVAL 47 DAY), DATE_SUB(NOW(), INTERVAL 46 DAY), 2),
-- event 3 (completed)
(6, 1, 3, 2, 10, 1, 1, NULL, 'approved', 8, NULL, DATE_SUB(NOW(), INTERVAL 24 DAY), DATE_SUB(NOW(), INTERVAL 23 DAY), 2),
(7, 1, 3, 3, 5, 1, 0, NULL, 'approved', 5, NULL, DATE_SUB(NOW(), INTERVAL 22 DAY), DATE_SUB(NOW(), INTERVAL 21 DAY), 2),
-- event 4 (active today)
(8, 1, 4, 1, 6, 1, 1, 'Πλήρης ομάδα με όχημα.', 'approved', 5, 'Σημείο συνάντησης: Κούλες.', DATE_SUB(NOW(), INTERVAL 9 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY), 2),
(9, 1, 4, 2, 4, 1, 1, NULL, 'approved', 4, NULL, DATE_SUB(NOW(), INTERVAL 9 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY), 2),
(10, 1, 4, 3, 8, 1, 0, 'Διαθέσιμοι όλη μέρα.', 'pending', NULL, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), NULL, NULL),
-- event 5 (open)
(11, 1, 5, 1, 5, 1, 1, NULL, 'pending', NULL, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), NULL, NULL),
(12, 1, 5, 4, 4, 0, 1, NULL, 'pending', NULL, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), NULL, NULL);

-- ------------------------------------------------------------ Check-ins
INSERT INTO operational_checkins
(municipality_id, event_id, team_id, application_id, status, present_people, expected_people, message, checked_in_by, checked_in_at) VALUES
(1, 1, 1, 1, 'present_full', 6, 6, NULL, 4, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 60 DAY), '17:40:00')),
(1, 1, 2, 2, 'present_partial', 3, 4, 'Ένα μέλος ασθένησε.', 5, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 60 DAY), '17:55:00')),
(1, 2, 1, 4, 'present_full', 6, 6, NULL, 4, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 35 DAY), '06:30:00')),
(1, 2, 4, 5, 'present_full', 3, 3, NULL, 7, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 35 DAY), '06:45:00')),
(1, 3, 2, 6, 'present_partial', 6, 8, 'Δύο μέλη δεν προσήλθαν.', 5, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '19:30:00')),
(1, 3, 3, 7, 'present_full', 5, 5, NULL, 6, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '19:20:00')),
(1, 4, 1, 8, 'present_full', 5, 5, NULL, 4, TIMESTAMP(CURDATE(), '08:10:00'));

-- ------------------------------------------------------------ Location pings (active event)
INSERT INTO location_pings (municipality_id, event_id, team_id, user_id, latitude, longitude, accuracy, message, created_at) VALUES
(1, 4, 1, 4, 35.3448000, 25.1349000, 12.50, 'Στη θέση μας.', TIMESTAMP(CURDATE(), '08:12:00')),
(1, 4, 2, 5, 35.3441000, 25.1361000, 18.00, NULL, TIMESTAMP(CURDATE(), '09:05:00'));

-- ------------------------------------------------------------ Shortage reports
INSERT INTO shortage_reports
(municipality_id, event_id, team_id, reported_by, shortage_type, severity, title, description, status, acknowledged_by, acknowledged_at, resolved_by, resolved_at, created_at) VALUES
(1, 3, 2, 5, 'people', 'medium', 'Έλλειψη δύο ατόμων', 'Δύο εθελοντές δεν προσήλθαν.', 'resolved', 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '19:45:00'), 2, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '20:30:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY), '19:35:00')),
(1, 4, 2, 5, 'medical_supplies', 'high', 'Έλλειψη υγειονομικού υλικού', 'Χρειαζόμαστε επιπλέον φαρμακευτικό υλικό στο σημείο 2.', 'open', NULL, NULL, NULL, NULL, TIMESTAMP(CURDATE(), '10:20:00'));

-- ------------------------------------------------------------ Event reports (completed events)
INSERT INTO event_reports
(municipality_id, event_id, team_id, report_type, incidents_count, transfers_count, first_aid_count, summary, created_by, created_at) VALUES
(1, 1, 1, 'team_report', 2, 0, 2, 'Δύο περιστατικά ελαφράς αδιαθεσίας, αντιμετωπίστηκαν επί τόπου.', 4, DATE_SUB(NOW(), INTERVAL 59 DAY)),
(1, 1, 2, 'team_report', 1, 0, 1, 'Ένα περιστατικό πρώτων βοηθειών.', 5, DATE_SUB(NOW(), INTERVAL 59 DAY)),
(1, 2, 1, 'team_report', 4, 1, 3, 'Τέσσερα περιστατικά, μία διακομιδή.', 4, DATE_SUB(NOW(), INTERVAL 34 DAY)),
(1, 2, 4, 'team_report', 1, 0, 1, 'Ήπιο περιστατικό αφυδάτωσης.', 7, DATE_SUB(NOW(), INTERVAL 34 DAY)),
(1, 3, 2, 'team_report', 0, 0, 0, 'Καμία ανάγκη παρέμβασης.', 5, DATE_SUB(NOW(), INTERVAL 13 DAY)),
(1, 3, 3, 'team_report', 1, 0, 0, 'Μικρή υλική ζημιά, ενημερώθηκε ο δήμος.', 6, DATE_SUB(NOW(), INTERVAL 13 DAY));

-- ------------------------------------------------------------ Notifications
INSERT INTO notifications (municipality_id, user_id, team_id, event_id, title, message, type, is_read, email_sent, created_at) VALUES
(1, 4, 1, 5, 'Νέα δράση: Γιορτή Κρητικής Γαστρονομίας', 'Δημοσιεύθηκε νέα δράση. Δηλώστε συμμετοχή.', 'event_published', 1, 1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1, 5, 2, 5, 'Νέα δράση: Γιορτή Κρητικής Γαστρονομίας', 'Δημοσιεύθηκε νέα δράση. Δηλώστε συμμετοχή.', 'event_published', 0, 1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1, 2, NULL, 4, 'Αναφορά έλλειψης', 'Η ομάδα Εθελοντές Σαμαρείτες ανέφερε έλλειψη υγειονομικού υλικού.', 'shortage_reported', 0, 0, TIMESTAMP(CURDATE(), '10:20:00')),
(1, 6, 3, 4, 'Η δήλωσή σας εκκρεμεί', 'Η δήλωση συμμετοχής σας για την Εορταστική Δράση Λιμανιού είναι σε αξιολόγηση.', 'application_submitted', 0, 1, DATE_SUB(NOW(), INTERVAL 2 DAY));

-- ------------------------------------------------------------ Settings
INSERT INTO app_settings (setting_key, setting_value) VALUES
('platform_announcement', ''),
('support_email', 'theodore.sfakianakis@gmail.com');
