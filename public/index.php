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

if ($appConfig['env'] === 'production') {
    ini_set('display_errors', '0');
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

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
    $token = '';
    if (isset($_POST['_token'])) {
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

$router = new Router();
require BASE_PATH . '/routes/web.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
