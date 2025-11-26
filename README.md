# Alphavel Database

> High-performance Query Builder + ORM with Laravel-style API

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.4-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

---

## ✨ Features

- 🚀 **17,000+ req/s** - `findOne()` with global statement cache
- 📈 **+625% faster** - `findMany()` batch queries vs sequential
- 🎯 **Laravel-compatible** - Familiar Query Builder syntax
- ⚡ **Connection pooling** - Coroutine-safe
- 💾 **Global statement cache** - Prepare once, execute millions
- � **Read connection singleton** - Zero coroutine lookup overhead
- 🔒 **Transaction safety** - ACID-compliant isolated connections
- 📚 **ORM (optional)** - Eloquent-like models with relationships

## 🏆 Performance Benchmarks

| Method | Req/sec | vs Query Builder | Use Case |
|--------|---------|------------------|----------|
| `DB::findOne()` | ~17,000 | **+161%** | Single record lookup |
| `DB::findMany()` | ~8,700 | **+625%** | Batch IN queries |
| `DB::batchFetch()` | ~10,500 | **+70%** | Different records |
| `DB::table()->get()` | ~6,500 | Baseline | Complex queries |

**👉 [See Performance Guide](PERFORMANCE_GUIDE.md) for detailed optimizations**

## 📦 Installation

```bash
composer require alphavel/database
```

## ⚙️ Configuration

```env
DB_HOST=127.0.0.1
DB_DATABASE=myapp
DB_USERNAME=root
DB_PASSWORD=secret
```

The framework automatically uses optimal settings (no tuning required).

## 🚀 Quick Start

### Hot Path Methods (Maximum Performance)

```php
use Alphavel\Database\DB;

// 1. Single record lookup (17,000+ req/s)
$user = DB::findOne('users', 123);
// SELECT * FROM users WHERE id = ?
// Statement cached globally, zero overhead!

// 2. Batch queries (8,700+ req/s - 625% faster!)
$users = DB::findMany('users', [1, 2, 3, 4, 5]);
// SELECT * FROM users WHERE id IN (1,2,3,4,5)
// Single query instead of 5 queries!

// 3. Multiple different records (10,500+ req/s)
[$user, $product, $order] = DB::batchFetch('entities', [$userId, $productId, $orderId]);
// Reuses same cached statement 3 times
```

### Query Builder (Complex Queries)

```php
// Use when you need filters, joins, pagination
$users = DB::table('users')
    ->where('status', 'active')
    ->where('age', '>', 18)
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();

// Insert
DB::table('users')->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// Transactions
DB::transaction(function() {
    DB::execute('INSERT INTO orders ...');
    DB::execute('INSERT INTO order_items ...');
});
```

### Models (ORM)

```php
use App\Models\User;

// Find
$user = User::find(1);
$users = User::where('active', true)->get();

// Create
$user = User::create([
    'name' => 'Jane Doe',
    'email' => 'jane@example.com',
]);

// Update
$user->name = 'John Smith';
$user->save();

// Relationships
$posts = $user->posts;  // Lazy loading
$users = User::with('posts')->get();  // Eager loading
```

## 📚 Documentation

**Full documentation**: https://github.com/alphavel/documentation

- [Query Builder](https://github.com/alphavel/documentation/blob/master/packages/database/query-builder.md)
- [ORM (Models)](https://github.com/alphavel/documentation/blob/master/packages/database/orm.md)
- [Migrations](https://github.com/alphavel/documentation/blob/master/packages/database/migrations.md)
- [Performance](https://github.com/alphavel/documentation/blob/master/packages/database/performance.md)
- [Best Practices](https://github.com/alphavel/documentation/blob/master/packages/database/best-practices.md)

## 📄 License

MIT License
