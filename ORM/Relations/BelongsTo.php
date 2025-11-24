<?php

declare(strict_types=1);

namespace Alphavel\Database\ORM\Relations;

use Alphavel\Database\ORM\Relation;
use Alphavel\Database\QueryBuilder;
use Alphavel\Database\DB;

/**
 * BelongsTo Relationship (Inverse of One-to-Many)
 * 
 * Performance: Lazy loads by default
 * Example: Post belongsTo User
 */
class BelongsTo extends Relation
{
    /**
     * Get query for relationship
     */
    public function getQuery(): QueryBuilder
    {
        $instance = new $this->related();
        $table = $instance->getTable();
        
        return DB::table($table)
            ->where($this->localKey, '=', $this->parent->{$this->foreignKey})
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
        // Build hash map: id => result
        $map = [];
        foreach ($results as $result) {
            $key = $result->{$this->localKey} ?? null;
            if ($key !== null) {
                $map[$key] = $result;
            }
        }
        
        // Match to parent models
        foreach ($models as $model) {
            $key = $model->{$this->foreignKey} ?? null;
            $model->{$relation} = $map[$key] ?? null;
        }
        
        return $models;
    }
    
    /**
     * Guess foreign key (different for belongsTo)
     */
    protected function guessForeignKey(): string
    {
        $class = class_basename($this->related);
        return strtolower($class) . '_id';
    }
}
