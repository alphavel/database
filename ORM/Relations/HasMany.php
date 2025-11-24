<?php

declare(strict_types=1);

namespace Alphavel\Database\ORM\Relations;

use Alphavel\Database\ORM\Relation;
use Alphavel\Database\QueryBuilder;
use Alphavel\Database\DB;

/**
 * HasMany Relationship (One-to-Many)
 * 
 * Performance: Lazy loads by default, supports eager loading
 * Example: User hasMany Posts
 */
class HasMany extends Relation
{
    /**
     * Get query for relationship
     * 
     * Performance: O(1) - just builds query, no execution
     */
    public function getQuery(): QueryBuilder
    {
        $instance = new $this->related();
        $table = $instance->getTable();
        
        return DB::table($table)
            ->where($this->foreignKey, '=', $this->parent->{$this->localKey});
    }
    
    /**
     * Get results as array/collection
     * 
     * Performance: Cached after first call
     */
    public function get(): array
    {
        if ($this->results !== null) {
            return $this->results;
        }
        
        $this->results = $this->getQuery()->get();
        return $this->results;
    }
    
    /**
     * Match eager loaded results to parent models
     * 
     * Performance: O(n + m) where n = parents, m = results
     * Uses hash map for O(1) lookups
     * 
     * @param array $models Parent models
     * @param array $results Query results
     * @param string $relation Relation name
     * @return array Models with matched relationships
     */
    public function match(array $models, array $results, string $relation): array
    {
        // Build hash map: foreign_key => [results]
        $map = [];
        foreach ($results as $result) {
            $key = $result->{$this->foreignKey} ?? null;
            if ($key !== null) {
                $map[$key] = $map[$key] ?? [];
                $map[$key][] = $result;
            }
        }
        
        // Match to parent models (O(n))
        foreach ($models as $model) {
            $key = $model->{$this->localKey} ?? null;
            $model->{$relation} = $map[$key] ?? [];
        }
        
        return $models;
    }
}
