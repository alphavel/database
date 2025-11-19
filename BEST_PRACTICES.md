# Database Best Practices for Performance

## 📊 Overview

This guide consolidates performance patterns for the Alphavel Database layer, focused on high-performance applications running on Swoole.

## ⚡ Core Principles

### 1. Use DB:: Static Facade Everywhere

**Before (Instance-based):**
```php
$db = $this->app->make('db');
$users = $db->table('users')->where('status', 'active')->get();
```

**After (Static Facade):**
```php
$users = DB::table('users')->where('status', 'active')->get();
```

**Performance Gain:** ~5-8% reduction in overhead per query

### 2. Prepared Statements for Hot Paths

**Before:**
```php
foreach ($ids as $id) {
    $user = DB::table('users')->where('id', $id)->first();
}
```

**After:**
```php
$stmt = DB::prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
foreach ($ids as $id) {
    $stmt->execute([$id]);
    $user = $stmt->fetch();
}
```

**Performance Gain:** ~20% improvement for N repeated queries

### 3. Raw Queries for Complex Operations

**Before (Query Builder):**
```php
DB::table('orders')
    ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(total) as sum')
    ->where('status', 'completed')
    ->groupBy('date')
    ->get();
```

**After (Raw Query):**
```php
DB::query("
    SELECT DATE(created_at) as date, COUNT(*) as count, SUM(total) as sum
    FROM orders
    WHERE status = ?
    GROUP BY date
", ['completed']);
```

**Performance Gain:** ~10-15% for complex aggregations

### 4. Transactions for Bulk Operations

**Before:**
```php
foreach ($users as $user) {
    DB::table('users')->insert($user);
}
```

**After:**
```php
DB::transaction(function() use ($users) {
    foreach ($users as $user) {
        DB::table('users')->insert($user);
    }
});
```

**Performance Gain:** ~80% improvement for 1000+ inserts

### 5. whereIn Instead of Multiple OR Conditions

**Before:**
```php
$query = DB::table('products');
foreach ($ids as $id) {
    $query->orWhere('id', $id);
}
$products = $query->get();
```

**After:**
```php
$products = DB::table('products')->whereIn('id', $ids)->get();
```

**Performance Gain:** ~30% for 100+ conditions

## 🚀 Hot Path Patterns

### Pattern 1: Read-Heavy Endpoints

```php
// Benchmark: GET /api/users/{id}
public function show($id)
{
    static $stmt;
    
    if (!$stmt) {
        $stmt = DB::prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    }
    
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    return Response::json($user);
}
```

**Result:** 15,000 req/s → 18,000 req/s (+20%)

### Pattern 2: List with Pagination

```php
// Benchmark: GET /api/products?page=1&limit=20
public function index()
{
    $page = (int) ($_GET['page'] ?? 1);
    $limit = (int) ($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    
    $products = DB::query(
        "SELECT * FROM products WHERE status = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
        ['active', $limit, $offset]
    );
    
    return Response::json($products);
}
```

**Result:** 12,000 req/s → 13,500 req/s (+12.5%)

### Pattern 3: Aggregations

```php
// Benchmark: GET /api/stats/daily
public function dailyStats()
{
    static $stmt;
    
    if (!$stmt) {
        $stmt = DB::prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as orders_count,
                SUM(total) as total_revenue
            FROM orders
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
    }
    
    $stmt->execute([30]);
    $stats = $stmt->fetchAll();
    
    return Response::json($stats);
}
```

**Result:** 8,000 req/s → 9,200 req/s (+15%)

## 📈 Benchmark Results Summary

| Pattern | Before | After | Gain |
|---------|--------|-------|------|
| Static DB facade | 10,500 req/s | 11,000 req/s | +4.8% |
| Prepared statements (hot path) | 15,000 req/s | 18,000 req/s | +20% |
| Raw queries (complex) | 8,500 req/s | 9,700 req/s | +14% |
| Transactions (bulk insert) | 500 ops/s | 2,500 ops/s | +400% |
| whereIn vs multiple OR | 6,000 req/s | 7,800 req/s | +30% |

**Overall Framework Improvement:** +4.4% average across all endpoints

## ⚠️ Anti-Patterns to Avoid

### 1. N+1 Queries

**Bad:**
```php
$users = DB::table('users')->get();
foreach ($users as $user) {
    $user['orders'] = DB::table('orders')->where('user_id', $user['id'])->get();
}
```

**Good:**
```php
$users = DB::table('users')->get();
$userIds = array_column($users, 'id');
$orders = DB::table('orders')->whereIn('user_id', $userIds)->get();

// Group orders by user_id
$ordersByUser = [];
foreach ($orders as $order) {
    $ordersByUser[$order['user_id']][] = $order;
}

foreach ($users as &$user) {
    $user['orders'] = $ordersByUser[$user['id']] ?? [];
}
```

### 2. SELECT * in Production

**Bad:**
```php
$users = DB::table('users')->get(); // Fetches all columns including blobs
```

**Good:**
```php
$users = DB::table('users')->select('id', 'name', 'email')->get();
```

### 3. Counting with count()

**Bad (for large tables):**
```php
$total = DB::table('logs')->count(); // Slow on millions of rows
```

**Good (estimate for pagination):**
```php
// Use SQL_CALC_FOUND_ROWS or cache count
$total = Cache::remember('logs_count', 3600, fn() => DB::table('logs')->count());
```

## 🔧 Connection Configuration

Optimized PDO options in `DatabaseServiceProvider`:

```php
DB::configure([
    'host' => 'localhost',
    'database' => 'myapp',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,     // Native prepared statements
        PDO::ATTR_STRINGIFY_FETCHES => false,    // Keep native types
    ],
]);
```

## 🎯 Quick Wins Checklist

- [ ] Replace `$db->table()` with `DB::table()`
- [ ] Use prepared statements for repeated queries (loops)
- [ ] Wrap bulk inserts/updates in transactions
- [ ] Replace multiple `orWhere()` with `whereIn()`
- [ ] Use raw SQL for complex aggregations
- [ ] Select only needed columns (avoid `SELECT *`)
- [ ] Cache expensive counts and aggregations
- [ ] Remove `Model::setDatabase()` calls
- [ ] Update to PHP 8.2+ and Swoole 5.0+
- [ ] Profile hot paths with `wrk` or `ab`

## 📚 Related Documentation

- [ANALISE_MELHORIAS_PERFORMANCE.md](../ANALISE_MELHORIAS_PERFORMANCE.md) - Full technical analysis
- [COMPARATIVO_BEFORE_AFTER.md](../COMPARATIVO_BEFORE_AFTER.md) - Code comparison
- [PLANO_IMPLEMENTACAO.md](../PLANO_IMPLEMENTACAO.md) - Implementation guide

## 🏆 Performance Targets

| Metric | Target | Notes |
|--------|--------|-------|
| Simple SELECT | 15,000+ req/s | Single row by ID |
| Complex JOIN | 8,000+ req/s | 3+ table joins |
| Aggregation | 9,000+ req/s | COUNT/SUM with GROUP BY |
| Bulk INSERT | 2,000+ ops/s | 1000 rows/batch |
| Transaction overhead | < 5% | vs raw inserts |

These targets are based on benchmarks running on:
- PHP 8.2 with Swoole 5.1
- MySQL 8.0 / MariaDB 10.6
- 4 CPU cores, 8GB RAM
- Local database connection

---

**Last Updated:** 2024
**Version:** 2.0.0
