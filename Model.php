<?php

namespace Alphavel\Database;

abstract class Model
{
    protected static string $table;

    protected static string $primaryKey = 'id';

    protected array $attributes = [];

    protected array $original = [];

    protected static ?Database $db = null;

    public static function setDatabase(Database $db): void
    {
        static::$db = $db;
    }

    protected static function query(): QueryBuilder
    {
        if (static::$db === null) {
            throw new DatabaseException('Database not set. Call Model::setDatabase() first.');
        }

        return static::$db->table(static::$table);
    }

    public static function all(): array
    {
        $results = static::query()->get();

        return static::hydrate($results);
    }

    public static function find(mixed $id): ?static
    {
        $result = static::query()
            ->where(static::$primaryKey, $id)
            ->first();

        if (! $result) {
            return null;
        }

        return static::newInstance($result);
    }

    public static function findOrFail(mixed $id): static
    {
        $model = static::find($id);

        if (! $model) {
            throw new DatabaseException("Model not found with " . static::$primaryKey . ": {$id}");
        }

        return $model;
    }

    public static function where(string $column, mixed $operator, mixed $value = null): QueryBuilder
    {
        return static::query()->where($column, $operator, $value);
    }

    public static function first(): ?static
    {
        $result = static::query()->first();

        if (! $result) {
            return null;
        }

        return static::newInstance($result);
    }

    public static function create(array $attributes): static
    {
        $id = static::query()->insertGetId($attributes);

        return static::find($id);
    }

    public static function count(): int
    {
        return static::query()->count();
    }

    protected static function hydrate(array $results): array
    {
        return array_map(fn ($result) => static::newInstance($result), $results);
    }

    protected static function newInstance(array $attributes): static
    {
        $model = new static();
        $model->attributes = $attributes;
        $model->original = $attributes;

        return $model;
    }

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
        $this->original = $attributes;
    }

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function toJson(): string
    {
        return json_encode($this->attributes);
    }

    public function save(): bool
    {
        if (isset($this->attributes[static::$primaryKey]) && $this->exists()) {
            return $this->performUpdate();
        }

        return $this->performInsert();
    }

    protected function performInsert(): bool
    {
        $id = static::query()->insertGetId($this->attributes);
        $this->attributes[static::$primaryKey] = $id;
        $this->original = $this->attributes;

        return true;
    }

    protected function performUpdate(): bool
    {
        $id = $this->attributes[static::$primaryKey];
        $data = array_diff_assoc($this->attributes, $this->original);

        if (empty($data)) {
            return true;
        }

        static::query()
            ->where(static::$primaryKey, $id)
            ->update($data);

        $this->original = $this->attributes;

        return true;
    }

    public function exists(): bool
    {
        if (! isset($this->attributes[static::$primaryKey])) {
            return false;
        }

        return static::query()
            ->where(static::$primaryKey, $this->attributes[static::$primaryKey])
            ->count() > 0;
    }

    public function delete(): bool
    {
        if (! isset($this->attributes[static::$primaryKey])) {
            return false;
        }

        static::query()
            ->where(static::$primaryKey, $this->attributes[static::$primaryKey])
            ->delete();

        return true;
    }

    public function refresh(): static
    {
        if (! isset($this->attributes[static::$primaryKey])) {
            return $this;
        }

        $fresh = static::find($this->attributes[static::$primaryKey]);

        if ($fresh) {
            $this->attributes = $fresh->attributes;
            $this->original = $fresh->original;
        }

        return $this;
    }

    public function isDirty(): bool
    {
        return $this->attributes !== $this->original;
    }

    public function getDirty(): array
    {
        return array_diff_assoc($this->attributes, $this->original);
    }
}
