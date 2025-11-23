# 🚀 Alphavel Database - Guia Laravel-Style

Alphavel Database foi projetado para ser **extremamente familiar** para desenvolvedores Laravel, com a mesma API fluente e intuitiva que você já conhece.

---

## 📋 Índice

- [Queries Básicas](#-queries-básicas)
- [Query Builder](#-query-builder)
- [Batch Queries (Novo! 🆕)](#-batch-queries-novo-)
- [Transações](#-transações)
- [Conexões Persistentes](#-conexões-persistentes)
- [Comparação com Laravel](#-comparação-com-laravel)

---

## 🔍 Queries Básicas

### SELECT Queries

```php
use Alphavel\Database\DB;

// Select all - Laravel style
$users = DB::query('SELECT * FROM users');

// Select with bindings
$user = DB::queryOne(
    'SELECT * FROM users WHERE email = ?',
    ['john@example.com']
);

// Named parameters (PDO style)
$posts = DB::query(
    'SELECT * FROM posts WHERE author_id = :author AND status = :status',
    ['author' => 1, 'status' => 'published']
);
```

### INSERT / UPDATE / DELETE

```php
// Insert
$affected = DB::execute(
    'INSERT INTO users (name, email, password) VALUES (?, ?, ?)',
    ['John Doe', 'john@example.com', password_hash('secret', PASSWORD_DEFAULT)]
);

// Update
$affected = DB::execute(
    'UPDATE users SET last_login = NOW() WHERE id = ?',
    [42]
);

// Delete
$affected = DB::execute(
    'DELETE FROM users WHERE id = ?',
    [42]
);
```

---

## 🏗️ Query Builder

API 100% compatível com Laravel!

### Select

```php
// Simple select
$users = DB::table('users')->get();

// Select with where
$active = DB::table('users')
    ->where('status', 'active')
    ->get();

// Select one record
$user = DB::table('users')
    ->where('id', 42)
    ->first();

// Select specific columns
$names = DB::table('users')
    ->select(['id', 'name', 'email'])
    ->get();
```

### Where Clauses

```php
// Simple where
$posts = DB::table('posts')
    ->where('published', true)
    ->get();

// Multiple where (AND)
$results = DB::table('posts')
    ->where('status', 'published')
    ->where('author_id', 1)
    ->get();

// Where with operator
$adults = DB::table('users')
    ->where('age', '>=', 18)
    ->get();

// Where IN (Laravel style!)
$users = DB::table('users')
    ->whereIn('id', [1, 2, 3, 4, 5])
    ->get();

// Where BETWEEN
$recent = DB::table('posts')
    ->whereBetween('created_at', ['2024-01-01', '2024-12-31'])
    ->get();
```

### Joins

```php
// Inner join
$results = DB::table('users')
    ->join('posts', 'users.id', '=', 'posts.user_id')
    ->select(['users.name', 'posts.title'])
    ->get();

// Left join
$results = DB::table('users')
    ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
    ->get();
```

### Order By & Limit

```php
// Order by
$users = DB::table('users')
    ->orderBy('created_at', 'DESC')
    ->get();

// Limit
$latest = DB::table('posts')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();

// Offset
$paginated = DB::table('users')
    ->limit(10)
    ->offset(20)
    ->get();
```

### Group By & Having

```php
// Group by
$counts = DB::table('posts')
    ->select(['user_id', 'COUNT(*) as total'])
    ->groupBy('user_id')
    ->get();

// Having
$active = DB::table('posts')
    ->select(['user_id', 'COUNT(*) as total'])
    ->groupBy('user_id')
    ->having('total', '>', 5)
    ->get();
```

---

## 📦 Batch Queries (Novo! 🆕)

### Por que usar Batch Queries?

```php
// ❌ LENTO: N queries (312 req/s)
$worlds = [];
foreach ($ids as $id) {
    $world = DB::table('World')->where('id', $id)->first();
    $worlds[] = $world;
}

// ✅ RÁPIDO: 1 query (2,269 req/s) - 627% mais rápido! 🔥
$worlds = DB::findMany('World', $ids);
```

### DB::findOne() - Maximum Performance

O método mais rápido para buscar um único registro:

```php
// Hot path optimization (benchmark-ready)
$world = DB::findOne('World', mt_rand(1, 10000));
// SELECT * FROM World WHERE id = ?
// Gera SQL consistente = cache hit perfeito!

// Find by custom column
$user = DB::findOne('users', 'john@example.com', 'email');
// SELECT * FROM users WHERE email = ?

// With null check
$post = DB::findOne('posts', 42);
if ($post === null) {
    return response()->json(['error' => 'Not found'], 404);
}
```

**Performance**: +49% vs Query Builder (6,500 → 9,712 req/s) 🔥

### DB::findMany() - Batch Queries

Para buscar múltiplos registros:

```php
// Find multiple users by ID
$users = DB::findMany('users', [1, 2, 3, 4, 5]);
// SELECT * FROM users WHERE id IN (1,2,3,4,5)

// Find by custom column
$posts = DB::findMany('posts', ['published', 'draft'], 'status');
// SELECT * FROM posts WHERE status IN ('published','draft')

// With empty array (returns [])
$empty = DB::findMany('users', []);  // No query executed
```

**Performance**: +627% vs sequential queries (312 → 2,269 req/s) 🔥

### DB::queryIn() - Para Queries Customizadas

Quando você precisa de mais controle:

```php
// Simple IN query
$results = DB::queryIn(
    'SELECT * FROM World WHERE id IN (?)',
    [1, 2, 3, 4, 5]
);

// With JOIN
$data = DB::queryIn(
    'SELECT u.name, p.title 
     FROM users u 
     JOIN posts p ON u.id = p.user_id 
     WHERE u.id IN (?)',
    [10, 20, 30]
);

// Complex conditions
$active = DB::query(
    'SELECT * FROM users 
     WHERE id IN (?) AND status = ? AND created_at > ?',
    array_merge($ids, ['active', '2024-01-01'])
);
```

### QueryBuilder::whereIn() - Mais Flexível

Para usar com o Query Builder:

```php
// Basic whereIn
$users = DB::table('users')
    ->whereIn('id', [1, 2, 3, 4, 5])
    ->get();

// With additional filters
$activeUsers = DB::table('users')
    ->whereIn('id', $ids)
    ->where('status', 'active')
    ->orderBy('created_at', 'DESC')
    ->get();

// Multiple whereIn
$results = DB::table('posts')
    ->whereIn('author_id', [1, 2, 3])
    ->whereIn('status', ['published', 'featured'])
    ->get();

// With joins
$data = DB::table('users')
    ->join('posts', 'users.id', '=', 'posts.user_id')
    ->whereIn('users.id', $userIds)
    ->select(['users.name', 'posts.title'])
    ->get();
```

---

## 🔄 Transações

Laravel-style transaction handling:

```php
// Transaction with closure (recommended)
DB::transaction(function() {
    DB::execute('UPDATE accounts SET balance = balance - 100 WHERE id = ?', [1]);
    DB::execute('UPDATE accounts SET balance = balance + 100 WHERE id = ?', [2]);
});

// Manual transaction control
DB::beginTransaction();

try {
    DB::execute('INSERT INTO orders (user_id, total) VALUES (?, ?)', [1, 99.99]);
    DB::execute('UPDATE products SET stock = stock - 1 WHERE id = ?', [42]);
    
    DB::commit();
} catch (\Exception $e) {
    DB::rollback();
    throw $e;
}
```

---

## ⚡ Conexões Persistentes

Ativado por padrão para máxima performance!

```php
// config/database.php
return [
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'localhost'),
            'database' => env('DB_DATABASE', 'alphavel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            
            // ⚡ Persistent connections - ENABLED BY DEFAULT
            'persistent' => true,  // +1,769% performance! 🔥
        ]
    ],
];
```

### Performance Boost

```
Sem persistent: 350 req/s
Com persistent: 6,541 req/s
Ganho: +1,769% 🚀
```

---

## 🆚 Comparação com Laravel

| Feature | Laravel | Alphavel | Compatible? |
|---------|---------|----------|-------------|
| `DB::query()` | ✅ | ✅ | ✅ 100% |
| `DB::table()` | ✅ | ✅ | ✅ 100% |
| `where()` | ✅ | ✅ | ✅ 100% |
| `whereIn()` | ✅ | ✅ | ✅ 100% |
| `join()` | ✅ | ✅ | ✅ 100% |
| `orderBy()` | ✅ | ✅ | ✅ 100% |
| `groupBy()` | ✅ | ✅ | ✅ 100% |
| `transaction()` | ✅ | ✅ | ✅ 100% |
| **`findMany()`** | ❌ | ✅ 🆕 | - |
| **`queryIn()`** | ❌ | ✅ 🆕 | - |
| **Persistent Connections** | ❌ Manual | ✅ Default | - |

### Exemplos Lado a Lado

#### Laravel
```php
// Laravel
$users = User::whereIn('id', [1, 2, 3])->get();

// or
$users = DB::table('users')
    ->whereIn('id', [1, 2, 3])
    ->get();
```

#### Alphavel (3 formas)
```php
// 1. findMany (mais simples) - RECOMENDADO
$users = DB::findMany('users', [1, 2, 3]);

// 2. Query Builder (Laravel-style)
$users = DB::table('users')
    ->whereIn('id', [1, 2, 3])
    ->get();

// 3. queryIn (para queries complexas)
$users = DB::queryIn(
    'SELECT * FROM users WHERE id IN (?)',
    [1, 2, 3]
);
```

---

## 🎯 Best Practices

### 1. Use Batch Queries para Múltiplos IDs

```php
// ❌ Evite N queries
foreach ($ids as $id) {
    $results[] = DB::table('users')->where('id', $id)->first();
}

// ✅ Use batch query
$results = DB::findMany('users', $ids);
```

### 2. Use Prepared Statements (Automático)

```php
// ✅ BOM: Prepared statements (automático)
$user = DB::queryOne('SELECT * FROM users WHERE email = ?', [$email]);

// ❌ RUIM: String concatenation (SQL injection!)
$user = DB::query("SELECT * FROM users WHERE email = '{$email}'");
```

### 3. Use Transações para Operações Críticas

```php
// ✅ BOM: Transaction garante atomicidade
DB::transaction(function() {
    DB::execute('UPDATE accounts SET balance = balance - 100 WHERE id = ?', [1]);
    DB::execute('UPDATE accounts SET balance = balance + 100 WHERE id = ?', [2]);
});
```

### 4. Configure Workers Corretamente

```env
# .env
SWOOLE_WORKER_NUM=4    # = CPU cores
DB_POOL_MAX=20         # = WORKER_NUM * 5
DB_POOL_MIN=8          # = WORKER_NUM * 2
DB_PERSISTENT=true     # Já é padrão
```

---

## 📊 Performance Tips

### Single Query Optimization
```php
// Query Builder: 350 req/s (baseline)
// DB::findOne(): 6,500 req/s (+1,757%)
// Com Global Cache: 9,712 req/s (+2,674%)

$user = DB::findOne('users', 42);  // Hot path otimizado!
```

### Batch Query Optimization
```php
// 20 queries sequenciais: 312 req/s
// 1 query com IN: 2,269 req/s (+627%)

$users = DB::findMany('users', $ids);  // 627% mais rápido!
```

### Performance Comparison

| Método | Request/s | vs Baseline | Use Case |
|--------|-----------|-------------|----------|
| `table()->where()->first()` | 350 | baseline | Queries complexas |
| `findOne('table', $id)` | 6,500 | +1,757% | Hot paths, benchmarks |
| `findMany(batch)` | 2,269 | +548% | Múltiplos registros |
| With Global Cache | 9,712 | +2,674% | Produção 🚀 |

---

## 🔗 Links Úteis

- **Performance Guide**: [PERFORMANCE_OPTIMIZATIONS.md](PERFORMANCE_OPTIMIZATIONS.md)
- **Configuration**: [.env.performance](.env.performance)
- **Changelog**: [CHANGELOG.md](CHANGELOG.md)

---

## 💡 Dicas Rápidas

```php
// ✅ Laravel-style fluent API
DB::table('users')
    ->where('status', 'active')
    ->whereIn('role', ['admin', 'moderator'])
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();

// ✅ Hot path optimization (novo!)
DB::findOne('World', mt_rand(1, 10000));  // +1,757% 🔥

// ✅ Batch queries (novo!)
DB::findMany('posts', [1, 2, 3, 4, 5]);  // +627% 🔥

// ✅ Transações seguras
DB::transaction(fn() => /* ... */);

// ✅ Conexões persistentes (automático)
// Nada a fazer, já está ativo! 🎉
```

**Alphavel Database: Laravel-like API + Swoole Performance = ❤️**
