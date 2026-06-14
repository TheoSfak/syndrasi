<?php
/**
 * SynDrasi - Super admin: municipalities, users, settings, global stats.
 */
class AdminController
{
    public function dashboard()
    {
        requireRole(['super_admin']);

        $counts = [
            'municipalities' => (int) dbq('SELECT COUNT(*) FROM municipalities')->fetchColumn(),
            'active_municipalities' => (int) dbq("SELECT COUNT(*) FROM municipalities WHERE status = 'active'")->fetchColumn(),
            'teams'  => (int) dbq('SELECT COUNT(*) FROM volunteer_teams')->fetchColumn(),
            'users'  => (int) dbq('SELECT COUNT(*) FROM users')->fetchColumn(),
            'events' => (int) dbq('SELECT COUNT(*) FROM events')->fetchColumn(),
            'events_year' => (int) dbq('SELECT COUNT(*) FROM events WHERE YEAR(start_datetime) = YEAR(NOW())')->fetchColumn(),
            'applications' => (int) dbq('SELECT COUNT(*) FROM event_applications')->fetchColumn(),
        ];

        $municipalityUsage = dbq(
            "SELECT m.id, m.name, m.status,
                    (SELECT COUNT(*) FROM volunteer_teams t WHERE t.municipality_id = m.id) AS teams_count,
                    (SELECT COUNT(*) FROM events e WHERE e.municipality_id = m.id) AS events_count,
                    (SELECT COUNT(*) FROM users u WHERE u.municipality_id = m.id) AS users_count,
                    COALESCE((
                        SELECT SUM(TIMESTAMPDIFF(MINUTE,ev.start_datetime,ev.end_datetime)/60 * ea.approved_people)
                        FROM event_applications ea
                        JOIN events ev ON ev.id = ea.event_id
                        WHERE ea.municipality_id = m.id AND ea.status = 'approved' AND ev.status = 'completed'
                    ), 0) AS volunteer_hours
             FROM municipalities m ORDER BY m.name"
        )->fetchAll();

        $recentAudit = dbq(
            'SELECT a.*, u.name AS user_name FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.created_at DESC LIMIT 15'
        )->fetchAll();

        render('dashboard/admin', [
            'pageTitle'         => 'Πίνακας Ελέγχου Πλατφόρμας',
            'counts'            => $counts,
            'municipalityUsage' => $municipalityUsage,
            'recentAudit'       => $recentAudit,
        ]);
    }

    /* ------------------------------------------- Impersonation (support) */

    public function impersonate($userId)
    {
        requireRole(['super_admin']);
        if (isset($_SESSION['impersonating_user_id'])) {
            flash_set('danger', 'Είστε ήδη σε λειτουργία impersonation.');
            redirect('/admin/dashboard');
        }
        $target = User::find($userId);
        if (!$target || $target['role'] === 'super_admin') {
            flash_set('danger', 'Δεν επιτρέπεται impersonation αυτού του χρήστη.');
            redirect('/admin/users');
        }
        $_SESSION['impersonating_user_id']   = $_SESSION['user_id'];
        $_SESSION['impersonating_user_name']  = User::find(current_user_id())['name'] ?? 'Super Admin';
        $_SESSION['user_id']        = (int) $target['id'];
        $_SESSION['role']           = $target['role'];
        $_SESSION['municipality_id']= $target['municipality_id'];
        $_SESSION['team_id']        = $target['team_id'];
        audit('impersonation_start', 'user', (int) $target['id'], 'impersonator: ' . $_SESSION['impersonating_user_id']);
        flash_set('warning', 'Λειτουργείτε ως ' . $target['name'] . '. Κάντε κλικ στο "Επιστροφή" για να επιστρέψετε.');
        redirect('/');
    }

    public function stopImpersonation()
    {
        if (empty($_SESSION['impersonating_user_id'])) {
            redirect('/');
        }
        $origId = (int) $_SESSION['impersonating_user_id'];
        $orig   = User::find($origId);
        if (!$orig) { session_destroy(); redirect('/login'); }
        audit('impersonation_end', 'user', current_user_id());
        $_SESSION['user_id']         = $orig['id'];
        $_SESSION['role']            = $orig['role'];
        $_SESSION['municipality_id'] = $orig['municipality_id'];
        $_SESSION['team_id']         = $orig['team_id'];
        unset($_SESSION['impersonating_user_id'], $_SESSION['impersonating_user_name']);
        flash_set('success', 'Επιστρέψατε στον λογαριασμό Super Admin.');
        redirect('/admin/users');
    }

    /* -------------------------------------------------------- Municipalities */

    public function municipalities()
    {
        requireRole(['super_admin']);
        render('municipalities/index', [
            'pageTitle'      => 'Δήμοι',
            'municipalities' => Municipality::all(),
        ]);
    }

    public function storeMunicipality()
    {
        requireRole(['super_admin']);
        $name = post_str('name');
        if ($name === '') {
            flash_set('danger', 'Το όνομα του δήμου είναι υποχρεωτικό.');
            redirect('/admin/municipalities');
        }
        $id = Municipality::create([
            'name' => $name,
            'city' => post_str('city') ?: null,
            'address' => post_str('address') ?: null,
            'email' => post_str('email') ?: null,
            'phone' => post_str('phone') ?: null,
            'status' => 'active',
        ]);
        audit('municipality_created', 'municipality', $id, $name);
        flash_set('success', 'Ο δήμος δημιουργήθηκε.');
        redirect('/admin/municipalities');
    }

    public function updateMunicipality($id)
    {
        requireRole(['super_admin']);
        $m = Municipality::find($id);
        if (!$m) {
            abort(404, 'Ο δήμος δεν βρέθηκε.');
        }
        $name = post_str('name');
        if ($name === '') {
            flash_set('danger', 'Το όνομα του δήμου είναι υποχρεωτικό.');
            redirect('/admin/municipalities');
        }
        Municipality::update($m['id'], [
            'name' => $name,
            'city' => post_str('city') ?: null,
            'address' => post_str('address') ?: null,
            'email' => post_str('email') ?: null,
            'phone' => post_str('phone') ?: null,
            'status' => post_str('status') === 'inactive' ? 'inactive' : 'active',
        ]);
        audit('municipality_updated', 'municipality', $m['id'], $name);
        flash_set('success', 'Ο δήμος ενημερώθηκε.');
        redirect('/admin/municipalities');
    }

    public function toggleMunicipality($id)
    {
        requireRole(['super_admin']);
        $m = Municipality::find($id);
        if (!$m) {
            abort(404, 'Ο δήμος δεν βρέθηκε.');
        }
        dbq(
            "UPDATE municipalities SET status = IF(status = 'active', 'inactive', 'active') WHERE id = :id",
            ['id' => $m['id']]
        );
        audit('municipality_status_toggled', 'municipality', $m['id']);
        flash_set('success', 'Η κατάσταση του δήμου άλλαξε.');
        redirect('/admin/municipalities');
    }

    /** Municipality detail page */
    public function showMunicipality($id)
    {
        requireRole(['super_admin']);
        $m = Municipality::find($id);
        if (!$m) { abort(404, 'Ο δήμος δεν βρέθηκε.'); }

        $users = dbq(
            'SELECT u.*, t.name AS team_name FROM users u
             LEFT JOIN volunteer_teams t ON t.id = u.team_id
             WHERE u.municipality_id = :mid ORDER BY u.role, u.name',
            ['mid' => $id]
        )->fetchAll();

        $teams = dbq(
            'SELECT * FROM volunteer_teams WHERE municipality_id = :mid ORDER BY name',
            ['mid' => $id]
        )->fetchAll();

        $stats = [
            'events_total'   => (int) dbq('SELECT COUNT(*) FROM events WHERE municipality_id = :mid', ['mid' => $id])->fetchColumn(),
            'events_active'  => (int) dbq("SELECT COUNT(*) FROM events WHERE municipality_id = :mid AND status = 'active'", ['mid' => $id])->fetchColumn(),
            'events_year'    => (int) dbq('SELECT COUNT(*) FROM events WHERE municipality_id = :mid AND YEAR(start_datetime) = YEAR(NOW())', ['mid' => $id])->fetchColumn(),
            'applications'   => (int) dbq('SELECT COUNT(*) FROM event_applications WHERE municipality_id = :mid', ['mid' => $id])->fetchColumn(),
            'approved'       => (int) dbq("SELECT COUNT(*) FROM event_applications WHERE municipality_id = :mid AND status = 'approved'", ['mid' => $id])->fetchColumn(),
            'volunteer_hours'=> round((float) dbq(
                "SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE,e.start_datetime,e.end_datetime)/60 * ea.approved_people),0)
                 FROM event_applications ea JOIN events e ON e.id=ea.event_id
      