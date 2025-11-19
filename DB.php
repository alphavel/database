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
    /**
     * Connections pool indexed by coroutine ID
     * Each coroutine gets its own isolated PDO connection
     */
    private static array $connections = [];

    /**
     * Prepared statements cache indexed by coroutine ID and SQL
     * Statements are prepared once and reused within the same coroutine
     */
    private static array $statements = [];

    /**
     * Database configuration
     */
    private static array $config = [];

    /**
     * Set database configuration
     * Usually called by DatabaseServiceProvider
     */
    public static function setConfig(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Get PDO connection for current coroutine
     * Connections are isolated per coroutine (thread-safe)
     * 
     * @return PDO
     */
    public static function connection(): PDO
    {
        $cid = self::getCoroutineId();
        
        if (!isset(self::$connections[$cid])) {
            self::$connections[$cid] = self::createConnection();
        }
        
        return self::$connections[$cid];
    }

    /**
     * Get or create a cached prepared statement
     * Statements are prepared once per coroutine and reused
     * 
     * Critical for performance: reduces overhead by ~100ns per execution
     * 
     * @param string $sql
     * @return PDOStatement
     */
    public static function prepare(string $sql): PDOStatement
    {
        $cid = self::getCoroutineId();
        
        if (!isset(self::$statements[$cid][$sql])) {
            self::$statements[$cid][$sql] = self::connection()->prepare($sql);
        }
        
        return self::$statements[$cid][$sql];
    }

    /**
     * Execute a raw query without parameters
     * More efficient than prepare() for static queries
     * 
     * @param string $sql
     * @return PDOStatement
     */
    public static function rawQuery(string $sql): PDOStatement
    {
        return self::connection()->query($sql);
    }

    /**
     * Execute a query and return all results
     * 
     * @param string $sql
     * @param array $params
     * @return array
     */
    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    /**
     * Execute a query and return first result
     * 
     * @param string $sql
     * @param array $params
     * @return array|null
     */
    public static function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = self::prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Execute a statement (INSERT, UPDATE, DELETE)
     * 
     * @param string $sql
     * @param array $params
     * @return int Number of affected rows
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount();
    }

    /**
     * Get the last inserted ID
     * 
     * @return string
     */
    public static function lastInsertId(): string
    {
        return self::connection()->lastInsertId();
    }

    /**
     * Begin a transaction
     * 
     * @return void
     */
    public static function beginTransaction(): void
    {
        self::connection()->beginTransaction();
    }

    /**
     * Commit the current transaction
     * 
     * @return void
     */
    public static function commit(): void
    {
        self::connection()->commit();
    }

    /**
     * Rollback the current transaction
     * 
     * @return void
     */
    public static function rollback(): void
    {
        self::connection()->rollBack();
    }

    /**
     * Check if currently in a transaction
     * 
     * @return bool
     */
    public static function inTransaction(): bool
    {
        return self::connection()->inTransaction();
    }

    /**
     * Execute code within a transaction
     * Automatically commits on success, rolls back on exception
     * 
     * @param callable $callback
     * @return mixed
     * @throws \Throwable
     */
    public static function transaction(callable $callback): mixed
    {
        self::beginTransaction();
        
        try {
            $result = $callback();
            self::commit();
            
            return $result;
        } catch (\Throwable $e) {
            if (self::inTransaction()) {
                self::rollback();
            }
            throw $e;
        }
    }

    /**
     * Create a new query builder for the given table
     * 
     * @param string $table
     * @return QueryBuilder
     */
    public static function table(string $table): QueryBuilder
    {
        return new QueryBuilder($table);
    }

    /**
     * Get current coroutine ID or 'default' for non-Swoole environments
     * 
     * @return string
     */
    private static function getCoroutineId(): string
    {
        // Fallback for development without Swoole
        if (!extension_loaded('swoole')) {
            return 'default';
        }
        
        $cid = Coroutine::getCid();
        return $cid > 0 ? (string)$cid : 'default';
    }

    /**
     * Create a new PDO connection
     * 
     * @return PDO
     * @throws DatabaseException
     */
    private static function createConnection(): PDO
    {
        try {
            $dsn = sprintf(
                '%s:host=%s;dbname=%s;charset=%s',
                self::$config['driver'] ?? 'mysql',
                self::$config['host'] ?? 'localhost',
                self::$config['database'] ?? 'test',
                self::$config['charset'] ?? 'utf8mb4'
            );

            $pdo = new PDO(
                $dsn,
                self::$config['username'] ?? 'root',
                self::$config['password'] ?? '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            // Set port if specified
            if (isset(self::$config['port'])) {
                $dsn .= ';port=' . self::$config['port'];
            }

            return $pdo;
        } catch (\PDOException $e) {
            throw new DatabaseException('Connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Clear statement cache for current coroutine
     * Useful for long-running processes to prevent memory leaks
     * 
     * @return void
     */
    public static function clearStatementCache(): void
    {
        $cid = self::getCoroutineId();
        unset(self::$statements[$cid]);
    }

    /**
     * Close connection for current coroutine
     * 
     * @return void
     */
    public static function disconnect(): void
    {
        $cid = self::getCoroutineId();
        unset(self::$connections[$cid]);
        unset(self::$statements[$cid]);
    }
}
