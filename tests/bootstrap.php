<?php
/**
 * PHPUnit bootstrap. The app has no composer autoloading of its own
 * (deliberately dependency-free at runtime — see phpstan-bootstrap.php),
 * so tests get the same manual autoloader public/index.php registers.
 */
define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';
require BASE_PATH . '/app/Helpers/functions.php';

spl_autoload_register(function ($class) {
    foreach (['Controllers', 'Models', 'Services'] as $dir) {
        $file = BASE_PATH . '/app/' . $dir . '/' . $class . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});
