<?php

namespace Alphavel\Database;

class QueryBuilder
{
    private Database $db;

    private string $table;

    private array $selects = ['*'];

    private array $wheres = [];

    private array $joins = [];

    private array $orderBy = [];

    private array $groupBy = [];

    private ?int $limit = null;

    private ?int $offset = null;

    public function __construct(Database $db, string $table)
    {
        $this->db = $db;
        $this->table = $table;
    }

    public function select(string ...$columns): self
    {
        $this->selects = $columns;

        return $this;
    }

    public function where(string $column, mixed $operator, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'AND',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    public function orWhere(string $column, mixed $operator, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'OR',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'AND',
            'column' => $column,
            'operator' => 'IN',
            'value' => $values,
        ];

        return $this;
    }

    public function whereNotIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'AND',
            'column' => $column,
            'operator' => 'NOT IN',
            'value' => $values,
        ];

        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'AND',
            'column' => $column,
            'operator' => 'IS NULL',
            'value' => null,
        ];

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'AND',
            'column' => $column,
            'operator' => 'IS NOT NULL',
            'value' => null,
        ];

        return $this;
    }

    public function whereBetween(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'AND',
            'column' => $column,
            'operator' => 'BETWEEN',
            'value' => $values,
        ];

        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = [$column, strtoupper($direction)];

        return $this;
    }

    public function groupBy(string ...$columns): self
    {
        $this->groupBy = array_merge($this->groupBy, $columns);

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
        [$sql, $bindings] = $this->compileSelect();

        return $this->db->query($sql, $bindings);
    }

    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();

        return $results[0] ?? null;
    }

    public function count(): int
    {
        $original = $this->selects;
        $this->selects = ['COUNT(*) as count'];

        $result = $this->first();
        $this->selects = $original;

        return (int) ($result['count'] ?? 0);
    }

    public function sum(string $column): float
    {
        return $this->aggregate('SUM', $column);
    }

    public function avg(string $column): float
    {
        return $this->aggregate('AVG', $column);
    }

    public function max(string $column): mixed
    {
        return $this->aggregate('MAX', $column);
    }

    public function min(string $column): mixed
    {
        return $this->aggregate('MIN', $column);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function insert(array $data): int
    {
        [$sql, $bindings] = $this->compileInsert($data);

        return $this->db->execute($sql, $bindings);
    }

    public function insertGetId(array $data): string
    {
        $this->insert($data);

        return $this->db->lastInsertId();
    }

    public function update(array $data): int
    {
        [$sql, $bindings] = $this->compileUpdate($data);

        return $this->db->execute($sql, $bindings);
    }

    public function delete(): int
    {
        [$sql, $bindings] = $this->compileDelete();

        return $this->db->execute($sql, $bindings);
    }

    private function aggregate(string $function, string $column): float
    {
        $original = $this->selects;
        $this->selects = ["{$function}({$column}) as aggregate"];

        $result = $this->first();
        $this->selects = $original;

        return (float) ($result['aggregate'] ?? 0);
    }

    private function compileSelect(): array
    {
        $sql = 'SELECT ' . implode(', ', $this->selects) . ' FROM ' . $this->table;
        $bindings = [];

        // Joins
        if (! empty($this->joins)) {
            foreach ($this->joins as $join) {
                $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']}";
                $sql .= " {$join['operator']} {$join['second']}";
            }
        }

        // Wheres
        if (! empty($this->wheres)) {
            [$whereSql, $whereBindings] = $this->compileWheres();
            $sql .= $whereSql;
            $bindings = array_merge($bindings, $whereBindings);
        }

        // Group By
        if (! empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        // Order By
        if (! empty($this->orderBy)) {
            $orders = array_map(fn ($order) => "{$order[0]} {$order[1]}", $this->orderBy);
            $sql .= ' ORDER BY ' . implode(', ', $orders);
        }

        // Limit & Offset
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return [$sql, $bindings];
    }

    private function compileInsert(array $data): array
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";

        return [$sql, array_values($data)];
    }

    private function compileUpdate(array $data): array
    {
        $sets = array_map(fn ($key) => "{$key} = ?", array_keys($data));
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets);

        $bindings = array_values($data);

        if (! empty($this->wheres)) {
            [$whereSql, $whereBindings] = $this->compileWheres();
            $sql .= $whereSql;
            $bindings = array_merge($bindings, $whereBindings);
        }

        return [$sql, $bindings];
    }

    private function compileDelete(): array
    {
        $sql = "DELETE FROM {$this->table}";
        $bindings = [];

        if (! empty($this->wheres)) {
            [$whereSql, $whereBindings] = $this->compileWheres();
            $sql .= $whereSql;
            $bindings = $whereBindings;
        }

        return [$sql, $bindings];
    }

    private function compileWheres(): array
    {
        $sql = ' WHERE ';
        $bindings = [];
        $clauses = [];

        foreach ($this->wheres as $i => $where) {
            $clause = '';

            if ($i > 0) {
                $clause .= " {$where['type']} ";
            }

            switch ($where['operator']) {
                case 'IN':
                case 'NOT IN':
                    $placeholders = implode(', ', array_fill(0, count($where['value']), '?'));
                    $clause .= "{$where['column']} {$where['operator']} ({$placeholders})";
                    $bindings = array_merge($bindings, $where['value']);
                    break;

                case 'BETWEEN':
                    $clause .= "{$where['column']} BETWEEN ? AND ?";
                    $bindings = array_merge($bindings, $where['value']);
                    break;

                case 'IS NULL':
                case 'IS NOT NULL':
                    $clause .= "{$where['column']} {$where['operator']}";
                    break;

                default:
                    $clause .= "{$where['column']} {$where['operator']} ?";
                    $bindings[] = $where['value'];
            }

            $clauses[] = $clause;
        }

        $sql .= implode('', $clauses);

        return [$sql, $bindings];
    }
}
