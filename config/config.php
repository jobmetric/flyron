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

];
