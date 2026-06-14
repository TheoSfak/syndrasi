<?php
/**
 * SynDrasi - Database settings (MySQL 8 / MariaDB 10.5+).
 * Values can be overridden with environment variables.
 */
return [
    'host'     => getenv('DB_HOST') !== false ? getenv('DB_HOST') : '127.0.0.1',
    'port'     => getenv('DB_PORT') !== false ? getenv('DB_PORT') : '3306',
    'database' => getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'syndrasi',
    'username' => getenv('DB_USER') !== false ? getenv('DB_USER') : 'root',
    'password' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
    'charset'  => 'utf8mb4',
];
