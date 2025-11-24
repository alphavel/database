<?php

declare(strict_types=1);

namespace Alphavel\Database\ORM;

use Alphavel\Database\QueryBuilder;

/**
 * Base Relationship Class
 * 
 * Performance: Lazy loading by default, eager loading on demand
 */
abstract class Relation
{
    protected object $parent;
    protected string $related;
    protected string $foreignKey;
    protected string $localKey;
    
    /**
     * Cached results
     */
    protected mixed $results = null;
    
    public function __construct(
        object $parent,
        string $related,
        ?string $foreignKey = null,
        ?string $localKey = null
    ) {
        $this->parent = $parent;
        $this->related = $related;
        $this->foreignKey = $foreignKey ?? $this->guessForeignKey();
        $this->localKey = $localKey ?? $this->guessLocalKey();
    }
    
    /**
     * Get query builder for relationship
     * 
     * Performance: Returns new QueryBuilder instance
     * No DB queries until get() is called
     */
    abstract public function getQuery(): QueryBuilder;
    
    /**
     * Execute relationship query
     * 
     * Performance: Cached after first call
     */
    public function get(): mixed
    {
        if ($this->results !== null) {
            return $this->results;
        }
        
        $this->results = $this->getQuery()->get();
        return $this->results;
    }
    
    /**
     * Get first result
     */
    public function first(): mixed
    {
        return $this->getQuery()->first();
    }
    
    /**
     * Magic call for query builder methods
     * 
     * Performance: Delegates to QueryBuilder
     */
    public function __call(string $method, array $args): mixed
    {
        $query = $this->getQuery();
        
        if (method_exists($query, $method)) {
            return $query->$method(...$args);
        }
        
        throw new \BadMethodCallException("Method {$method} does not exist");
    }
    
    /**
     * Guess foreign key name
     */
    protected function guessForeignKey(): string
    {
        $class = class_basename(get_class($this->parent));
        return strtolower($class) . '_id';
    }
    
    /**
     * Guess local key name
     */
    protected function guessLocalKey(): string
    {
        return 'id';
    }
}
