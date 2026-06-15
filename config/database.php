<?php
/**
 * SynDrasi - Database settings (MySQL 8 / MariaDB 10.5+).
 * Values can be overridden with environment variables.
 */
return [
    'host'     => env('DB_HOST', '127.0.0.1'),
    'port'     => env('DB_PORT', '3306'),
    'database' => env('DB_NAME', 'syndrasi'),
    'username' => env('DB_USER', 'root'),
    'password' => env('DB_PASS', ''),
    'charset'  => 'utf8mb4',
];
