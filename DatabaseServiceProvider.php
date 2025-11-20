<?php

namespace Alphavel\Database;

use Alphavel\Framework\ServiceProvider;

/**
 * Database Service Provider
 * 
 * Registers database services with the application container.
 * Supports configuration publishing and merging.
 * 
 * @package Alphavel\Database
 * @version 2.1.0
 */
class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register database services
     */
    public function register(): void
    {
        // Merge package config with application config
        $this->mergeConfigFrom(
            __DIR__ . '/config/database.php',
            'database'
        );

        // Register DatabaseManager as singleton
        $this->app->singleton('db', function ($app) {
            $config = $app->config('database.connections.mysql', []);
            
            if (empty($config['database'])) {
                throw new DatabaseException(
                    'Database name is required. Set DB_DATABASE in .env or configure database.connections.mysql.database'
                );
            }

            return new DatabaseManager($config);
        });

        // Register DB facade
        $this->facades([
            'DB' => 'db',
        ]);
    }

    /**
     * Bootstrap database services
     */
    public function boot(): void
    {
        // Publish configuration file
        $basePath = dirname(__DIR__, 3); // Navigate to project root
        
        $this->publishes([
            __DIR__ . '/config/database.php' => $basePath . '/config/database.php',
        ], 'config');
    }

    /**
     * Merge package configuration with application config
     */
    protected function mergeConfigFrom(string $path, string $key): void
    {
        if (!file_exists($path)) {
            return;
        }

        $packageConfig = require $path;
        $appConfig = $this->app->config($key, []);

        // Deep merge: app config overrides package config
        $merged = array_replace_recursive($packageConfig, $appConfig);

        // Create temporary config file to load
        $tempFile = sys_get_temp_dir() . '/alphavel_db_config_' . uniqid() . '.php';
        file_put_contents($tempFile, '<?php return ' . var_export([$key => $merged], true) . ';');
        
        $this->app->loadConfig($tempFile);
        unlink($tempFile);
    }

    /**
     * Register paths to be published
     */
    protected function publishes(array $paths, string $group = null): void
    {
        // Store paths for vendor:publish command
        // This is a placeholder - full implementation requires VendorPublishCommand
        
        foreach ($paths as $source => $destination) {
            // For now, just ensure the config directory exists
            $configDir = dirname($destination);
            if (!is_dir($configDir) && strpos($configDir, '/config') !== false) {
                @mkdir($configDir, 0755, true);
            }
        }
    }
}
