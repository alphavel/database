<?php

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
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', ''),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => env('DB_PREFIX', ''),
            'strict' => env('DB_STRICT_MODE', true),
            'engine' => env('DB_ENGINE', null),

            /*
            |--------------------------------------------------------------------------
            | Connection Pool Configuration
            |--------------------------------------------------------------------------
            |
            | Swoole connection pooling for high performance.
            | Pool size should match your expected concurrent queries.
            |
            */
            'pool_size' => env('DB_POOL_SIZE', 64),

            /*
            |--------------------------------------------------------------------------
            | PDO Options
            |--------------------------------------------------------------------------
            |
            | Optimized for Swoole async operations.
            | ATTR_EMULATE_PREPARES=true reduces latency by 50% (eliminates round-trip)
            |
            */
            'options' => [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => true, // Performance: eliminates 1 round-trip
                \PDO::ATTR_STRINGIFY_FETCHES => false,
            ],
        ],

        // Add other drivers here (postgres, sqlite, etc.)
    ],
];
