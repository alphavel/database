<?php

namespace Alphavel\Database;

use Swoole\Coroutine\Channel;
use RuntimeException;
use Throwable;

class ConnectionPool
{
    private Channel $pool;
    private array $config;
    private int $size;
    private int $count = 0;

    /**
     * Create intelligent connection pool
     * 
     * Formula: pool_size = workers × connections_per_worker
     * 
     * @param array $config Database configuration
     * @param int $size Pool size (auto-calculated if 0)
     */
    public function __construct(array $config, int $size = 0)
    {
        $this->config = $config;
        
        // Intelligent pool sizing (auto-adapt to workers)
        if ($size === 0) {
            $workers = \swoole_cpu_num() * 2; // Swoole default
            $connectionsPerWorker = $config['connections_per_worker'] ?? 5;
            $this->size = $workers * $connectionsPerWorker;
            
            echo "[ConnectionPool] Auto-sized: {$workers} workers × {$connectionsPerWorker} = {$this->size} connections\n";
        } else {
            $this->size = $size;
        }
        
        $this->pool = new Channel($this->size);
    }

    /**
     * Pre-warm pool (CRITICAL for first-request performance!)
     */
    public function fill(): void
    {
        echo "[ConnectionPool] Pre-warming {$this->size} connections...\n";
        $start = microtime(true);
        
        while ($this->count < $this->size) {
            $this->makeConnection();
        }
        
        $elapsed = round((microtime(true) - $start) * 1000, 2);
        echo "[ConnectionPool] ✅ Pre-warmed {$this->count} connections in {$elapsed}ms\n";
    }

    public function get(float $timeout = 5.0): Connection
    {
        if ($this->pool->isEmpty() && $this->count < $this->size) {
            $this->makeConnection();
        }

        $connection = $this->pool->pop($timeout);
        
        if ($connection === false) {
            throw new RuntimeException('Connection pool exhausted and timeout reached.');
        }

        // Heartbeat / Health check
        if (!$this->check($connection)) {
            $this->count--;
            // Try to get another one, recursively
            // Ideally we should create a new one to replace the broken one immediately if possible
            // But for simplicity, let's just call get() again which will trigger makeConnection if needed
            return $this->get($timeout); 
        }

        return $connection;
    }

    public function put(Connection $connection): void
    {
        $this->pool->push($connection);
    }

    private function makeConnection(): void
    {
        try {
            $connection = $this->createConnectionInstance();
            $this->put($connection);
            $this->count++;
        } catch (Throwable $e) {
            // Log error or handle it?
            // For now, we might just let it fail when get() is called if pool is empty
            throw $e;
        }
    }

    private function createConnectionInstance(): Connection
    {
        $dsn = sprintf(
            '%s:host=%s;dbname=%s;charset=%s',
            $this->config['driver'] ?? 'mysql',
            $this->config['host'] ?? 'localhost',
            $this->config['database'] ?? 'test',
            $this->config['charset'] ?? 'utf8mb4'
        );

        if (isset($this->config['port'])) {
            $dsn .= ';port=' . $this->config['port'];
        }

        $options = $this->config['options'] ?? [];
        // Ensure we have basic PDO options
        $options += [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => true, // Otimização: reduz latência pela metade
            \PDO::ATTR_PERSISTENT => $this->config['persistent'] ?? true, // Conexões persistentes: +1,769% performance
        ];

        return new Connection(
            $dsn,
            $this->config['username'] ?? 'root',
            $this->config['password'] ?? '',
            $options
        );
    }

    private function check(Connection $connection): bool
    {
        try {
            // Lightweight check
            $connection->query('SELECT 1');
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
    
    public function getSize(): int
    {
        return $this->size;
    }
    
    public function getCount(): int
    {
        return $this->count;
    }
}
