<?php

namespace Alphavel\Database;

use PDO;
use PDOException;

class Database
{
    private ?PDO $pdo = null;

    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function connection(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }

        return $this->pdo;
    }

    private function connect(): void
    {
        $dsn = sprintf(
            '%s:host=%s;dbname=%s;charset=%s',
            $this->config['driver'] ?? 'mysql',
            $this->config['host'] ?? 'localhost',
            $this->config['database'] ?? 'test',
            $this->config['charset'] ?? 'utf8mb4'
        );

        try {
            $this->pdo = new PDO(
                $dsn,
                $this->config['username'] ?? 'root',
                $this->config['password'] ?? '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => $this->config['persistent'] ?? false,
                ]
            );
        } catch (PDOException $e) {
            throw new DatabaseException('Connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function lastInsertId(): string
    {
        return $this->connection()->lastInsertId();
    }

    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

    public function transaction(callable $callback): mixed
    {
        $this->connection()->beginTransaction();

        try {
            $result = $callback($this);
            $this->connection()->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->connection()->rollBack();

            throw $e;
        }
    }
}
