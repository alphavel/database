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

        // Get database config
        $config = $this->app->config('database.connections.mysql', []);
        
        // Validate database name
        if (empty($config['database'])) {
            throw new DatabaseException(
                'Database name is required. Set DB_DATABASE in .env or configure database.connections.mysql.database'
            );
        }
        
        // Validate config for performance issues (development only)
        $this->validateConfiguration($config);
        
        // Configure DB facade
        DB::configure($config);

        // Register DatabaseManager as singleton
        $this->app->singleton('db', function ($app) use ($config) {
            return new DatabaseManager($config);
        });

        // Register DB facade
        $this->facades([
            'DB' => 'db',
        ]);
    }
    
    /**
     * Validate database configuration for performance issues
     * 
     * Logs warnings in development when non-optimal settings detected.
     */
    protected function validateConfiguration(array $config): void
    {
        // Only validate in development
        $env = $this->app->config('env', 'production');
        $debug = $this->app->config('debug', false);
        
        if ($env !== 'development' && $env !== 'local' && !$debug) {
            return;
        }
        
        $warnings = DB::validateConfig($config);
        
        if (!empty($warnings)) {
            error_log("\n" . str_repeat('=', 80));
            error_log("[Alphavel Database] ⚠️  Performance Configuration Warnings");
            error_log(str_repeat('=', 80));
            
            foreach ($warnings as $warning) {
                error_log("  • $warning");
            }
            
            error_log("\n💡 Use DB::optimizedConfig() helper for optimal performance:");
            error_log("   'mysql' => DB::optimizedConfig([");
            error_log("       'host' => env('DB_HOST', '127.0.0.1'),");
            error_log("       'database' => env('DB_DATABASE', 'alphavel'),");
            error_log("       // ... other settings");
            error_log("   ]),");
            error_log(str_repeat('=', 80) . "\n");
        }
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
