<?php
/**
 * SynDrasi - Platform maintenance & self-update (super admin).
 * Backs the "Cron Jobs" and "Updates" tabs in Platform Settings.
 */
class MaintenanceController
{
    /** Run the housekeeping cleanup on demand (replaces the cron schedule for demos). */
    public function cleanup()
    {
        requireRole([Role::SUPER_ADMIN]);
        $r = MaintenanceService::cleanup();
        flash_set('success', sprintf(
            'Καθαρισμός ολοκληρώθηκε: %d εγγραφές rate-limit, %d tokens επαναφοράς διαγράφηκαν.',
            $r['rate_limit_removed'], $r['reset_tokens_removed']
        ));
        redirect('/admin/settings#cron');
    }

    /* ── Updates ───────────────────────────────────────────────────────────── */

    public function backup()
    {
        requireRole([Role::SUPER_ADMIN]);
        $r = UpdateService::backupNow();
        if ($r['ok']) {
            flash_set('success', sprintf(t('controllers/MaintenanceController.004', 'Δημιουργήθηκε αντίγραφο ασφαλείας: %s'), $r['name']));
        } else {
            flash_set('danger', sprintf(t('controllers/MaintenanceController.005', 'Αποτυχία backup: %s'), $r['error']));
        }
        redirect('/admin/settings#updates');
    }

    public function checkUpdate()
    {
        requireRole([Role::SUPER_ADMIN]);
        $_SESSION['update_check'] = UpdateService::checkLatest();
        redirect('/admin/settings#updates');
    }

    public function applyUpdate()
    {
        requireRole([Role::SUPER_ADMIN]);
        $r = UpdateService::applyUpdate();
        if ($r['ok']) {
            $msg = 'Η ενημέρωση ολοκληρώθηκε. Έκδοση: ' . $r['version']
                 . ' · Αρχεία: ' . $r['files']
                 . ' · Migrations: ' . (empty($r['migrated']) ? 'καμία' : implode(', ', $r['migrated']))
                 . ' · Backup: ' . $r['backup'];
            flash_set('success', $msg);
        } else {
            flash_set('danger', sprintf(t('controllers/MaintenanceController.006', 'Η ενημέρωση απέτυχε: %s'), $r['error']));
        }
        unset($_SESSION['update_check']);
        redirect('/admin/settings#updates');
    }

    public function downloadBackup()
    {
        requireRole([Role::SUPER_ADMIN]);
        $path = UpdateService::backupFile((string) ($_GET['file'] ?? ''));
        if ($path === null) {
            abort(404, 'Το backup δεν βρέθηκε.');
        }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function restoreBackup()
    {
        requireRole([Role::SUPER_ADMIN]);
        $r = UpdateService::restoreBackup(post_str('file'));
        if ($r['ok']) {
            flash_set('success', sprintf(t('controllers/MaintenanceController.007', 'Επαναφορά από backup «%s». Δημιουργήθηκε αυτόματα και νέο backup της προηγούμενης κατάστασης.'), $r['name']));
        } else {
            flash_set('danger', sprintf(t('controllers/MaintenanceController.008', 'Αποτυχία επαναφοράς: %s'), $r['error']));
        }
        redirect('/admin/settings#updates');
    }

    public function runMigrations()
    {
        requireRole([Role::SUPER_ADMIN]);
        $r = MigrationRunner::runPending();
        if ($r['error']) {
            flash_set('danger', sprintf(t('controllers/MaintenanceController.009', 'Migration απέτυχε: %s'), $r['error']));
        } elseif (empty($r['applied'])) {
            flash_set('info', t('controllers/MaintenanceController.001', 'Δεν υπήρχαν εκκρεμείς migrations.'));
        } else {
            flash_set('success', sprintf(t('controllers/MaintenanceController.010', 'Εφαρμόστηκαν migrations: %s'), implode(', ', $r['applied'])));
        }
        redirect('/admin/settings#updates');
    }

    /* ── Danger zone: reset all operational data ───────────────────────────── */

    public function resetData()
    {
        requireRole([Role::SUPER_ADMIN]);
        if (post_str('confirm') !== 'ΔΙΑΓΡΑΦΗ') {
            flash_set('danger', t('controllers/MaintenanceController.002', 'Η διαγραφή ακυρώθηκε — η λέξη επιβεβαίωσης δεν ταίριαξε.'));
            redirect('/admin/settings#danger');
            return;
        }

        /* Tables to wipe — all operational/event data, preserving users,
           teams, categories, templates, municipalities and platform settings. */
        $tables = [
            'event_application_members',
            'shift_applications',
            'event_shifts',
            'operational_checkins',
            'operational_notes',
            'shortage_reports',
            'sos_alerts',
            'location_pings',
            'gps_requests',
            'photo_requests',
            'event_photos',
            'event_messages',
            'event_room_messages',
            'event_reports',
            'team_debriefs',
            'volunteer_participations',
            'mobilization_responses',
            'mobilizations',
            'event_applications',
            'events',
            'notifications',
            'audit_logs',
            'password_resets',
        ];

        try {
            db()->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($tables as $t) {
                db()->exec('TRUNCATE TABLE `' . $t . '`');
            }
            db()->exec('SET FOREIGN_KEY_CHECKS = 1');
        } catch (Throwable $e) {
            db()->exec('SET FOREIGN_KEY_CHECKS = 1');
            flash_set('danger', sprintf(t('controllers/MaintenanceController.011', 'Σφάλμα κατά τη διαγραφή: %s'), $e->getMessage()));
            redirect('/admin/settings#danger');
            return;
        }

        flash_set('success', t('controllers/MaintenanceController.003', 'Όλα τα δεδομένα δράσεων, εκτάκτων και στατιστικών διαγράφηκαν. Χρήστες και ομάδες παρέμειναν άθικτοι.'));
        redirect('/admin/settings#danger');
    }
}
