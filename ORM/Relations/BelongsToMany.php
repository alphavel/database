<?php

declare(strict_types=1);

namespace Alphavel\Database\ORM\Relations;

use Alphavel\Database\ORM\Relation;
use Alphavel\Database\QueryBuilder;
use Alphavel\Database\DB;

/**
 * BelongsToMany Relationship (Many-to-Many)
 * 
 * Performance: Uses pivot table, supports eager loading
 * Example: User belongsToMany Roles (via user_roles table)
 */
class BelongsToMany extends Relation
{
    protected string $pivotTable;
    protected string $foreignPivotKey;
    protected string $relatedPivotKey;
    
    public function __construct(
        object $parent,
        string $related,
        string $pivotTable,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $localKey = null
    ) {
        parent::__construct($parent, $related, null, $localKey);
        
        $this->pivotTable = $pivotTable;
        $this->foreignPivotKey = $foreignPivotKey ?? $this->guessForeignKey();
        $this->relatedPivotKey = $relatedPivotKey ?? $this->guessRelatedPivotKey();
    }
    
    /**
     * Get query for relationship
     * 
     * Performance: Single JOIN query
     */
    public function getQuery(): QueryBuilder
    {
        $instance = new $this->related();
        $table = $instance->getTable();
        
        return DB::table($table)
            ->join(
                $this->pivotTable,
                "{$table}.{$this->localKey}",
                '=',
                "{$this->pivotTable}.{$this->relatedPivotKey}"
            )
            ->where(
                "{$this->pivotTable}.{$this->foreignPivotKey}",
                '=',
                $this->parent->{$this->localKey}
            )
            ->select("{$table}.*");
    }
    
    /**
     * Get results
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
     * Match eager loaded results
     * 
     * Performance: O(n + m + p) where p = pivot rows
     */
    public function match(array $models, array $results, string $relation): array
    {
        // First, load pivot relationships
        $pivotMap = $this->loadPivotMap($models);
        
        // Build hash map: id => result
        $resultMap = [];
        foreach ($results as $result) {
            $resultMap[$result->{$this->localKey}] = $result;
        }
        
        // Match to parent models
        foreach ($models as $model) {
            $key = $model->{$this->localKey};
            $relatedIds = $pivotMap[$key] ?? [];
            
            $matched = [];
            foreach ($relatedIds as $relatedId) {
                if (isset($resultMap[$relatedId])) {
                    $matched[] = $resultMap[$relatedId];
                }
            }
            
            $model->{$relation} = $matched;
        }
        
        return $models;
    }
    
    /**
     * Load pivot table relationships
     * 
     * Performance: Single query for all models
     */
    private function loadPivotMap(array $models): array
    {
        $ids = array_map(fn($m) => $m->{$this->localKey}, $models);
        
        $pivots = DB::table($this->pivotTable)
            ->whereIn($this->foreignPivotKey, $ids)
            ->get();
        
        $map = [];
        foreach ($pivots as $pivot) {
            $foreignKey = $pivot->{$this->foreignPivotKey};
            $relatedKey = $pivot->{$this->relatedPivotKey};
            
            $map[$foreignKey] = $map[$foreignKey] ?? [];
            $map[$foreignKey][] = $relatedKey;
        }
        
        return $map;
    }
    
    /**
     * Guess related pivot key
     */
    protected function guessRelatedPivotKey(): string
    {
        $class = class_basename($this->related);
        return strtolower($class) . '_id';
    }
}
