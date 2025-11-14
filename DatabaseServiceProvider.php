<?php

namespace Alphavel\Database;

use Alphavel\Core\ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('db', function () {
            $config = $this->app->config('database', []);

            return new Database($config);
        });

        // Auto-register facade
        $this->facades([
            'DB' => 'db',
        ]);
    }

    public function boot(): void
    {
        $db = $this->app->make('db');
        Model::setDatabase($db);
    }
}
