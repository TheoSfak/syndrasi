<?php
/**
 * SynDrasi - Authentication, profile, home redirect.
 */
class AuthController
{
    public function home()
    {
        if (is_logged_in()) {
            redirect(role_home());
        }
        redirect('/login');
    }

    public function showLogin()
    {
        if (is_logged_in()) {
            redirect(role_home());
        }
        render('auth/login', ['pageTitle' => 'Σύνδεση'], false);
    }

    public function login()
    {
        $email    = post_str('email');
        $password = (string) (isset($_POST['password']) ? $_POST['password'] : '');

        if ($email === '' || $password === '') {
            flash_set('danger', 'Συμπληρώστε email και κωδικό πρόσβασης.');
            redirect('/login');
        }

        // ── Rate limiting: max 5 failures per IP+email per 15 minutes ──────────
        $ip         = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ratKey     = 'login_fail_' . md5($ip . '|' . mb_strtolower($email));
        $lockKey    = 'login_lock_' . md5($ip . '|' . mb_strtolower($email));
        $lockUntil  = $this->rateSetting($lockKey);

        if ($lockUntil && time() < (int) $lockUntil) {
            $minutes = ceil(((int) $lockUntil - time()) / 60);
            flash_set('danger', "Πολλές αποτυχημένες προσπάθειες. Δοκιμάστε ξανά σε {$minutes} λεπτό(α).");
            redirect('/login');
        }
        // ──────────────────────────────────────────────────────────────────────

        $user = User::findByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Increment failure counter
            $failures = (int) $this->rateSetting($ratKey) + 1;
            $this->setRateSetting($ratKey, $failures);
            if ($failures >= 5) {
                $this->setRateSetting($lockKey, time() + 900); // lock 15 min
                $this->setRateSetting($ratKey, 0);
                flash_set('danger', 'Πάρα πολλές αποτυχημένες προσπάθειες. Ο λογαριασμός κλειδώθηκε για 15 λεπτά.');
            } else {
                flash_set('danger', 'Λάθος email ή κωδικός πρόσβασης. (' . $failures . '/5 αποτυχίες)');
            }
            audit('login_failed', 'user', null, 'email: ' . $email);
            redirect('/login');
        }

        // Success — clear rate limit counters
        $this->setRateSetting($ratKey, 0);
        $this->setRateSetting($lockKey, 0);

        if ($user['status'] !== 'active') {
            flash_set('danger', 'Ο λογαριασμός σας είναι ανενεργός. Επικοινωνήστε με τον διαχειριστή.');
            redirect('/login');
        }

        // Municipality must be active (super admin excluded)
        if ($user['role'] !== 'super_admin' && $user['municipality_id']) {
            $mun = Municipality::find($user['municipality_id']);
            if (!$mun || $mun['status'] !== 'active') {
                flash_set('danger', 'Ο δήμος σας δεν είναι ενεργός στην πλατφόρμα.');
                redirect('/login');
            }
        }

        session_regenerate_id(true);
        $_SESSION['user_id']        = (int) $user['id'];
        $_SESSION['role']           = $user['role'];
        $_SESSION['municipality_id'] = $user['municipality_id'] !== null ? (int) $user['municipality_id'] : null;
        $_SESSION['team_id']        = $user['team_id'] !== null ? (int) $user['team_id'] : null;

        User::updateLastLogin($user['id']);
        audit('login', 'user', $user['id']);

        flash_set('success', 'Καλώς ήρθατε, ' . $user['name'] . '!');
        redirect(role_home());
    }

    // ── Rate-limit helpers (app_settings table) ────────────────────────────

    private function rateSetting(string $key): ?string
    {
        $val = dbq(
            "SELECT setting_value FROM app_settings WHERE setting_key = :k LIMIT 1",
            