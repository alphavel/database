<?php

namespace Alphavel\Database;

/**
 * Database Service Provider
 * 
 * Automatically configures DB facade from .env
 * 
 * Usage in bootstrap/app.php:
 * DatabaseServiceProvider::boot();
 * 
 * @package Alphavel\Database
 * @version 2.0.0
 */
class DatabaseServiceProvider
{
    /**
     * Bootstrap database connection from environment
     */
    public static function boot(): void
    {
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $database = getenv('DB_DATABASE') ?: '';
        $username = getenv('DB_USERNAME') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: '';
        $charset = getenv('DB_CHARSET') ?: 'utf8mb4';

        if (empty($database)) {
            throw new DatabaseException('DB_DATABASE environment variable is required');
        }

        DB::configure([
            'host' => $host,
            'port' => (int) $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => $charset,
            'options' => [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_STRINGIFY_FETCHES => false,
            ],
        ]);
    }

    /**
     * Configure from custom config array
     */
    public static function configure(array $config): void
    {
        DB::configure($config);
    }
}
