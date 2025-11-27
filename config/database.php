<?php

/**
 * Alphavel Database Configuration
 * 
 * ⚡ This configuration is optimized for maximum performance out-of-the-box.
 * 
 * Key Optimizations:
 * - ATTR_EMULATE_PREPARES => false (+20% performance with Global Statement Cache)
 * - No pool_size by default (singleton connectionRead() is faster for reads)
 * - No ATTR_PERSISTENT (redundant in Swoole, prevents lock contention)
 * 
 * 📚 Learn more: https://github.com/alphavel/database#performance-tuning
 */

use Alphavel\Database\DB;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Database Connection
    |--------------------------------------------------------------------------
    |
    | Database configuration for Alphavel applications.
    | Uses environment variables with sensible defaults.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [
        /*
        |--------------------------------------------------------------------------
        | MySQL Connection (Optimized)
        |--------------------------------------------------------------------------
        |
        | This is the recommended configuration for 99% of applications.
        | Achieves 7,000+ req/s on database-heavy workloads.
        |
        | Uses DB::optimizedConfig() which sets optimal PDO attributes for Swoole.
        |
        */
        'mysql' => DB::optimizedConfig([
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', ''),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => env('DB_PREFIX', ''),
            'strict' => env('DB_STRICT_MODE', true),
            'engine' => env('DB_ENGINE', null),
        ]),

        // Add other drivers here (postgres, sqlite, etc.)
    ],
];
