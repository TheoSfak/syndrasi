<?php
/**
 * SynDrasi - Front controller.
 * Web root must point to this /public directory.
 */

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Helpers/functions.php';
require BASE_PATH . '/app/Helpers/Router.php';
require BASE_PATH . '/app/Middleware/access.php';

$appConfig = config('config');
date_default_timezone_set($appConfig['timezone']);

// Error logging always enabled; display only in development
ini_set('log_errors', '1');
if ($appConfig['env'] === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// Return 503 while an update is being applied
if (is_file(BASE_PATH . '/storage/maintenance.lock')) {
    http_response_code(503);
    header('Retry-After: 30');
    exit('Το σύστημα βρίσκεται σε συντήρηση. Παρακαλούμε δοκιμάστε ξανά σε λίγα λεπτά.');
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

/* Secure session */
session_name('syndrasi_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

/* Autoload application classes */
spl_autoload_register(function ($class) {
    foreach (['Controllers', 'Models', 'Services'] as $dir) {
        $file = BASE_PATH . '/app/' . $dir . '/' . $class . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

/* Composer autoload if present (PHPMailer etc.) */
if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
}

/* Global CSRF protection for all POST requests */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $isProtectedCronIngest = str_ends_with($requestPath, '/cron/fire-risk-map/ingest')
        || str_ends_with($requestPath, '/cron/fire-service/ingest');
    $token = '';
    if ($isProtectedCronIngest) {
        $token = csrf_token();
    } elseif (isset($_POST['_token'])) {
        $token = $_POST['_token'];
    } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    } else {
        $jsonBody = json_input();
        if (isset($jsonBody['_token'])) {
            $token = $jsonBody['_token'];
        }
    }
    if (!verify_csrf($token)) {
        if (wants_json()) {
            json_out(['success' => false, 'message' => 'Μη έγκυρο αίτημα ασφαλείας. Ανανεώστε τη σελίδα.'], 419);
        }
        http_response_code(419);
        exit('Μη έγκυρο αίτημα ασφαλείας. Παρακαλούμε ανανεώστε τη σελίδα και προσπαθήστε ξανά.');
    }
}

// Global exception handler — catches anything the router or controllers don't handle
set_exception_handler(function (Throwable $e) use ($appConfig) {
    error_log($e);
    if ($appConfig['env'] !== 'production') {
        http_response_code(500);
        echo '<pre>' . htmlspecialchars((string) $e) . '</pre>';
    } else {
        http_response_code(500);
        echo 'Σφάλμα συστήματος. Παρακαλούμε δοκιμάστε ξανά αργότερα.';
    }
});

$router = new Router();
require BASE_PATH . '/routes/web.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
