# Alphavel Database

> High-performance Query Builder + ORM with Laravel-style API

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.4-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

---

## ✨ Features

- 🚀 **6,700+ req/s** - Optimized for Swoole
- 🎯 **Laravel-compatible** - Familiar Query Builder syntax
- ⚡ **Connection pooling** - Coroutine-safe
- 💾 **Global statement cache** - Prepare once, execute millions
- 📦 **Batch queries** - `findMany()` helper (+627% performance)
- 🔒 **Transaction safety** - ACID-compliant isolated connections
- 📚 **ORM (optional)** - Eloquent-like models with relationships

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

### Query Builder

```php
use Alphavel\Database\DB;

// Select
$users = DB::table('users')
    ->where('status', 'active')
    ->orderBy('created_at', 'DESC')
    ->get();

// Batch queries (627% faster!)
$worlds = DB::findMany('World', [1, 2, 3, 4, 5]);

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
