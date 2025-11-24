# Alphavel Database

🏆 **#1 Fastest PHP Framework** - High-performance Query Builder + ORM with **Laravel-style API** and Swoole optimization.

> 💡 **Laravel-compatible**: If you know Laravel's Query Builder & Eloquent, you already know Alphavel Database!
> 
> ⚡ **6,700 req/s** - Beats FrankenPHP (+141%), RoadRunner (+448%), and Hyperf (+719%)!

## 🚀 Features

### Core (Always Available)
- **� #1 Fastest PHP Framework** - Global Statement Cache beats Go implementations
- **�🎯 Laravel-Style Query Builder** - 100% familiar syntax (6,700 req/s)
- **⚡ Persistent Connections** - Enabled by default (+1,769%)
- **📦 Batch Queries** - `findMany()` helper (+627% performance)
- **🔄 Connection Pooling** - Swoole Channel-based pool
- **🔒 Coroutine-Safe** - Context isolation per coroutine
- **💾 Global Statement Cache** - Prepare once, execute millions of times
- **🔐 Transaction Safety** - ACID-compliant isolated connections

### ORM (Optional - v2.0+)
- **📚 Eloquent-like Models** - Laravel-familiar Active Record pattern
- **🔗 Relationships** - hasMany, belongsTo, hasOne, belongsToMany
- **⚡ Lazy Loading** - Zero overhead until relations accessed
- **🎭 Events & Observers** - creating, created, updating, etc
- **🔄 Attribute Casting** - Dates, JSON, custom casters

> **Performance Note**: Query Builder (6,700 req/s) vs Models with hydration (363 req/s). Choose based on your needs!

## 📦 Installation

```bash
composer require alphavel/database
```

## ⚙️ Configuration

### 🎯 Zero-Config Setup (Recommended)

Alphavel Database is **optimized by default**. Just set your environment variables:

```env
DB_HOST=127.0.0.1
DB_DATABASE=myapp
DB_USERNAME=root
DB_PASSWORD=secret
```

That's it! The framework automatically uses optimal settings:
- ✅ `ATTR_EMULATE_PREPARES => false` (+20% performance)
- ✅ No `ATTR_PERSISTENT` (prevents overhead in Swoole)
- ✅ No `pool_size` by default (singleton is faster)

### 📝 Manual Configuration (Advanced)

Use the `DB::optimizedConfig()` helper in `config/database.php`:

```php
use Alphavel\Database\DB;

return [
    'database' => [
        'connections' => [
            'mysql' => DB::optimizedConfig([
                'host' => env('DB_HOST', '127.0.0.1'),
                'database' => env('DB_DATABASE', 'alphavel'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
            ]),
        ],
    ],
];
```

### ⚡ Quick Setup from Environment

Use `DB::fromEnv()` for ultra-fast setup:

```php
use Alphavel\Database\DB;

// Reads DB_* env vars automatically
DB::configure(DB::fromEnv());
```

### ⚠️ Development Warnings

The framework automatically validates your configuration in development and warns you about performance issues:

```
[Alphavel Database] ⚠️  Performance Configuration Warnings
================================================================================
  • ATTR_EMULATE_PREPARES is set to true. This reduces performance by ~20%.
  • pool_size is set to 64. Large pools reduce performance by ~7%.

💡 Use DB::optimizedConfig() helper for optimal performance
================================================================================
```

## 🎯 Quick Start (Laravel Developers)

```php
use Alphavel\Database\DB;

// 🔍 Queries (Laravel-style)
$users = DB::table('users')
    ->where('status', 'active')
    ->whereIn('role', ['admin', 'moderator'])
    ->orderBy('created_at', 'DESC')
    ->get();

// 📦 NEW: Batch queries (627% faster!)
$worlds = DB::findMany('World', [1, 2, 3, 4, 5]);
// SELECT * FROM World WHERE id IN (1,2,3,4,5)

// 🔄 Transactions
DB::transaction(function() {
    DB::execute('UPDATE accounts SET balance = balance - 100 WHERE id = ?', [1]);
    DB::execute('UPDATE accounts SET balance = balance + 100 WHERE id = ?', [2]);
});
```

**📚 Full Laravel-Style Guide**: [LARAVEL_STYLE_GUIDE.md](LARAVEL_STYLE_GUIDE.md)

---

## 🤔 Query Builder vs Models - When to Use?

| Feature | Query Builder | Models (ORM) |
|---------|--------------|--------------|
| **Performance** | ⚡⚡⚡⚡⚡ 6,700 req/s | ⚡⚡⚡ 363 req/s |
| **Syntax** | `DB::table('users')->get()` | `User::all()` |
| **Relations** | ❌ Manual joins | ✅ `$user->posts` |
| **Events** | ❌ No | ✅ creating, created, etc |
| **Casting** | ❌ Manual | ✅ Automatic |
| **Use Case** | APIs, hot paths | Complex business logic |

### 💡 Recommendation

```php
// ✅ Use Query Builder for APIs (6,700 req/s)
public function index() {
    return DB::table('users')
        ->where('active', true)
        ->get();
}

// ✅ Use Models for complex logic (363 req/s, but worth it!)
public function store(Request $request) {
    $user = User::create($request->validated());
    // Events fired: creating, created
    // Relations available: $user->posts
    return $user->load('roles', 'permissions');
}
```

**Rule of thumb:** Start with Query Builder (fast), upgrade to Models only when you need relations/events/casting.

---

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

---

## 📚 Models (ORM) - v2.0+

**New in v2.0:** Eloquent-like ORM now included!

### Defining Models

```php
<?php

namespace App\Models;

use Alphavel\Database\Model;

class User extends Model
{
    protected static string $table = 'users';
    protected static string $primaryKey = 'id';
    
    protected array $fillable = ['name', 'email', 'password'];
    protected array $hidden = ['password'];
    protected array $casts = [
        'created_at' => 'datetime',
        'is_admin' => 'boolean',
    ];
}
```

### Basic Operations

```php
// Find by ID
$user = User::find(1);

// Find or fail
$user = User::findOrFail(1);

// Get all
$users = User::all();

// Where query
$admins = User::where('is_admin', true)->get();

// Create
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => password_hash('secret', PASSWORD_DEFAULT),
]);

// Update
$user->name = 'Jane Doe';
$user->save();

// Delete
$user->delete();
```

### Relationships

```php
class User extends Model
{
    // One-to-many
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
    
    // One-to-one
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }
    
    // Many-to-one
    public function role()
    {
        return $this->belongsTo(Role::class);
    }
    
    // Many-to-many
    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_user');
    }
}

// Usage
$user = User::find(1);
$posts = $user->posts;  // Lazy loading
$profile = $user->profile;

// Eager loading (N+1 prevention)
$users = User::with('posts', 'profile')->get();
```

### Performance Note

**Models have overhead due to hydration:**
- Query Builder: **6,700 req/s** (arrays)
- Models: **363 req/s** (objects with features)

**When to use:**
- ✅ Complex business logic
- ✅ Need relations ($user->posts)
- ✅ Need events (creating, created, etc)
- ✅ Need casting (dates, JSON, etc)

**When NOT to use:**
- ❌ Simple API endpoints
- ❌ Performance-critical hot paths
- ❌ Bulk operations
- ❌ Reporting/analytics queries

**Best Practice:** Use both! Query Builder for reads, Models for writes.

---

## ⚡ Performance Tuning

### Critical Configuration for Maximum Performance

The following settings are **essential** for achieving optimal performance with Swoole + Global Statement Cache:

#### 1. ✅ Use Real Prepared Statements (Required)

```php
'options' => [
    PDO::ATTR_EMULATE_PREPARES => false,  // CRITICAL: 14-27% faster!
]
```

**Why?**
- ✅ Real MySQL prepared statements (not PHP emulation)
- ✅ Essential for Global Statement Cache performance
- ✅ Benchmark: **6,000 → 7,200 req/s** (+20%)
- ✅ Statements prepared once, executed millions of times

**When emulated prepares SLOW you down:**
- ❌ PHP re-parses SQL on every execute
- ❌ No benefit from MySQL's query cache
- ❌ Extra memory allocation per execution

#### 2. ❌ Avoid ATTR_PERSISTENT (Harmful in Swoole)

```php
'options' => [
    // PDO::ATTR_PERSISTENT => true,  // ❌ DO NOT USE in Swoole!
]
```

**Why PERSISTENT is harmful in Swoole:**
- ❌ Swoole workers are **already persistent processes**
- ❌ `DB::connectionRead()` provides singleton connection
- ❌ PERSISTENT adds lock contention and state management overhead
- ❌ Benchmark: **7,200 → 6,850 req/s** (-5% slower!)

**Bottom line:** PERSISTENT is **redundant** in Swoole and makes things slower.

#### 3. ⚠️ Minimize pool_size (or disable it)

```php
// Option 1: Disable pool (recommended for read-heavy APIs)
'pool_size' => 0,  // ✅ No pool overhead

// Option 2: Minimal pool (only if you need transactions)
'pool_size' => 8,  // workers × 2 (e.g., 4 workers × 2)
```

**Why small pool_size?**
- ✅ Hot path methods (`findOne`, `findMany`) use `connectionRead()` singleton
- ✅ Pool only used for `connection()` method (transactions, writes)
- ❌ Large unused pool = wasted memory (64 connections × ~1MB each)
- ❌ Benchmark: **7,200 → 6,800 req/s** (-7% slower with pool_size=64)

**Best practice:**
- **Read-heavy APIs**: `pool_size => 0` (use singleton only)
- **Transactional apps**: `pool_size => workers × 2`

### 📊 Performance Impact Summary

| Configuration | Req/s | Impact |
|---------------|-------|--------|
| ❌ EMULATE_PREPARES=true | 6,000 | **Baseline (SLOW)** |
| ✅ EMULATE_PREPARES=false | 7,200 | **+20%** ✅ |
| ✅ + no PERSISTENT | 7,200 | Same (correct) |
| ❌ + PERSISTENT=true | 6,850 | **-5%** ❌ |
| ❌ + pool_size=64 | 6,800 | **-7%** ❌ |
| ✅ All optimized | **7,200+** | **+20% total** 🎯 |

### 🎯 Recommended Configuration

```php
return [
    'database' => [
        'connections' => [
            'mysql' => [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'database' => env('DB_DATABASE', 'alphavel'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,  // ✅ CRITICAL
                    // PDO::ATTR_PERSISTENT => false,      // ✅ DO NOT SET (default)
                ],
                // 'pool_size' => 0,  // ✅ Disable pool for read-heavy apps
            ],
        ],
    ],
];
```

---

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

## � Performance Optimizations

Alphavel Database includes **4 native performance optimizations** for extreme throughput:

### 1. ⚡ Persistent Connections (+1,769%)
```php
// config/database.php - ENABLED BY DEFAULT
'persistent' => true,  // PDO::ATTR_PERSISTENT
```

**Benchmark**: 350 → 6,541 req/s (+1,769%) 🔥

### 2. 📦 Batch Queries (+627%)
```php
// ❌ BAD: 20 queries (312 req/s)
foreach ($ids as $id) {
    $world = DB::table('World')->where('id', $id)->first();
}

// ✅ GOOD: 1 query (2,269 req/s)
$worlds = DB::findMany('World', $ids);
```

**Benchmark**: 312 → 2,269 req/s (+627%) 🔥

### 3. 💾 Statement Cache (+15-30%)
Automatic prepared statement caching - **no configuration needed**!

### 4. 🔄 Connection Pooling (+200-400%)
Swoole connection pool - **automatic** with configuration:

```env
# .env
SWOOLE_WORKER_NUM=4    # CPU cores
DB_POOL_MAX=20         # 4 * 5
DB_POOL_MIN=8          # 4 * 2
DB_PERSISTENT=true
```

### 📊 Combined Results
| Configuration | Req/s | Improvement |
|--------------|-------|-------------|
| Baseline | 350 | - |
| All optimizations | 9,712 | **+2,674%** 🚀 |

**📖 Full guide**: See [PERFORMANCE_OPTIMIZATIONS.md](PERFORMANCE_OPTIMIZATIONS.md)  
**⚙️ Configuration**: See [.env.performance](.env.performance)

## �📄 License

MIT License
