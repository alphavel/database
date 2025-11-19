<?php

namespace Alphavel\Database;

/**
 * Minimalist Query Builder for High Performance
 * 
 * Focused on:
 * - Zero overhead: methods compile SQL directly
 * - Reusable prepared statements via DB::prepare()
 * - Simple array returns (no unnecessary hydration)
 * - Fluent API for common queries
 * 
 * @package Alphavel\Database
 * @version 2.0.0
 */
class QueryBuilder
{
    private string $table;
    private array $select = ['*'];
    private array $where = [];
    private array $bindings = [];
    private ?string $orderBy = null;
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function select(string ...$columns): self
    {
        $this->select = $columns;
        return $this;
    }

    public function where(string $column, mixed $operator, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->where[] = [$column, $operator, $value];
        $this->bindings[] = $value;

        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $this->where[] = [$column, 'IN', "($placeholders)"];
        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy = "$column " . strtoupper($direction);
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function get(): array
    {
        $sql = $this->compileSelect();
        return DB::query($sql, $this->bindings);
    }

    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();

        return $results[0] ?? null;
    }

    public function find(mixed $id, string $primaryKey = 'id'): ?array
    {
        return $this->where($primaryKey, $id)->first();
    }

    public function count(): int
    {
        $originalSelect = $this->select;
        $this->select = ['COUNT(*) as count'];

        $result = $this->first();
        $this->select = $originalSelect;

        return (int) ($result['count'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function insert(array $data): string
    {
        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = implode(',', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$this->table} (" . implode(',', $columns) . ") VALUES ($placeholders)";

        $stmt = DB::prepare($sql);
        $stmt->execute($values);

        return DB::lastInsertId();
    }

    public function insertGetId(array $data): string
    {
        return $this->insert($data);
    }

    public function update(array $data): int
    {
        $sets = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $sets[] = "$column = ?";
            $bindings[] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets);

        if (!empty($this->where)) {
            $conditions = [];
            foreach ($this->where as [$column, $operator, $value]) {
                if ($operator === 'IN') {
                    $conditions[] = "$column $operator $value";
                } else {
                    $conditions[] = "$column $operator ?";
                }
            }
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
            $bindings = array_merge($bindings, $this->bindings);
        }

        return DB::execute($sql, $bindings);
    }

    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}";

        if (!empty($this->where)) {
            $conditions = [];
            foreach ($this->where as [$column, $operator, $value]) {
                if ($operator === 'IN') {
                    $conditions[] = "$column $operator $value";
                } else {
                    $conditions[] = "$column $operator ?";
                }
            }
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        return DB::execute($sql, $this->bindings);
    }

    private function compileSelect(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->select) . ' FROM ' . $this->table;

        if (!empty($this->where)) {
            $conditions = [];
            foreach ($this->where as [$column, $operator, $value]) {
                if ($operator === 'IN') {
                    $conditions[] = "$column $operator $value";
                } else {
                    $conditions[] = "$column $operator ?";
                }
            }
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        if ($this->orderBy) {
            $sql .= ' ORDER BY ' . $this->orderBy;
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    public function getStatement(): \PDOStatement
    {
        return DB::prepare($this->compileSelect());
    }
}
