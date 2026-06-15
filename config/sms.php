<?php
/**
 * SynDrasi - SMS settings.
 *
 * driver:
 *   'log'  -> writes messages to storage/logs/sms.log (default, needs nothing)
 *   'http' -> POST to a generic SMS gateway (set SMS_ENDPOINT + SMS_API_KEY)
 *   'none' -> SMS disabled
 */
return [
    'driver'   => env('SMS_DRIVER', 'log'),
    'sender'   => env('SMS_SENDER', 'SynDrasi'),
    'endpoint' => env('SMS_ENDPOINT', ''),
    'api_key'  => env('SMS_API_KEY', ''),
];
