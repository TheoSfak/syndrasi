<?php
/**
 * SynDrasi - General application settings.
 */
return [
    'app_name'    => 'SynDrasi',
    // development | production. Set APP_ENV=development locally; defaults to
    // production so a deployment never leaks errors if the var is unset.
    'env'         => env('APP_ENV', 'production'),
    'timezone'    => 'Europe/Athens',
    'footer_text' => ' Σφακιανάκης Θεόδωρος | email: theodore.sfakianakis@gmail.com | κιν. 6945139015',
    'map_refresh_seconds' => 45,
];
