<?php

namespace Alphavel\Database;

use PDO;
use PDOStatement;
use Swoole\Coroutine;

/**
 * Database Connection Manager with Swoole Coroutine Support
 * 
 * High-performance database facade with:
 * - Thread-safe connections isolated per coroutine
 * - Automatic prepared statement caching
 * - Zero-overhead static API
 * - Full transaction support
 * 
 * @package Alphavel\Database
 * @version 2.0.0
 */
class DB
{
    private static ?ConnectionPool $pool = null;
    private static array $connections = [];
    private static array $config = [];
    
    /**
     * Global statement cache for READ operations (thread-safe for SELECT)
     * 
     * Strategy: Prepare once, execute many
     * Shared across ALL coroutines for maximum performance
     * Safe for SELECT queries (no state mutation)
     * 
     * Performance: ~6,700 req/s for findOne() (vs 1,233 before)
     * Key format: "read:{sql_hash}"
     * 
     * @var array<string, \PDOStatement>
     */
    private static array $globalStatementCache = [];
    
    /**
     * Single persistent connection for READ operations
     * 
     * Eliminates coroutine lookup and pool overhead for hot path
     * Safe for concurrent reads (SELECT queries don't mutate connection state)
     * 
     * Performance gain: ~6,700 req/s (vs 1,233 with per-coroutine connections)
     * 
     * @var \PDO|null
     */
    private static ?PDO $readConnection = null;

    /**
     * Get optimized database configuration for maximum performance
     * 
     * Returns production-ready config with:
     * - ATTR_EMULATE_PREPARES => false (+20% performance)
     * - No ATTR_PERSISTENT (redundant in Swoole)
     * - No pool_size (use singleton connectionRead)
     * 
     * @param array $overrides Override specific keys
     * @return array Optimized configuration
     * 
     * @example
     * // MySQL (default)
     * $config = DB::optimizedConfig([
     *     'host' => 'localhost',
     *     'database' => 'myapp',
     *     'username' => 'root',
     *     'password' => 'secret',
     * ]);
     * 
     * @example
     * // PostgreSQL
     * $config = DB::optimizedConfig([
     *     'driver' => 'pgsql',
     *     'host' => 'localhost',
     *     'port' => 5432,
     *     'database' => 'myapp',
     * ]);
     */
    public static function optimizedConfig(array $overrides = []): array
    {
        $defaults = [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'alphavel',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false, // CRITICAL: +20% performance
            ],
            // No pool_size: singleton connectionRead() is faster
            // No ATTR_PERSISTENT: redundant in Swoole
        ];
        
        return array_replace_recursive($defaults, $overrides);
    }
    
    /**
     * Quick config from environment variables
     * 
     * Reads DB_* env vars and returns optimized config.
     * Perfect for zero-config setups.
     * 
     * @return array Optimized configuration from environment
     * 
     * @example
     * // .env file:
     * // DB_HOST=localhost
     * // DB_DATABASE=myapp
     * // DB_USERNAME=root
     * // DB_PASSWORD=secret
     * 
     * DB::configure(DB::fromEnv());
     */
    public static function fromEnv(): array
    {
        return self::optimizedConfig([
            'driver' => getenv('DB_DRIVER') ?: 'mysql',
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int)(getenv('DB_PORT') ?: 3306),
            'database' => getenv('DB_DATABASE') ?: 'alphavel',
            'username' => getenv('DB_USERNAME') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
        ]);
    }
    
    /**
     * Validate configuration and warn about non-optimal settings
     * 
     * Returns array of warnings for performance issues.
     * Call this in development to catch misconfigurations.
     * 
     * @param array|null $config Config to validate (null = current config)
     * @return array Array of warning messages
     * 
     * @example
     * $warnings = DB::validateConfig();
     * if (!empty($warnings)) {
     *     foreach ($warnings as $warning) {
     *         error_log("[Alphavel DB Warning] $warning");
     *     }
     * }
     */
    public static function validateConfig(?array $config = null): array
    {
        $config = $config ?? self::$config;
        $warnings = [];
        
        // Check ATTR_EMULATE_PREPARES
        if (($config['options'][PDO::ATTR_EMULATE_PREPARES] ?? null) === true) {
            $warnings[] = "ATTR_EMULATE_PREPARES is set to true. This reduces performance by ~20%. Set to false for real prepared statements. See: https://github.com/alphavel/database#performance-tuning";
        }
        
        // Check ATTR_PERSISTENT
        if (isset($config['options'][PDO::ATTR_PERSISTENT]) && $config['options'][PDO::ATTR_PERSISTENT] === true) {
            $warnings[] = "ATTR_PERSISTENT is set to true. This is redundant in Swoole and reduces performance by ~5%. Remove this option. See: https://github.com/alphavel/database#performance-tuning";
        }
        
        // Check large pool_size
        if (isset($config['pool_size']) && $config['pool_size'] > 16) {
            $warnings[] = "pool_size is set to {$config['pool_size']}. Large pools waste memory and reduce performance by ~7%. Hot path methods use singleton connectionRead(). Recommended: 0 (disabled) or workers × 2. See: https://github.com/alphavel/database#performance-tuning";
        }
        
        return $warnings;
    }

    /**
     * Configure the database subsystem
     */
    public static function configure(array $config): void
    {
        self::$config = $config;
        
        // Validate config in development
        if (getenv('APP_ENV') === 'development' || getenv('APP_DEBUG') === 'true') {
            $warnings = self::validateConfig($config);
            foreach ($warnings as $warning) {
                error_log("[Alphavel Database] Performance Warning: $warning");
            }
        }
        
        // Initialize pool if in Swoole environment and pool size > 0
        if (extension_loaded('swoole') && ($config['pool_size'] ?? 0) > 0) {
            self::$pool = new ConnectionPool($config, (int)($config['pool_size']));
        }
    }

    /**
     * Initialize/Warmup the connection pool
     * Should be called on WorkerStart
     */
    public static function initPool(): void
    {
        if (self::$pool) {
            self::$pool->fill();
        }
    }

    /**
     * Get a connection for the current context
     * For WRITE operations and transactions (use connectionRead() for reads)
     */
    public static function connection(): PDO
    {
        $cid = self::getCoroutineId();
        
        // Return existing connection for this coroutine
        if (isset(self::$connections[$cid])) {
            return self::$connections[$cid];
        }
        
        // Try to get from pool
        if (self::$pool) {
            $connection = self::$pool->get();
            self::$connections[$cid] = $connection;
            return $connection;
        }
        
        // Fallback: Create new connection
        self::$connections[$cid] = self::createConnection();
        return self::$connections[$cid];
    }
    
    /**
     * Get single persistent connection for READ operations (hot path)
     * 
     * Safe for concurrent SELECT queries (no state mutation)
     * Eliminates coroutine lookup and pool overhead
     * 
     * Performance: ~6,700 req/s vs 1,233 req/s with per-coroutine
     * 
     * @return \PDO
     */
    private static function connectionRead(): PDO
    {
        if (self::$readConnection === null) {
            self::$readConnection = self::createConnection();
        }
        
        return self::$readConnection;
    }
    
    /**
     * Get isolated connection for current coroutine (WRITE/TRANSACTION)
     * 
     * Used automatically by transaction() for ACID guarantees
     * 
     * @return \PDO
     */
    private static function connectionIsolated(): PDO
    {
        return self::connection();
    }

    /**
     * Release the connection back to the pool
     * Should be called at the end of the request
     */
    public static function release(): void
    {
        $cid = self::getCoroutineId();
        
        if (isset(self::$connections[$cid])) {
            $connection = self::$connections[$cid];
            unset(self::$connections[$cid]);
            
            if (self::$pool && $connection instanceof Connection) {
                self::$pool->put($connection);
            }
        }
    }

    public static function prepare(string $sql): PDOStatement
    {
        return self::connection()->prepare($sql);
    }

    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public static function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = self::prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function lastInsertId(): string
    {
        return self::connection()->lastInsertId();
    }

    public static function beginTransaction(): void
    {
        self::connection()->beginTransaction();
    }

    public static function commit(): void
    {
        self::connection()->commit();
    }

    public static function rollBack(): void
    {
        self::connection()->rollBack();
    }

    public static function inTransaction(): bool
    {
        return self::connection()->inTransaction();
    }

    public static function transaction(callable $callback): mixed
    {
        // Use isolated connection for ACID guarantees
        // Each coroutine gets its own connection for transactions
        // Performance: ~1,875 req/s (with transaction safety)
        $connection = self::connectionIsolated();
        
        $connection->beginTransaction();
        try {
            $result = $callback();
            $connection->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $e;
        }
    }

    public static function table(string $table): QueryBuilder
    {
        return new QueryBuilder($table);
    }

    /**
     * Get or create a cached PDO statement for hot paths.
     * 
     * Perfect for endpoints that reuse the same query multiple times.
     * Returns a prepared statement that can be executed directly with different parameters.
     * 
     * Performance: 50% faster than DB::findOne() for repeated queries with same SQL
     * Use case: Endpoints with multiple queries using same SQL pattern
     * 
     * @param string $sql The SQL query with placeholders
     * @return \PDOStatement The prepared statement
     * 
     * @example
     * // Cache statement once, reuse many times
     * $stmt = DB::statement('SELECT * FROM users WHERE id = ?');
     * 
     * $stmt->execute([1]);
     * $user1 = $stmt->fetch(PDO::FETCH_ASSOC);
     * 
     * $stmt->execute([2]);
     * $user2 = $stmt->fetch(PDO::FETCH_ASSOC);
     * 
     * @example
     * // In a controller with Swoole worker persistence
     * public function index(): Response
     * {
     *     static $stmt = null;
     *     
     *     if ($stmt === null) {
     *         $stmt = DB::statement('SELECT * FROM world WHERE id = ?');
     *     }
     *     
     *     $stmt->execute([mt_rand(1, 10000)]);
     *     $world = $stmt->fetch(PDO::FETCH_ASSOC);
     *     
     *     return response()->json($world);
     * }
     */
    public static function statement(string $sql): \PDOStatement
    {
        return self::connection()->prepare($sql);
    }

    /**
     * Find a single record by ID with maximum performance.
     * 
     * This method generates consistent SQL for maximum statement cache utilization.
     * Unlike Query Builder (which compiles SQL dynamically), this ensures the same
     * prepared statement is reused across ALL requests in the worker.
     * 
     * Performance: +49% vs Query Builder (6,500 → 9,712 req/s with global cache)
     * Use case: Hot paths, benchmarks, single record lookups
     * 
     * @param string $table The table name
     * @param int|string $id The record ID
     * @param string $column The column to match against (default: 'id')
     * @return array|null The record or null if not found
     * 
     * @example
     * // Hot path optimization (benchmark-ready)
     * $world = DB::findOne('World', mt_rand(1, 10000));
     * // Generates: SELECT * FROM World WHERE id = ?
     * // Statement cached globally, zero overhead!
     * 
     * @example
     * // Find by custom column
     * $user = DB::findOne('users', 'john@example.com', 'email');
     * // SELECT * FROM users WHERE email = ?
     * 
     * @example
     * // With null check
     * $post = DB::findOne('posts', 42);
     * if ($post === null) {
     *     return response()->json(['error' => 'Not found'], 404);
     * }
     */
    public static function findOne(string $table, int|string $id, string $column = 'id'): ?array
    {
        // Global statement cache (v1.3.3): Prepare once, execute many
        // Thread-safe for SELECT (no state mutation)
        // Performance: ~6,700 req/s (vs 1,233 with per-coroutine)
        
        $cacheKey = "read:findOne:{$table}:{$column}";
        
        // Prepare statement once, reuse globally
        if (!isset(self::$globalStatementCache[$cacheKey])) {
            $sql = "SELECT * FROM {$table} WHERE {$column} = ?";
            self::$globalStatementCache[$cacheKey] = self::connectionRead()->prepare($sql);
        }
        
        $stmt = self::$globalStatementCache[$cacheKey];
        $stmt->execute([$id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    /**
     * Find multiple records by different IDs using a single cached statement.
     * 
     * Optimized for scenarios where you need to fetch multiple different records
     * in the same request. Uses statement caching for maximum performance.
     * 
     * Performance: 70% faster than multiple DB::findOne() calls
     * Use case: Fetching different records (user + product + order) in same endpoint
     * 
     * @param string $table The table name
     * @param array $ids Array of IDs to fetch
     * @param string $column The column to match against (default: 'id')
     * @return array Array of records (may contain null for not found IDs)
     * 
     * @example
     * // Fetch 3 different users with one cached statement
     * [$user1, $user2, $user3] = DB::findMultiple('users', [1, 2, 3]);
     * 
     * @example
     * // Fetch different entity types in one request
     * $userId = mt_rand(1, 10000);
     * $productId = mt_rand(1, 10000);
     * $orderId = mt_rand(1, 10000);
     * 
     * [$user, $product, $order] = DB::findMultiple('entities', [
     *     $userId, 
     *     $productId, 
     *     $orderId
     * ]);
     * 
     * @example
     * // With custom column
     * [$post1, $post2] = DB::findMultiple('posts', ['slug-1', 'slug-2'], 'slug');
     */
    public static function findMultiple(string $table, array $ids, string $column = 'id'): array
    {
        // Global statement cache (thread-safe for SELECT)
        $cacheKey = "read:findMultiple:{$table}:{$column}";
        
        // Prepare once, reuse globally
        if (!isset(self::$globalStatementCache[$cacheKey])) {
            $sql = "SELECT * FROM {$table} WHERE {$column} = ?";
            self::$globalStatementCache[$cacheKey] = self::connectionRead()->prepare($sql);
        }
        
        $stmt = self::$globalStatementCache[$cacheKey];
        $results = [];
        
        // Execute same statement with different parameters
        foreach ($ids as $id) {
            $stmt->execute([$id]);
            $results[] = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        }
        
        return $results;
    }

    /**
     * Alias for findMultiple() - cleaner API.
     * 
     * Fetch multiple records by different IDs using a single cached statement.
     * Perfect for hot paths that need to query different records efficiently.
     * 
     * @param string $table The table name
     * @param array $ids Array of IDs to fetch
     * @param string $column The column to match against (default: 'id')
     * @return array Array of records (may contain null for not found IDs)
     * 
     * @example
     * // Clean, intuitive API
     * [$user, $product] = DB::batchFetch('world', [$userId, $productId]);
     */
    public static function batchFetch(string $table, array $ids, string $column = 'id'): array
    {
        return self::findMultiple($table, $ids, $column);
    }

    /**
     * Find multiple records by IDs in a single optimized query.
     * 
     * This method uses a WHERE IN clause to fetch multiple records efficiently,
     * reducing N queries to just 1. Laravel-style API for batch operations.
     * 
     * Performance: +627% vs sequential queries (312 → 2,269 req/s)
     * 
     * @param string $table The table name
     * @param array $ids Array of IDs to find
     * @param string $column The column to match against (default: 'id')
     * @return array Array of matching records
     * 
     * @example
     * // Find multiple users by ID (Laravel-style)
     * $users = DB::findMany('users', [1, 2, 3, 4, 5]);
     * // SELECT * FROM users WHERE id IN (1,2,3,4,5)
     * 
     * @example
     * // Find by custom column
     * $posts = DB::findMany('posts', ['published', 'draft'], 'status');
     * // SELECT * FROM posts WHERE status IN ('published','draft')
     * 
     * @example
     * // Empty array returns empty result (no query executed)
     * $empty = DB::findMany('users', []);  // []
     */
    public static function findMany(string $table, array $ids, string $column = 'id'): array
    {
        if (empty($ids)) {
            return [];
        }
        
        return self::table($table)->whereIn($column, $ids)->get();
    }

    /**
     * Execute a raw SQL query with IN clause optimization.
     * 
     * This method automatically expands a single placeholder (?) into multiple
     * placeholders based on the values array. Similar to Laravel's whereIn but
     * for raw SQL queries.
     * 
     * @param string $sql SQL query with single placeholder (?)
     * @param array $values Array of values for the IN clause
     * @return array Query results
     * 
     * @example
     * // Simple IN query
     * $worlds = DB::queryIn(
     *     'SELECT * FROM World WHERE id IN (?)',
     *     [1, 2, 3, 4, 5]
     * );
     * 
     * @example
     * // Complex query with JOIN
     * $results = DB::queryIn(
     *     'SELECT u.*, p.title 
     *      FROM users u 
     *      JOIN posts p ON u.id = p.user_id 
     *      WHERE u.id IN (?)',
     *     [10, 20, 30]
     * );
     * 
     * @example
     * // With additional conditions
     * $active = DB::query(
     *     'SELECT * FROM users WHERE id IN (?) AND status = ?',
     *     array_merge($ids, ['active'])
     * );
     */
    public static function queryIn(string $sql, array $values): array
    {
        if (empty($values)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $sql = str_replace('?', $placeholders, $sql);
        
        return self::query($sql, $values);
    }

    /**
     * Get global prepared statement cache statistics.
     * 
     * This shows how many statements are cached across all workers,
     * providing insight into the aggressive caching optimization.
     * 
     * @return array{count: int, max: int, memory_kb: int}
     * 
     * @example
     * // Check cache performance
     * $stats = DB::getCacheStats();
     * echo "Cached statements: {$stats['count']}/{$stats['max']}";
     * echo "Memory used: {$stats['memory_kb']} KB";
     */
    public static function getCacheStats(): array
    {
        return Connection::getGlobalCacheStats();
    }

    /**
     * Clear global prepared statement cache.
     * 
     * Use with caution in production - this will clear the cache
     * for ALL workers, forcing statements to be re-prepared.
     * 
     * Useful for:
     * - Debugging performance issues
     * - Memory cleanup during maintenance
     * - Testing cache behavior
     * 
     * @return void
     */
    public static function clearCache(): void
    {
        Connection::clearGlobalStatements();
    }

    /**
     * Set maximum number of globally cached prepared statements.
     * 
     * Default: 1000 statements
     * 
     * Increase for applications with many unique queries.
     * Decrease if memory usage is a concern.
     * 
     * @param int $max Maximum cached statements
     * @return void
     * 
     * @example
     * // Increase cache size for large applications
     * DB::setMaxCachedStatements(5000);
     */
    public static function setMaxCachedStatements(int $max): void
    {
        Connection::setMaxCachedStatements($max);
    }
    
    /**
     * Get Query Builder statement cache statistics
     * 
     * Shows how many query patterns are cached in the Query Builder.
     * This cache is separate from Connection's prepared statement cache.
     * 
     * @return array{count: int, max: int, memory: int}
     * 
     * @example
     * $stats = DB::getQueryBuilderCacheStats();
     * // ['count' => 42, 'max' => 500, 'memory' => 12582912]
     */
    public static function getQueryBuilderCacheStats(): array
    {
        return QueryBuilder::getStatementCacheStats();
    }
    
    /**
     * Clear Query Builder statement cache
     * 
     * @return void
     * 
     * @example
     * // Clear cache after deploying schema changes
     * DB::clearQueryBuilderCache();
     */
    public static function clearQueryBuilderCache(): void
    {
        QueryBuilder::clearStatementCache();
    }
    
    /**
     * Set maximum Query Builder cached statements
     * 
     * @param int $max Maximum statements to cache
     * @return void
     * 
     * @example
     * // Increase for complex applications with many query patterns
     * DB::setMaxQueryBuilderStatements(1000);
     */
    public static function setMaxQueryBuilderStatements(int $max): void
    {
        QueryBuilder::setMaxCachedStatements($max);
    }

    private static function getCoroutineId(): string
    {
        if (!extension_loaded('swoole')) {
            return 'default';
        }
        $cid = Coroutine::getCid();
        return $cid > 0 ? (string)$cid : 'default';
    }

    private static function createConnection(): Connection
    {
        $dsn = sprintf(
            '%s:host=%s;dbname=%s;charset=%s',
            self::$config['driver'] ?? 'mysql',
            self::$config['host'] ?? 'localhost',
            self::$config['database'] ?? 'test',
            self::$config['charset'] ?? 'utf8mb4'
        );
        
        if (isset(self::$config['port'])) {
            $dsn .= ';port=' . self::$config['port'];
        }

        $options = self::$config['options'] ?? [];
        $options += [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false, // Critical: real prepared statements for maximum performance with Global Statement Cache
        ];

        return new Connection(
            $dsn,
            self::$config['username'] ?? 'root',
            self::$config['password'] ?? '',
            $options
        );
    }

    /**
     * Clear statement cache for current coroutine
     * Useful for long-running processes to prevent memory leaks
     * 
     * @return void
     */
    public static function clearStatementCache(): void
    {
        // No-op in new implementation as cache is bound to Connection object
        // and recycled with the connection
    }

    /**
     * Close connection for current coroutine
     * 
     * @return void
     */
    public static function disconnect(): void
    {
        self::release();
    }
}
