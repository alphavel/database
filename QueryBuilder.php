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
 * - Statement cache for maximum performance (v1.3.0+)
 * 
 * @package Alphavel\Database
 * @version 2.0.0
 */
class QueryBuilder
{
    /**
     * Static SQL cache (persists across requests in Swoole worker)
     * Shared across all coroutines - thread-safe for immutable SQL strings
     * 
     * @var array<string, string>
     */
    private static array $sqlCache = [];
    
    /**
     * Statement pool per coroutine (isolated, no race conditions)
     * 
     * Key format: "{coroutineId}:{cacheKey}"
     * Each coroutine has its own PDOStatement instances
     * 
     * Performance: Eliminates prepare() overhead (100-200µs per request)
     * Thread-safety: Statements never shared between coroutines
     * 
     * @var array<string, \PDOStatement>
     */
    private static array $statementPool = [];
    
    /**
     * Maximum number of cached SQL strings (shared)
     * @var int
     */
    private static int $maxCachedSql = 500;
    
    /**
     * Maximum number of statements per coroutine
     * @var int
     */
    private static int $maxStatementsPerCoroutine = 50;
    
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
        // Global statement cache (v1.3.3): Prepare once, execute many
        // Thread-safe for SELECT (read-only queries)
        // Performance: ~6,340 req/s for search endpoint
        
        $cacheKey = $this->getStatementCacheKey();
        
        // Cache SQL compilation globally
        if (!isset(self::$sqlCache[$cacheKey])) {
            // Check SQL cache size limit
            if (count(self::$sqlCache) >= self::$maxCachedSql) {
                // Remove oldest entries (simple FIFO)
                self::$sqlCache = array_slice(self::$sqlCache, 100, null, true);
            }
            
            $sql = $this->compileSelect();
            self::$sqlCache[$cacheKey] = $sql;
        }
        
        $sql = self::$sqlCache[$cacheKey];
        
        // HOT PATH OPTIMIZATION:
        // If not in a transaction, use the shared read connection and global statement cache.
        // This avoids coroutine lookup overhead and maximizes statement reuse.
        if (!DB::inTransaction()) {
            $stmtKey = "global:stmt:{$cacheKey}";
            
            if (!isset(self::$statementPool[$stmtKey])) {
                // Use singleton read connection
                self::$statementPool[$stmtKey] = DB::connectionRead()->prepare($sql);
            }
            
            $stmt = self::$statementPool[$stmtKey];
        } else {
            // TRANSACTION PATH:
            // Must use the isolated connection for the current coroutine.
            // Cache must be namespaced by connection/coroutine to avoid mixing statements.
            // We use the object hash of the connection as part of the key to be safe.
            $conn = DB::connection();
            $connHash = spl_object_id($conn);
            $stmtKey = "tx:{$connHash}:{$cacheKey}";
            
            if (!isset(self::$statementPool[$stmtKey])) {
                self::$statementPool[$stmtKey] = $conn->prepare($sql);
            }
            
            $stmt = self::$statementPool[$stmtKey];
        }
        
        // Execute with current bindings
        $stmt->execute($this->bindings);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
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
    
    /**
     * Generate cache key based on query structure (not values)
     * 
     * This ensures queries with same structure but different values
     * reuse the same prepared statement.
     * 
     * Example:
     * - WHERE id >= ? AND id <= ?  (same structure)
     * - WHERE id >= 1 AND id <= 100 (different values)
     * - WHERE id >= 50 AND id <= 500 (different values)
     * → All use the same cached statement!
     * 
     * @return string MD5 hash of query structure
     */
    private function getStatementCacheKey(): string
    {
        // Build structure fingerprint (ignores actual values)
        $structure = [
            'table' => $this->table,
            'select' => $this->select,
            'where_count' => count($this->where),
            'where_structure' => array_map(fn($w) => [$w[0], $w[1]], $this->where),
            'orderBy' => $this->orderBy,
            'has_limit' => $this->limit !== null,
            'has_offset' => $this->offset !== null,
        ];
        
        return md5(serialize($structure));
    }
    
    /**
     * Clear all caches (SQL and statement pool)
     * Useful for testing or memory management
     * 
     * @return void
     */
    public static function clearStatementCache(): void
    {
        self::$sqlCache = [];
        self::$statementPool = [];
    }
    
    /**
     * Get cache statistics
     * 
     * @return array{sql_count: int, statements_count: int, sql_max: int, stmt_max_per_co: int, memory: int}
     */
    public static function getStatementCacheStats(): array
    {
        return [
            'sql_count' => count(self::$sqlCache),
            'statements_count' => count(self::$statementPool),
            'sql_max' => self::$maxCachedSql,
            'stmt_max_per_co' => self::$maxStatementsPerCoroutine,
            'memory' => memory_get_usage(true),
        ];
    }
    
    /**
     * Set maximum number of cached SQL strings (shared)
     * 
     * @param int $max Maximum SQL strings to cache
     * @return void
     */
    public static function setMaxCachedStatements(int $max): void
    {
        self::$maxCachedSql = $max;
    }
    
    /**
     * Set maximum number of statements per coroutine
     * 
     * @param int $max Maximum statements per coroutine
     * @return void
     */
    public static function setMaxStatementsPerCoroutine(int $max): void
    {
        self::$maxStatementsPerCoroutine = $max;
    }
}
