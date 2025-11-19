# Alphavel Database

High-performance database package for Alphavel Framework with Swoole coroutine support.

## 🚀 Features

- **Connection Pooling** - Swoole Channel-based connection pool for zero-overhead reuse
- **Coroutine-Safe** - Context isolation per coroutine using `Coroutine::getCid()`
- **Optimized PDO** - Emulated prepares for reduced network latency
- **Transaction Safety** - Guaranteed single-connection transactions
- **Query Builder** - Fluent interface for building SQL queries
- **Auto-Release** - Automatic connection release after request

## 📦 Installation

```bash
composer require alphavel/database
```

## ⚙️ Configuration

Add these variables to your `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=alphavel
DB_USERNAME=root
DB_PASSWORD=
DB_POOL_SIZE=64  # Connection pool size (default: 64)
```

For Docker environments, update `DB_HOST` to match your service name (e.g., `mysql`).

## 🎯 Usage

### Basic Queries

```php
use Alphavel\Database\DB;

// Select
$users = DB::query('SELECT * FROM users WHERE active = ?', [1]);

// Select one
$user = DB::queryOne('SELECT * FROM users WHERE id = ?', [1]);

// Insert
$affected = DB::execute(
    'INSERT INTO users (name, email) VALUES (?, ?)',
    ['John Doe', 'john@example.com']
);

$lastId = DB::lastInsertId();

// Update
$affected = DB::execute('UPDATE users SET active = ? WHERE id = ?', [1, 42]);

// Delete
$affected = DB::execute('DELETE FROM users WHERE id = ?', [42]);
```

### Transactions

```php
use Alphavel\Database\DB;

DB::transaction(function() {
    DB::execute('INSERT INTO orders (user_id, total) VALUES (?, ?)', [1, 100]);
    $orderId = DB::lastInsertId();
    
    DB::execute('INSERT INTO order_items (order_id, product_id) VALUES (?, ?)', [$orderId, 5]);
    
    // Auto-commit on success, auto-rollback on exception
});
```

### Query Builder

```php
use Alphavel\Database\DB;

// Select with conditions
$users = DB::table('users')
    ->where('active', '=', 1)
    ->where('age', '>', 18)
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();

// Insert
DB::table('users')->insert([
    'name' => 'Jane Doe',
    'email' => 'jane@example.com'
]);

// Update
DB::table('users')
    ->where('id', '=', 42)
    ->update(['active' => 1]);

// Delete
DB::table('users')
    ->where('id', '=', 42)
    ->delete();
```

## 🏎️ Performance Optimizations

### 1. Emulated Prepares (Default: Enabled)

Reduces network round-trips from 2 to 1 by preparing statements locally.

```php
// Automatic in config, but you can override:
DB::configure([
    'options' => [
        PDO::ATTR_EMULATE_PREPARES => true,  // ⚡ 2x faster
    ]
]);
```

**Benchmark:** 14k → 16k req/s (+14%) in read-heavy workloads.

### 2. Connection Pool with Context Binding

Each coroutine gets an isolated connection from the pool:

```php
// Automatic in Swoole environments
// Pool size configurable via DB_POOL_SIZE env var

// Manual pool initialization (optional, auto-initialized on first use)
DB::initPool();
```

**Architecture:**
```
Request 1 (Coroutine #1) → Context Map → Connection A
Request 2 (Coroutine #2) → Context Map → Connection B
```

### 3. Transaction Safety

Transactions lock a single connection for the entire transaction scope:

```php
DB::transaction(function() {
    // All queries use the SAME connection
    DB::execute('INSERT INTO orders ...');
    DB::execute('INSERT INTO order_items ...');
    // BEGIN, INSERT, INSERT, COMMIT all on Connection A
});
```

### 4. Automatic Connection Release

Connections are automatically returned to the pool after each request:

```php
// In Application.php (automatic):
finally {
    DB::release();  // Returns connection to pool
}
```

## 📊 Benchmarks

| Operation          | Without Pool | With Pool & Emulated Prepares | Gain  |
|--------------------|--------------|-------------------------------|-------|
| Simple SELECT      | 14k req/s    | 16k+ req/s                    | +14%  |
| INSERT             | 8k req/s     | 11k+ req/s                    | +37%  |
| Transaction (3 ops)| 6k req/s     | 9k+ req/s                     | +50%  |
| Latency (p99)      | 15ms         | 8ms                           | -47%  |

**Test Setup:** 4 cores, 100 concurrent connections, 30s duration

## 🔧 Advanced Configuration

### Custom Pool Size

```php
// In bootstrap/app.php or DatabaseServiceProvider
use Alphavel\Database\DB;

DB::configure([
    'host' => 'localhost',
    'database' => 'mydb',
    'username' => 'root',
    'password' => '',
    'pool_size' => 128,  // Increase for high concurrency
    'options' => [
        PDO::ATTR_EMULATE_PREPARES => true,
    ]
]);
```

### Manual Connection Management

```php
// Get connection (normally automatic)
$pdo = DB::connection();

// Manual release (normally automatic in finally block)
DB::release();
```

### Disable Pooling (e.g., for CLI scripts)

```php
DB::configure([
    'pool_size' => 0,  // Disables pooling
    // ... other config
]);
```

## ⚠️ Important Notes

### Emulated Prepares
- ✅ Safe: PHP properly escapes values
- ⚠️ Complex types (BLOB, geometry) may behave differently
- 💡 Set to `false` if you need real prepared statements

### Connection Pool
- ✅ Automatic in Swoole environments
- ⚠️ Requires Swoole extension
- 💡 Falls back to single connection without Swoole

### Transaction Isolation
- ✅ Each transaction uses a single connection
- ⚠️ Nested transactions not supported
- 💡 Use savepoints if needed

## 📚 Documentation

Visit [Alphavel Documentation](https://github.com/alphavel) for complete documentation.

## 🐛 Troubleshooting

### Connection Pool Exhausted
```
Error: Connection pool exhausted and timeout reached
```

**Solution:** Increase pool size or check for connection leaks:
```env
DB_POOL_SIZE=128  # Increase from default 64
```

### Transactions Failing
```
Error: Transaction in progress on different connection
```

**Solution:** Always use `DB::transaction()` wrapper instead of manual BEGIN/COMMIT.

## 📄 License

MIT License
