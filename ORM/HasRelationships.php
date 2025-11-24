<?php

declare(strict_types=1);

namespace Alphavel\Database\ORM;

use Alphavel\Database\ORM\Relations\HasMany;
use Alphavel\Database\ORM\Relations\HasOne;
use Alphavel\Database\ORM\Relations\BelongsTo;
use Alphavel\Database\ORM\Relations\BelongsToMany;

/**
 * HasRelationships Trait
 * 
 * Performance: Lazy loading by default, eager loading on demand
 * Zero overhead if relationships not used
 */
trait HasRelationships
{
    /**
     * Loaded relationships cache
     * 
     * Performance: Prevents duplicate queries
     */
    protected array $relations = [];
    
    /**
     * Relationships to eager load
     */
    protected array $with = [];
    
    /**
     * Define hasMany relationship
     * 
     * Performance: O(1) - just creates Relation object
     * 
     * @param string $related Related model class
     * @param string|null $foreignKey Foreign key on related table
     * @param string|null $localKey Local key on this table
     * @return HasMany
     */
    protected function hasMany(
        string $related,
        ?string $foreignKey = null,
        ?string $localKey = null
    ): HasMany {
        return new HasMany($this, $related, $foreignKey, $localKey);
    }
    
    /**
     * Define hasOne relationship
     */
    protected function hasOne(
        string $related,
        ?string $foreignKey = null,
        ?string $localKey = null
    ): HasOne {
        return new HasOne($this, $related, $foreignKey, $localKey);
    }
    
    /**
     * Define belongsTo relationship
     */
    protected function belongsTo(
        string $related,
        ?string $foreignKey = null,
        ?string $ownerKey = null
    ): BelongsTo {
        return new BelongsTo($this, $related, $foreignKey, $ownerKey);
    }
    
    /**
     * Define belongsToMany relationship
     */
    protected function belongsToMany(
        string $related,
        string $pivotTable,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null
    ): BelongsToMany {
        return new BelongsToMany(
            $this,
            $related,
            $pivotTable,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey
        );
    }
    
    /**
     * Load relationships (eager loading)
     * 
     * Performance: Single query per relationship (N+1 prevention)
     * Uses efficient matching with hash maps
     * 
     * @param array $models Models to load relationships for
     * @param array $relations Relationships to load
     * @return array Models with loaded relationships
     */
    public static function loadRelations(array $models, array $relations): array
    {
        if (empty($models) || empty($relations)) {
            return $models;
        }
        
        foreach ($relations as $relationName) {
            $models = static::loadRelation($models, $relationName);
        }
        
        return $models;
    }
    
    /**
     * Load single relationship for multiple models
     * 
     * Performance: O(1) query + O(n) matching
     */
    private static function loadRelation(array $models, string $relationName): array
    {
        // Parse nested relations (e.g., "posts.comments")
        if (str_contains($relationName, '.')) {
            return static::loadNestedRelation($models, $relationName);
        }
        
        // Get relation from first model
        $firstModel = $models[0];
        if (!method_exists($firstModel, $relationName)) {
            return $models;
        }
        
        $relation = $firstModel->$relationName();
        
        // Get all foreign/local keys for batch query
        $keys = static::getRelationKeys($models, $relation);
        
        // Execute single batch query
        $results = static::loadRelationResults($relation, $keys);
        
        // Match results to models (O(n + m) with hash map)
        return $relation->match($models, $results, $relationName);
    }
    
    /**
     * Load nested relationships (e.g., posts.comments)
     * 
     * Performance: Recursive loading with caching
     */
    private static function loadNestedRelation(array $models, string $relationName): array
    {
        [$parent, $child] = explode('.', $relationName, 2);
        
        // Load parent relation first
        $models = static::loadRelation($models, $parent);
        
        // Collect related models
        $relatedModels = [];
        foreach ($models as $model) {
            $related = $model->{$parent} ?? null;
            if ($related) {
                if (is_array($related)) {
                    $relatedModels = array_merge($relatedModels, $related);
                } else {
                    $relatedModels[] = $related;
                }
            }
        }
        
        // Load child relation on related models
        if (!empty($relatedModels)) {
            static::loadRelation($relatedModels, $child);
        }
        
        return $models;
    }
    
    /**
     * Get keys for batch relation query
     */
    private static function getRelationKeys(array $models, Relation $relation): array
    {
        $keys = [];
        
        foreach ($models as $model) {
            // Use reflection to get localKey property
            $reflectionClass = new \ReflectionClass($relation);
            $property = $reflectionClass->getProperty('localKey');
            $property->setAccessible(true);
            $localKey = $property->getValue($relation);
            
            if (isset($model->{$localKey})) {
                $keys[] = $model->{$localKey};
            }
        }
        
        return array_unique($keys);
    }
    
    /**
     * Load relation results with batch query
     * 
     * Performance: Single query for all models
     */
    private static function loadRelationResults(Relation $relation, array $keys): array
    {
        // Get query and add whereIn for batch loading
        $query = $relation->getQuery();
        
        // Get foreign key from relation
        $reflectionClass = new \ReflectionClass($relation);
        $property = $reflectionClass->getProperty('foreignKey');
        $property->setAccessible(true);
        $foreignKey = $property->getValue($relation);
        
        // Batch query
        return $query->whereIn($foreignKey, $keys)->get();
    }
    
    /**
     * Magic getter for relationships
     * 
     * Performance: Lazy loads on first access, then cached
     */
    public function __get(string $name): mixed
    {
        // Check if already loaded
        if (isset($this->relations[$name])) {
            return $this->relations[$name];
        }
        
        // Check if method exists
        if (method_exists($this, $name)) {
            $relation = $this->$name();
            
            if ($relation instanceof Relation) {
                // Lazy load and cache
                $this->relations[$name] = $relation->get();
                return $this->relations[$name];
            }
        }
        
        // Check parent properties
        return $this->$name ?? null;
    }
    
    /**
     * Get table name (for Model compatibility)
     */
    abstract public function getTable(): string;
}
