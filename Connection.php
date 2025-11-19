<?php

namespace Alphavel\Database;

use PDO;
use PDOStatement;

class Connection extends PDO
{
    private array $statements = [];
    private string $id;
    private float $lastUsedAt;

    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null)
    {
        parent::__construct($dsn, $username, $password, $options);
        $this->id = uniqid('conn_', true);
        $this->lastUsedAt = microtime(true);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        // Simple hash for caching
        $hash = md5($query);

        if (isset($this->statements[$hash])) {
            return $this->statements[$hash];
        }

        $stmt = parent::prepare($query, $options);
        
        if ($stmt) {
            $this->statements[$hash] = $stmt;
        }

        return $stmt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function touch(): void
    {
        $this->lastUsedAt = microtime(true);
    }

    public function getLastUsedAt(): float
    {
        return $this->lastUsedAt;
    }
    
    public function clearStatements(): void
    {
        $this->statements = [];
    }
}
