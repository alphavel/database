<?php

declare(strict_types=1);

namespace Alphavel\Database\ORM\Relations;

use Alphavel\Database\ORM\Relation;
use Alphavel\Database\QueryBuilder;
use Alphavel\Database\DB;

/**
 * HasOne Relationship (One-to-One)
 * 
 * Performance: Lazy loads by default
 * Example: User hasOne Profile
 */
class HasOne extends Relation
{
    /**
     * Get query for relationship
     */
    public function getQuery(): QueryBuilder
    {
        $instance = new $this->related();
        $table = $instance->getTable();
        
        return DB::table($table)
            ->where($this->foreignKey, '=', $this->parent->{$this->localKey})
            ->limit(1);
    }
    
    /**
     * Get result (single object)
     */
    public function get(): ?object
    {
        if ($this->results !== null) {
            return $this->results;
        }
        
        $this->results = $this->getQuery()->first();
        return $this->results;
    }
    
    /**
     * Match eager loaded results
     * 
     * Performance: O(n + m) with hash map
     */
    public function match(array $models, array $results, string $relation): array
    {
        // Build hash map: foreign_key => result
        $map = [];
        foreach ($results as $result) {
            $key = $result->{$this->foreignKey} ?? null;
            if ($key !== null) {
                $map[$key] = $result; // Single result (hasOne)
            }
        }
        
        // Match to parent models
        foreach ($models as $model) {
            $key = $model->{$this->localKey} ?? null;
            $model->{$relation} = $map[$key] ?? null;
        }
        
        return $models;
    }
}
