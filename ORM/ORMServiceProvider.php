<?php

declare(strict_types=1);

namespace Alphavel\Database\ORM;

use Alphavel\ServiceProvider;
use Alphavel\Database\Model;

/**
 * ORM Service Provider
 * 
 * Auto-discovery: No configuration required
 * Performance: Zero overhead registration
 */
class ORMServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No singleton needed - relationships are per-model
        // Performance: Zero overhead if not used
    }
    
    public function boot(): void
    {
        // Mix trait into Model class if exists
        if (class_exists(Model::class)) {
            $this->extendModel();
        }
    }
    
    /**
     * Extend Model with HasRelationships trait
     * 
     * Performance: Compile-time only, zero runtime overhead
     */
    private function extendModel(): void
    {
        // This is handled by Model class directly
        // Just ensure trait is autoloadable
    }
}
