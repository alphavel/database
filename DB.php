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
     * Configure the database subsystem
     */
    public static function configure(array $config): void
    {
        self::$config = $config;
        
        // Initialize pool if in Swoole environment and pool size > 0
        if (extension_loaded('swoole') && ($config['pool_size'] ?? 64) > 0) {
            self::$pool = new ConnectionPool($config, (int)($config['pool_size'] ?? 64));
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
        self::beginTransaction();
        try {
            $result = $callback();
            self::commit();
            return $result;
        } catch (\Throwable $e) {
            if (self::inTransaction()) {
                self::rollBack();
            }
            throw $e;
        }
    }

    public static function table(string $table): QueryBuilder
    {
        return new QueryBuilder($table);
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
            PDO::ATTR_EMULATE_PREPARES => false,
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
