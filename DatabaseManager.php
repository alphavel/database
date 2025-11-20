<?php

namespace Alphavel\Database;

/**
 * Database Manager
 * 
 * Manages database connections and provides a clean API for database operations.
 * Replaces anonymous proxy class for better IDE support and type hinting.
 * 
 * @package Alphavel\Database
 * @version 2.0.0
 */
class DatabaseManager
{
    private Database $database;

    public function __construct(array $config)
    {
        $this->database = new Database($config);
    }

    /**
     * Get the underlying Database instance
     */
    public function getDatabase(): Database
    {
        return $this->database;
    }

    /**
     * Execute a SELECT query
     */
    public function query(string $sql, array $params = []): array
    {
        return $this->database->query($sql, $params);
    }

    /**
     * Execute an INSERT/UPDATE/DELETE query
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->database->execute($sql, $params);
    }

    /**
     * Get PDO connection
     */
    public function connection(): \PDO
    {
        return $this->database->connection();
    }

    /**
     * Get a query builder instance
     */
    public function table(string $table): QueryBuilder
    {
        return $this->database->table($table);
    }

    /**
     * Execute a callback within a transaction
     */
    public function transaction(callable $callback): mixed
    {
        return $this->database->transaction($callback);
    }

    /**
     * Get the last inserted ID
     */
    public function lastInsertId(): string
    {
        return $this->database->lastInsertId();
    }

    /**
     * Magic method to forward static calls to Database class
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->database->$method(...$arguments);
    }
}
