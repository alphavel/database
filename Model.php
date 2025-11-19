<?php

namespace Alphavel\Database;

/**
 * Minimalist Active Record Base Model
 * 
 * Features:
 * - Uses DB:: static facade (no setDatabase required)
 * - Simple array-backed attributes
 * - Fluent query methods
 * - Dirty tracking for efficient updates
 * 
 * @package Alphavel\Database
 * @version 2.0.0
 */
abstract class Model
{
    protected static string $table;
    protected static string $primaryKey = 'id';

    protected array $attributes = [];
    protected array $original = [];

    /**
     * Get query builder instance for the model's table
     */
    protected static function query(): QueryBuilder
    {
        return DB::table(static::$table);
    }

    /**
     * Find all records
     */
    public static function all(): array
    {
        $results = static::query()->get();
        return static::hydrate($results);
    }

    /**
     * Find record by primary key
     */
    public static function find(mixed $id): ?static
    {
        $result = static::query()
            ->where(static::$primaryKey, $id)
            ->first();

        return $result ? static::newInstance($result) : null;
    }

    /**
     * Find or throw exception
     */
    public static function findOrFail(mixed $id): static
    {
        $model = static::find($id);

        if (!$model) {
            throw new DatabaseException("Model not found with " . static::$primaryKey . ": {$id}");
        }

        return $model;
    }

    /**
     * Start a where query
     */
    public static function where(string $column, mixed $operator, mixed $value = null): QueryBuilder
    {
        return static::query()->where($column, $operator, $value);
    }

    /**
     * Get first record
     */
    public static function first(): ?static
    {
        $result = static::query()->first();
        return $result ? static::newInstance($result) : null;
    }

    /**
     * Create and save new model
     */
    public static function create(array $attributes): static
    {
        $id = static::query()->insertGetId($attributes);
        return static::find($id);
    }

    /**
     * Count all records
     */
    public static function count(): int
    {
        return static::query()->count();
    }

    /**
     * Hydrate array of results into model instances
     */
    protected static function hydrate(array $results): array
    {
        return array_map(fn($result) => static::newInstance($result), $results);
    }

    /**
     * Create new model instance from attributes
     */
    protected static function newInstance(array $attributes): static
    {
        $model = new static();
        $model->attributes = $attributes;
        $model->original = $attributes;

        return $model;
    }

    /**
     * Constructor
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
        $this->original = $attributes;
    }

    /**
     * Magic getter
     */
    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Magic setter
     */
    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Magic isset
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Convert to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->attributes);
    }

    /**
     * Save model (insert or update)
     */
    public function save(): bool
    {
        if (isset($this->attributes[static::$primaryKey]) && $this->exists()) {
            return $this->performUpdate();
        }

        return $this->performInsert();
    }

    /**
     * Perform insert operation
     */
    protected function performInsert(): bool
    {
        $id = static::query()->insertGetId($this->attributes);
        $this->attributes[static::$primaryKey] = $id;
        $this->original = $this->attributes;

        return true;
    }

    /**
     * Perform update operation (only dirty attributes)
     */
    protected function performUpdate(): bool
    {
        $id = $this->attributes[static::$primaryKey];
        $data = array_diff_assoc($this->attributes, $this->original);

        if (empty($data)) {
            return true; // Nothing to update
        }

        static::query()
            ->where(static::$primaryKey, $id)
            ->update($data);

        $this->original = $this->attributes;

        return true;
    }

    /**
     * Check if model exists in database
     */
    public function exists(): bool
    {
        if (!isset($this->attributes[static::$primaryKey])) {
            return false;
        }

        return static::query()
            ->where(static::$primaryKey, $this->attributes[static::$primaryKey])
            ->count() > 0;
    }

    /**
     * Delete model from database
     */
    public function delete(): bool
    {
        if (!isset($this->attributes[static::$primaryKey])) {
            return false;
        }

        static::query()
            ->where(static::$primaryKey, $this->attributes[static::$primaryKey])
            ->delete();

        return true;
    }

    /**
     * Refresh model from database
     */
    public function refresh(): static
    {
        if (!isset($this->attributes[static::$primaryKey])) {
            return $this;
        }

        $fresh = static::find($this->attributes[static::$primaryKey]);

        if ($fresh) {
            $this->attributes = $fresh->attributes;
            $this->original = $fresh->original;
        }

        return $this;
    }

    /**
     * Check if model has been modified
     */
    public function isDirty(): bool
    {
        return $this->attributes !== $this->original;
    }

    /**
     * Get modified attributes
     */
    public function getDirty(): array
    {
        return array_diff_assoc($this->attributes, $this->original);
    }
}
