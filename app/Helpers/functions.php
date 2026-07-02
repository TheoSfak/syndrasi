<?php
/**
 * SynDrasi - Global helper functions.
 */

/* ---------------------------------------------------------------- Config */

function config($file)
{
    static $cache = [];
    if (!isset($cache[$file])) {
        $cache[$file] = require BASE_PATH . '/config/' . $file . '.php';
    }
    return $cache[$file];
}

/** Read an environment variable with a fallback default. */
function env($key, $default = null)
{
    $val = getenv($key);
    return $val !== false ? $val : $default;
}

/* -------------------------------------------------------------- Database */

function db()
{
    static $pdo = null;
    if ($pdo === null) {
        $cfg = config('database');
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $cfg['host'], $cfg['port'], $cfg['database'], $cfg['charset']
        );
        $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Sync MySQL session timezone to PHP's timezone so DATETIME values
        // are read/written consistently (prevents 3-hour UTC/Athens mismatch).
        $pdo->exec("SET time_zone = '" . (new DateTime('now'))->format('P') . "'");
    }
    return $pdo;
}

/** Run a prepared query and return the statement. */
function dbq($sql, array $params = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/* -------------------------------------------------------------- Escaping */

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* ------------------------------------------------------------------ URLs */

/** URI prefix when the app is not served from the domain root. */
function base_uri()
{
    static $base = null;
    if ($base === null) {
        $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/index.php';
        $dir = str_replace('\\', '/', dirname($script));
        $base = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
    }
    return $base;
}

function url($path = '/')
{
    return base_uri() . '/' . ltrim($path, '/');
}

function redirect($path): never
{
    header('Location: ' . url($path));
    exit;
}

function wants_json()
{
    $accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
    $xhr    = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? $_SERVER['HTTP_X_REQUESTED_WITH'] : '';
    $ctype  = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    return strpos($accept, 'application/json') !== false
        || strtolower($xhr) === 'xmlhttprequest'
        || strpos($ctype, 'application/json') !== false;
}

function json_out($data, $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Read JSON request body as array. */
function json_input()
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/* ------------------------------------------------------------------ CSRF */

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf($token)
{
    return isset($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field()
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/* ----------------------------------------------------------------- Flash */

function flash_set($type, $message)
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get()
{
    $msgs = isset($_SESSION['flash']) ? $_SESSION['flash'] : [];
    unset($_SESSION['flash']);
    return $msgs;
}

/* ------------------------------------------------------------- Old input */

function remember_old()
{
    $_SESSION['old_input'] = $_POST;
}

function old($key, $default = '')
{
    if (isset($_SESSION['old_input'][$key])) {
        return $_SESSION['old_input'][$key];
    }
    return $default;
}

function clear_old()
{
    unset($_SESSION['old_input']);
}

/* ------------------------------------------------------------------ Auth */

function is_logged_in()
{
    return !empty($_SESSION['user_id']);
}

function current_user()
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (is_logged_in()) {
            $user = dbq(
                'SELECT id, name, email, role, municipality_id, team_id, status, last_login_at, created_at
                 FROM users WHERE id = :id AND status = :st LIMIT 1',
                ['id' => $_SESSION['user_id'], 'st' => 'active']
            )->fetch();
            if (!$user) {
                session_unset();
                session_destroy();
            }
        }
    }
    return $user ?: null;
}

function current_user_id(): ?int
{
    return is_logged_in() ? (int) $_SESSION['user_id'] : null;
}

function current_role()
{
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

function current_municipality_id()
{
    return isset($_SESSION['municipality_id']) && $_SESSION['municipality_id'] !== null
        ? (int) $_SESSION['municipality_id'] : null;
}

function current_team_id()
{
    return isset($_SESSION['team_id']) && $_SESSION['team_id'] !== null
        ? (int) $_SESSION['team_id'] : null;
}

/* --------------------------------------------------------- Authority terms */

function authority_options(): array
{
    return [
        'municipality' => [
            'label' => 'Δήμος',
            'prefix' => 'Δήμος',
            'short' => 'Δήμος',
            'icon' => '🏛️',
            'event_singular' => 'Δράση',
            'event_plural' => 'Δράσεις',
            'event_plural_lc' => 'δράσεις',
            'event_new' => 'Νέα Δράση',
            'team_plural' => 'Εθελοντικές Ομάδες',
            'team_singular' => 'Εθελοντική Ομάδα',
            'admin_role' => 'Διαχειριστής Δήμου',
        ],
        'civil_protection' => [
            'label' => 'Πολιτική Προστασία',
            'prefix' => 'Πολιτική Προστασία',
            'short' => 'Πολ. Προστ.',
            'icon' => '🛡️',
            'event_singular' => 'Αποστολή',
            'event_plural' => 'Αποστολές',
            'event_plural_lc' => 'αποστολές',
            'event_new' => 'Νέα Αποστολή',
            'team_plural' => 'Ομάδες Πολιτικής Προστασίας',
            'team_singular' => 'Ομάδα Πολιτικής Προστασίας',
            'admin_role' => 'Διαχειριστής Φορέα',
        ],
        'fire_service' => [
            'label' => 'Πυροσβεστική',
            'prefix' => 'Πυροσβεστική',
            'short' => 'Πυρ/κή',
            'icon' => '🚒',
            'event_singular' => 'Αποστολή',
            'event_plural' => 'Αποστολές',
            'event_plural_lc' => 'αποστολές',
            'event_new' => 'Νέα Αποστολή',
            'team_plural' => 'Ομάδες / Κλιμάκια',
            'team_singular' => 'Ομάδα / Κλιμάκιο',
            'admin_role' => 'Διαχειριστής Φορέα',
        ],
        'coast_guard' => [
            'label' => 'Λιμενικό',
            'prefix' => 'Λιμενικό',
            'short' => 'Λιμενικό',
            'icon' => '⚓',
            'event_singular' => 'Αποστολή',
            'event_plural' => 'Αποστολές',
            'event_plural_lc' => 'αποστολές',
            'event_new' => 'Νέα Αποστολή',
            'team_plural' => 'Ομάδες Διάσωσης',
            'team_singular' => 'Ομάδα Διάσωσης',
            'admin_role' => 'Διαχειριστής Φορέα',
        ],
    ];
}

function normalize_authority_type($type): string
{
    return array_key_exists((string) $type, authority_options()) ? (string) $type : 'municipality';
}

function authority_defaults(string $type): array
{
    $options = authority_options();
    return $options[normalize_authority_type($type)];
}

function authority_context($municipalityId = null): array
{
    $mid = $municipalityId !== null ? (int) $municipalityId : current_municipality_id();
    $fallbackName = '';
    $type = 'municipality';
    $official = '';
    $short = '';

    if ($mid) {
        try {
            $row = dbq(
                'SELECT name, authority_type, official_name, short_name FROM municipalities WHERE id = :id LIMIT 1',
                ['id' => $mid]
            )->fetch();
            if ($row) {
                $fallbackName = (string) ($row['name'] ?? '');
                $type = normalize_authority_type($row['authority_type'] ?? 'municipality');
                $official = trim((string) ($row['official_name'] ?? ''));
                $short = trim((string) ($row['short_name'] ?? ''));
            }
        } catch (Throwable $e) {
            try {
                $settings = MunicipalitySetting::all($mid);
                $fallbackName = (string) (dbq('SELECT name FROM municipalities WHERE id = :id LIMIT 1', ['id' => $mid])->fetchColumn() ?: '');
                $type = normalize_authority_type($settings['org_type'] ?? 'municipality');
                $official = trim((string) ($settings['org_name'] ?? ''));
                $short = trim((string) ($settings['org_name_short'] ?? ''));
            } catch (Throwable $inner) { /* use defaults */ }
        }
    }

    $defs = authority_defaults($type);
    if ($official === '') {
        $official = $fallbackName !== '' ? $defs['prefix'] . ' ' . $fallbackName : $defs['prefix'];
    }
    if ($short === '') {
        $short = $defs['short'];
    }

    return array_merge($defs, [
        'authority_type' => $type,
        'municipality_id' => $mid,
        'base_name' => $fallbackName,
        'official_name' => $official,
        'short_name' => $short,
    ]);
}

function org_term(string $key, $municipalityId = null): string
{
    $ctx = authority_context($municipalityId);
    return (string) ($ctx[$key] ?? '');
}

/** Route name of the dashboard for the current role. */
function role_home()
{
    switch (current_role()) {
        case 'super_admin':        return '/admin/dashboard';
        case 'team_admin':         return '/team/dashboard';
        case 'event_operator':
        case 'municipality_admin': return '/dashboard';
        default:                   return '/login';
    }
}

/* ----------------------------------------------------------------- Audit */

function audit($action, $entityType = null, $entityId = null, $details = null)
{
    try {
        dbq(
            'INSERT INTO audit_logs (municipality_id, user_id, action, entity_type, entity_id, details, ip_address, user_agent)
             VALUES (:mid, :uid, :action, :etype, :eid, :details, :ip, :ua)',
            [
                'mid'     => current_municipality_id(),
                'uid'     => current_user_id(),
                'action'  => $action,
                'etype'   => $entityType,
                'eid'     => $entityId,
                'details' => $details !== null ? (is_string($details) ? $details : json_encode($details, JSON_UNESCAPED_UNICODE)) : null,
                'ip'      => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null,
                'ua'      => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
            ]
        );
    } catch (Exception $ex) {
        error_log('Audit log failed: ' . $ex->getMessage());
    }
}

/* ----------------------------------------------------------------- Views */

function render($view, array $data = [], $withLayout = true): never
{
    $config = config('config');
    $needsLeaflet = $data['needsLeaflet'] ?? in_array($view, ['events/show', 'team/event_show'], true);
    $needsChart = $data['needsChart'] ?? in_array($view, ['dashboard/municipality', 'statistics/index'], true);
    $needsSimpleMaps = $data['needsSimpleMaps'] ?? $needsLeaflet;
    $needsStatsCharts = $data['needsStatsCharts'] ?? ($view === 'statistics/index');
    extract($data, EXTR_SKIP);
    if ($withLayout) {
        include BASE_PATH . '/views/layouts/header.php';
    }
    include BASE_PATH . '/views/' . $view . '.php';
    if ($withLayout) {
        include BASE_PATH . '/views/layouts/footer.php';
    }
    clear_old();
    exit;
}

function abort($code, $message = null): never
{
    http_response_code($code);
    if (wants_json()) {
        json_out(['success' => false, 'message' => $message ?: 'Σφάλμα'], $code);
    }
    render('errors/error', ['code' => $code, 'message' => $message], is_logged_in());
}

/* --------------------------------------------------------- Greek labels */

function greek_status($status)
{
    static $map = [
        // events
        'draft'           => 'Πρόχειρη',
        'open'            => 'Ανοιχτή',
        'review'          => 'Σε έγκριση',
        'confirmed'       => 'Οριστικοποιημένη',
        'active'          => 'Ενεργή',
        'closed'          => 'Σε αρχειοθέτηση',
        'completed'       => 'Ολοκληρωμένη',
        'cancelled'       => 'Ακυρωμένη',
        // applications
        'pending'         => 'Εκκρεμεί',
        'approved'        => 'Εγκρίθηκε',
        'rejected'        => 'Απορρίφθηκε',
        // check-ins
        'present_full'    => 'Παρών',
        'present_partial' => 'Παρών με ελλείψεις',
        'not_present'     => 'Δεν προσήλθε',
        'departed'        => 'Αποχώρησε',
        // shortages
        'open_shortage'   => 'Ανοιχτή',
        'acknowledged'    => 'Ελήφθη',
        'resolved'        => 'Επιλύθηκε',
        // generic
        'active_generic'  => 'Ενεργή',
        'inactive'        => 'Ανενεργή',
    ];
    return isset($map[$status]) ? $map[$status] : $status;
}

function status_color($status)
{
    static $map = [
        'draft' => 'secondary', 'open' => 'primary', 'review' => 'info',
        'confirmed' => 'success', 'active' => 'warning', 'closed' => 'warning',
        'completed' => 'success', 'cancelled' => 'danger',
        'pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger',
        'present_full' => 'success', 'present_partial' => 'warning',
        'not_present' => 'secondary', 'departed' => 'dark',
        'acknowledged' => 'info', 'resolved' => 'success',
        'low' => 'secondary', 'medium' => 'info', 'high' => 'warning', 'critical' => 'danger',
    ];
    return isset($map[$status]) ? $map[$status] : 'secondary';
}

function status_badge($status)
{
    return '<span class="badge text-bg-' . e(status_color($status)) . '">' . e(greek_status($status)) . '</span>';
}

function shortage_type_label($type)
{
    static $map = [
        'people'           => 'Άτομα',
        'equipment'        => 'Εξοπλισμός',
        'medical_supplies' => 'Υγειονομικό υλικό',
        'vehicle'          => 'Όχημα',
        'other'            => 'Άλλο',
    ];
    return isset($map[$type]) ? $map[$type] : $type;
}

function severity_label($severity)
{
    static $map = ['low' => 'Χαμηλή', 'medium' => 'Μεσαία', 'high' => 'Υψηλή', 'critical' => 'Κρίσιμη'];
    return isset($map[$severity]) ? $map[$severity] : $severity;
}

/* ------------------------------------------------------------ Formatting */

function gr_date($datetime)
{
    if (!$datetime) return '—';
    $ts = strtotime($datetime);
    return $ts ? date('d/m/Y', $ts) : '—';
}

function gr_datetime($datetime)
{
    if (!$datetime) return '—';
    $ts = strtotime($datetime);
    return $ts ? date('d/m/Y H:i', $ts) : '—';
}

function gr_time($datetime)
{
    if (!$datetime) return '—';
    $ts = strtotime($datetime);
    return $ts ? date('H:i', $ts) : '—';
}

function gr_number($num, $decimals = 0)
{
    return number_format((float) $num, $decimals, ',', '.');
}

/* ------------------------------------------------------------ Validation */

/**
 * Validate a password (and optionally its confirmation).
 * Returns a Greek error message, or null when the password is acceptable.
 * Pass $confirm = null to skip the match check (e.g. admin-set passwords).
 */
function password_error($password, $confirm = null, $min = 8)
{
    if (mb_strlen((string) $password) < $min) {
        return 'Ο κωδικός πρέπει να έχει τουλάχιστον ' . $min . ' χαρακτήρες.';
    }
    if ($confirm !== null && $password !== $confirm) {
        return 'Οι κωδικοί δεν ταιριάζουν.';
    }
    return null;
}

function post_str($key, $default = '')
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

function post_int($key, $default = 0)
{
    return isset($_POST[$key]) && $_POST[$key] !== '' ? (int) $_POST[$key] : $default;
}

function post_bool($key)
{
    return !empty($_POST[$key]) ? 1 : 0;
}

function post_float_or_null($key)
{
    if (!isset($_POST[$key]) || trim((string) $_POST[$key]) === '') return null;
    return (float) str_replace(',', '.', (string) $_POST[$key]);
}

function greek_month_short(int $month): string
{
    return ['Ιαν','Φεβ','Μαρ','Απρ','Μάι','Ιουν','Ιουλ','Αυγ','Σεπ','Οκτ','Νοε','Δεκ'][$month - 1] ?? '';
}
