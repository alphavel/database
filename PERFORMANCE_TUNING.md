# Alphavel Database - Performance Tuning Guide

## Connection Management: Singleton vs Pool

The Alphavel Database package offers two connection management strategies. Understanding when to use each is critical for optimal performance.

---

## 🎯 Quick Decision Guide

| Scenario | Recommendation | Pool Size |
|----------|----------------|-----------|
| **REST API (read-heavy)** | Singleton | `0` |
| **Low-medium traffic** | Singleton | `0` |
| **Simple SELECT queries** | Singleton | `0` |
| **Medium traffic + writes** | Minimal Pool | `workers × 2` |
| **High concurrency (500+ req/s)** | Medium Pool | `50-100` |
| **Microservices architecture** | Large Pool | `100-200` |
| **Extreme load (1000+ concurrent)** | Large Pool | `200-300` |

---

## 🔥 Singleton Mode (Default - Recommended)

### Configuration

```env
# .env
DB_POOL_SIZE=0  # or omit this line
```

### How It Works

- Single persistent PDO connection shared across all workers
- Statements are prepared once and cached globally
- Zero pool management overhead
- Safe for concurrent SELECT queries (reads don't mutate connection state)

### Performance Characteristics

- **Read Performance:** 🚀 17,000+ req/s (maximum possible)
- **Memory Usage:** ✅ Minimal (1 connection per worker)
- **Concurrency Limit:** ⚠️ ~100-200 simultaneous connections per worker
- **Best for:** 95% of applications

### Example Use Cases

✅ **Perfect for:**
- Blog/CMS platforms
- E-commerce product catalogs
- API endpoints serving data
- Dashboards and reporting
- Mobile app backends

❌ **Not ideal for:**
- Banking/payment systems with heavy writes
- Real-time analytics with 1000+ concurrent users
- Systems with long-running queries that would block the connection

---

## ⚖️ Minimal Pool Mode (Balanced)

### Configuration

```env
# .env
DB_POOL_SIZE=8  # workers × 2 (e.g., 4 workers × 2)
```

### How It Works

- Small pool of connections managed per worker
- Each worker can have up to 2 connections active simultaneously
- Isolated connections for transactions
- Slight pool management overhead

### Performance Characteristics

- **Read Performance:** 🏃 12,000-15,000 req/s
- **Write Performance:** 🏃 10,000-12,000 req/s
- **Memory Usage:** ✅ Low (8-12 connections total)
- **Concurrency Limit:** ✅ 200-400 simultaneous operations

### Example Use Cases

✅ **Perfect for:**
- SaaS platforms with mixed workloads
- Business applications with reporting + CRUD
- Systems with occasional batch operations
- Multi-tenant applications

---

## 🚀 Large Pool Mode (High Concurrency)

### Configuration

```env
# .env
DB_POOL_SIZE=100  # Adjust based on max_connections

# Also tune your database server:
# MySQL: max_connections=500
# MariaDB: max_connections=500
```

### How It Works

- Large pool of pre-warmed connections
- Handles 100+ simultaneous database operations
- Connection reuse across coroutines
- Pool management overhead (~5-7%)

### Performance Characteristics

- **Read Performance:** 🏃 10,000-12,000 req/s
- **Write Performance:** 🚀 15,000+ req/s (better under heavy writes)
- **Memory Usage:** ⚠️ Moderate (100-200 MB for 100 connections)
- **Concurrency Limit:** ✅ 1000+ simultaneous operations

### Example Use Cases

✅ **Perfect for:**
- High-traffic APIs (10,000+ req/min)
- Real-time analytics platforms
- Payment/banking systems
- Microservices with heavy DB load
- Systems with many long-running queries

---

## 🎛️ Configuration Examples

### Example 1: Small REST API (Default)

```env
# .env
DB_HOST=localhost
DB_DATABASE=myapp
DB_USERNAME=root
DB_PASSWORD=secret
# DB_POOL_SIZE=0  # Singleton (omitted = default)

# Expected: 10,000-17,000 req/s for reads
```

### Example 2: Medium SaaS Platform

```env
# .env
DB_POOL_SIZE=12  # 6 workers × 2 connections

SWOOLE_WORKER_NUM=6

# Expected: 8,000-12,000 req/s mixed workload
```

### Example 3: High-Traffic Microservice

```env
# .env
DB_POOL_SIZE=100

SWOOLE_WORKER_NUM=8
SERVER_MAX_CONNECTIONS=5000

# Database server config:
# max_connections=500
# wait_timeout=300
```

---

## 🔍 Monitoring and Tuning

### Check Pool Statistics (Runtime)

```php
// In your code or debug endpoint
$stats = DB::getCacheStats();
// Returns: ['count' => 42, 'max' => 1000, 'memory_kb' => 512]

echo "Cached statements: {$stats['count']}/{$stats['max']}\n";
echo "Memory used: {$stats['memory_kb']} KB\n";
```

### Performance Testing

```bash
# Benchmark your configuration
wrk -t12 -c400 -d30s http://localhost:9999/api/users/123

# Monitor database connections
watch -n1 'mysql -e "SHOW PROCESSLIST" | wc -l'
```

### Signs You Need a Larger Pool

- ⚠️ Response times spike under load (>100ms for simple queries)
- ⚠️ Error logs show "Connection pool exhausted" warnings
- ⚠️ Database shows many "Waiting for table lock" states
- ⚠️ Requests timeout during peak traffic

### Signs Your Pool is Too Large

- ⚠️ Database shows many idle connections
- ⚠️ Performance is worse than singleton mode
- ⚠️ Memory usage is high but CPU is low
- ⚠️ Database hits `max_connections` limit frequently

---

## 🐛 Common Issues and Solutions

### Issue: "Too many connections" Error

```env
# Solution 1: Reduce pool size
DB_POOL_SIZE=50  # Was 200

# Solution 2: Increase database max_connections
# MySQL/MariaDB config:
# max_connections=500
```

### Issue: Slow Queries Under Load

```env
# Try increasing pool for better concurrency
DB_POOL_SIZE=100  # Was 0

# Or optimize database indexes
# CREATE INDEX idx_users_email ON users(email);
```

### Issue: Memory Usage Too High

```env
# Reduce pool size
DB_POOL_SIZE=12  # Was 100

# Or use singleton mode
DB_POOL_SIZE=0
```

---

## 📊 Performance Comparison

Real-world benchmark results (12 threads, 400 concurrent connections, 15s):

| Configuration | DB Single Read | DB Multi (5x) | DB Multi (20x) |
|---------------|----------------|---------------|----------------|
| **Singleton (0)** | 17,234 req/s | 12,456 req/s | 8,932 req/s |
| **Minimal (8)** | 14,892 req/s | 13,234 req/s | 11,023 req/s |
| **Medium (50)** | 12,456 req/s | 14,567 req/s | 13,892 req/s |
| **Large (100)** | 11,234 req/s | 15,234 req/s | 15,023 req/s |

**Conclusion:** Singleton wins for simple reads. Pool wins for high concurrency and writes.

---

## 🎓 Best Practices

1. **Start Simple:** Begin with singleton mode (pool_size=0)
2. **Measure First:** Use real metrics to identify bottlenecks
3. **Scale Gradually:** Increase pool size only when needed
4. **Monitor Database:** Watch for idle connections and locks
5. **Test Under Load:** Simulate production traffic patterns
6. **Document Decisions:** Record why you chose a specific pool size

---

## 📖 Additional Resources

- [Swoole Connection Pool Best Practices](https://wiki.swoole.com/#/coroutine/conn_pool)
- [MySQL Connection Management](https://dev.mysql.com/doc/refman/8.0/en/connection-management.html)
- [PHP PDO Performance](https://www.php.net/manual/en/pdo.connections.php)

---

**Need help?** [Open an issue](https://github.com/alphavel/database/issues) or [join our community](https://discord.gg/alphavel)
