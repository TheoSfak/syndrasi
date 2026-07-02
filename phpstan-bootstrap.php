<?php
/**
 * PHPStan static-analysis bootstrap.
 * The app has no composer autoloading; declare BASE_PATH and load the
 * global helper functions so PHPStan can resolve calls to them.
 */
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

require_once __DIR__ . '/app/Helpers/functions.php';
require_once __DIR__ . '/app/Middleware/access.php';
