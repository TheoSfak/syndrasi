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
            ['k' => $key]
        )->fetchColumn();
        return $val !== false ? (string) $val : null;
    }

    private function setRateSetting(string $key, $value): void
    {
        dbq(
            "INSERT INTO app_settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = :v2",
            ['k' => $key, 'v' => $value, 'v2' => $value]
        );
    }

    public function logout()
    {
        requireLogin();
        audit('logout', 'user', $_SESSION['user_id']);
        session_unset();
        session_destroy();
        redirect('/login');
    }

    // ── Forgot / Reset password ────────────────────────────────────────────────

    public function showForgotPassword()
    {
        if (is_logged_in()) redirect(role_home());
        render('auth/forgot_password', ['pageTitle' => 'Επαναφορά κωδικού'], false);
    }

    public function sendResetLink()
    {
        if (is_logged_in()) redirect(role_home());
        $email = mb_strtolower(trim(post_str('email')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('danger', 'Συμπληρώστε έγκυρο email.');
            redirect('/forgot-password');
        }

        // Always show the same message to prevent email enumeration
        $user = User::findByEmail($email);
        if ($user && $user['status'] === 'active') {
            dbq("DELETE FROM password_resets WHERE email = :email", ['email' => $email]);
            $token = bin2hex(random_bytes(32));
            dbq("INSERT INTO password_resets (email, token) VALUES (:email, :token)",
                ['email' => $email, 'token' => $token]);

            $resetUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                . url('/reset-password') . '?token=' . urlencode($token);

            $municipality = $user['municipality_id'] ? Municipality::find($user['municipality_id']) : null;
            $munName = $municipality['name'] ?? 'SynDrasi';

            MailService::send(
                $email,
                $user['name'],
                'Επαναφορά κωδικού πρόσβασης — SynDrasi',
                "<p>Λάβαμε αίτημα επαναφοράς κωδικού για τον λογαριασμό σας.</p>
                 <p>Κάντε κλικ στον παρακάτω σύνδεσμο για να ορίσετε νέο κωδικό:</p>
                 <p><a href=\"{$resetUrl}\">{$resetUrl}</a></p>
                 <p>Ο σύνδεσμος ισχύει για <strong>1 ώρα</strong>.</p>
                 <p>Αν δεν ζητήσατε αυτήν την αλλαγή, αγνοήστε αυτό το email.</p>",
                $munName,
                $user['municipality_id']
            );
            audit('password_reset_requested', 'user', $user['id']);
        }

        flash_set('success', 'Αν το email υπάρχει στο σύστημα, θα λάβετε οδηγίες επαναφοράς αμέσως.');
        redirect('/forgot-password');
    }

    public function showResetForm()
    {
        if (is_logged_in()) redirect(role_home());
        $token = isset($_GET['token']) ? trim($_GET['token']) : '';
        if (!$this->validResetToken($token)) {
            flash_set('danger', 'Ο σύνδεσμος επαναφοράς είναι άκυρος ή έχει λήξει.');
            redirect('/forgot-password');
        }
        render('auth/reset_password', ['pageTitle' => 'Νέος κωδικός', 'token' => $token], false);
    }

    public function doResetPassword()
    {
        if (is_logged_in()) redirect(role_home());
        $token    = post_str('token');
        $password = (string) (isset($_POST['password']) ? $_POST['password'] : '');
        $confirm  = (string) (isset($_POST['confirm'])  ? $_POST['confirm']  : '');

        $row = $this->validResetToken($token);
        if (!$row) {
            flash_set('danger', 'Ο σύνδεσμος επαναφοράς είναι άκυρος ή έχει λήξει.');
            redirect('/forgot-password');
        }
        if ($pwErr = password_error($password, $confirm)) {
            flash_set('danger', $pwErr);
            redirect('/reset-password?token=' . urlencode($token));
        }

        $user = User::findByEmail($row['email']);
        if (!$user) {
            flash_set('danger', 'Σφάλμα: ο χρήστης δεν βρέθηκε.');
            redirect('/forgot-password');
        }

        User::updatePassword($user['id'], password_hash($password, PASSWORD_DEFAULT));
        dbq("UPDATE password_resets SET used_at = NOW() WHERE token = :token", ['token' => $token]);
        audit('password_reset_completed', 'user', $user['id']);
        flash_set('success', 'Ο κωδικός σας άλλαξε επιτυχώς. Συνδεθείτε τώρα.');
        redirect('/login');
    }

    /** Returns the reset row if token is valid + unused + < 1 hour old, else false. */
    private function validResetToken(string $token): array|false
    {
        if (strlen($token) !== 64) return false;
        $row = dbq(
            "SELECT * FROM password_resets
             WHERE token = :token AND used_at IS NULL
               AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
             LIMIT 1",
            ['token' => $token]
        )->fetch();
        return $row ?: false;
    }

    public function profile()
    {
        requireLogin();
        $user = current_user();
        $municipality = $user['municipality_id'] ? Municipality::find($user['municipality_id']) : null;
        $team = $user['team_id'] ? VolunteerTeam::find($user['team_id']) : null;
        render('auth/profile', [
            'pageTitle'    => 'Το προφίλ μου',
            'user'         => $user,
            'municipality' => $municipality,
            'team'         => $team,
        ]);
    }

    public function changePassword()
    {
        requireLogin();
        $user = current_user();
        $current = (string) (isset($_POST['current_password']) ? $_POST['current_password'] : '');
        $new = (string) (isset($_POST['new_password']) ? $_POST['new_password'] : '');
        $confirm = (string) (isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '');

        if (!password_verify($current, $user['password_hash'])) {
            flash_set('danger', 'Ο τρέχων κωδικός δεν είναι σωστός.');
            redirect('/profile');
        }
        if ($pwErr = password_error($new, $confirm)) {
            flash_set('danger', $pwErr);
            redirect('/profile');
        }

        User::updatePassword($user['id'], password_hash($new, PASSWORD_DEFAULT));
        audit('password_changed', 'user', $user['id']);
        flash_set('success', 'Ο κωδικός πρόσβασης άλλαξε με επιτυχία.');
        redirect('/profile');
    }
}
