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
        requireRole(['super_admin']);
        $r = MaintenanceService::cleanup();
        flash_set('success', sprintf(
            'Καθαρισμός ολοκληρώθηκε: %d εγγραφές rate-limit, %d σημαίες υπενθύμισης, %d tokens επαναφοράς διαγράφηκαν.',
            $r['rate_limit_removed'], $r['shift_flags_removed'], $r['reset_tokens_removed']
        ));
        redirect('/admin/settings#cron');
    }

    /* ── Updates ───────────────────────────────────────────────────────────── */

    public function backup()
    {
        requireRole(['super_admin']);
        $r = UpdateService::backupNow();
        if ($r['ok']) {
            flash_set('success', 'Δημιουργήθηκε αντίγραφο ασφαλείας: ' . $r['name']);
        } else {
            flash_set('danger', 'Αποτυχία backup: ' . $r['error']);
        }
        redirect('/admin/settings#updates');
    }

    public function checkUpdate()
    {
        requireRole(['super_admin']);
        $_SESSION['update_check'] = UpdateService::checkLatest();
        redirect('/admin/settings#updates');
    }

    public function applyUpdate()
    {
        requireRole(['super_admin']);
        $r = UpdateService::applyUpdate();
        if ($r['ok']) {
            $msg = 'Η ενημέρωση ολοκληρώθηκε. Έκδοση: ' . $r['version']
                 . ' · Αρχεία: ' . $r['files']
                 . ' · Migrations: ' . (empty($r['migrated']) ? 'καμία' : implode(', ', $r['migrated']))
                 . ' · Backup: ' . $r['backup'];
            flash_set('success', $msg);
        } else {
            flash_set('danger', 'Η ενημέρωση απέτυχε: ' . $r['error']);
        }
        unset($_SESSION['update_check']);
        redirect('/admin/settings#updates');
    }

    public function downloadBackup()
    {
        requireRole(['super_admin']);
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
        requireRole(['super_admin']);
        $r = UpdateService::restoreBackup(post_str('file'));
        if ($r['ok']) {
            flash_set('success', 'Επαναφορά από backup «' . $r['name']
                . '». Δημιουργήθηκε αυτόματα και νέο backup της προηγούμενης κατάστασης.');
        } else {
            flash_set('danger', 'Αποτυχία επαναφοράς: ' . $r['error']);
        }
        redirect('/admin/settings#updates');
    }

    public function runMigrations()
    {
        requireRole(['super_admin']);
        $r = MigrationRunner::runPending();
        if ($r['error']) {
            flash_set('danger', 'Migration απέτυχε: ' . $r['error']);
        } elseif (empty($r['applied'])) {
            flash_set('info', 'Δεν υπήρχαν εκκρεμείς migrations.');
        } else {
            flash_set('success', 'Εφαρμόστηκαν migrations: ' . implode(', ', $r['applied']));
        }
        redirect('/admin/settings#updates');
    }
}
