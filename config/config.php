<?php

return [

    /*
    |--------------------------------------------------------------------------
    | PHP Binary Path
    |--------------------------------------------------------------------------
    |
    | This value determines the PHP binary that will be used to execute
    | background processes via the Flyron system. You may override
    | it in your .env file using the key FLYRON_PHP_PATH.
    |
    */

    'php_path' => env("FLYRON_PHP_PATH", PHP_BINARY),

    /*
    |--------------------------------------------------------------------------
    | Artisan File Path
    |--------------------------------------------------------------------------
    |
    | This value determines the path to the Artisan CLI file that should be
    | used when dispatching background commands. In most cases, it should
    | not be changed unless your Laravel root is not standard.
    |
    */

    'artisan_path' => env('FLYRON_ARTISAN_PATH', base_path('artisan')),

    /*
    |--------------------------------------------------------------------------
    | Scheduling Configuration
    |--------------------------------------------------------------------------
    |
    | Control automatic scheduling of Flyron maintenance commands. You can
    | disable all Flyron scheduling via FLYRON_SCHEDULE_ENABLED=false. The
    | process-clean task removes stale PID and (optionally) payload files.
    | You can also add arbitrary commands to be scheduled via the `commands`
    | array below.
    |
    | Supported frequencies (string):
    |  - 'everyMinute', 'everyFiveMinutes', 'hourly', 'daily', 'weekly',
    |    'monthly', or a raw cron expression like '0 * * * *'.
    */

    'schedule' => [
        'enabled' => env('FLYRON_SCHEDULE_ENABLED', true),
        // Optional: limit scheduling to specific environments (e.g., ["production"]).
        'environments' => env('FLYRON_SCHEDULE_ENVIRONMENTS') ? explode(',', env('FLYRON_SCHEDULE_ENVIRONMENTS')) : [],

        'process_clean' => [
            'enabled'   => env('FLYRON_SCHEDULE_PROCESS_CLEAN', true),
            'frequency' => env('FLYRON_SCHEDULE_PROCESS_CLEAN_FREQUENCY', 'hourly'),
            'payloads'  => env('FLYRON_SCHEDULE_PROCESS_CLEAN_PAYLOADS', true),
        ],

        'process_optimize' => [
            'enabled'   => env('FLYRON_SCHEDULE_PROCESS_OPTIMIZE', false),
            'frequency' => env('FLYRON_SCHEDULE_PROCESS_OPTIMIZE_FREQUENCY', 'weekly'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Process Settings (AsyncProcess)
    |--------------------------------------------------------------------------
    |
    | Controls payload security and retention, and optional concurrency throttle
    | for AsyncProcess.
    */

    'process' => [
        'encryption_enabled' => env('FLYRON_PROCESS_ENCRYPTION', false),
        'encryption_cipher' => env('FLYRON_PROCESS_CIPHER', 'aes-256-gcm'),
        'payload_ttl_seconds' => env('FLYRON_PROCESS_PAYLOAD_TTL', 86400),

        // Throttle settings for spawning processes
        'max_concurrency' => env('FLYRON_PROCESS_MAX_CONCURRENCY', 0), // 0 means unlimited
        'throttle_mode' => env('FLYRON_PROCESS_THROTTLE_MODE', 'reject'), // reject|wait
        'throttle_wait_max_seconds' => env('FLYRON_PROCESS_THROTTLE_WAIT_MAX', 30),
        'throttle_wait_interval_ms' => env('FLYRON_PROCESS_THROTTLE_WAIT_INTERVAL', 200),
    ],

];
