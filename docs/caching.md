# Caching

Optional Redis caching through `App\Helper\CacheRedis`. Redis is **not required**: when
`REDIS_SERVER_HOST`/`REDIS_SERVER_PORT` are unset or Redis is unreachable, the application serves
every request from the database.

## Design

- **Fail-open.** A connection or command error disables the cache for the rest of that request
  only; the next request retries with a fresh instance. Cache reads return `null` and writes are
  skipped, so callers keep working.
- **Namespaced keys.** Every namespace has a generation counter (`<namespace>:version`). A key is
  `{prefix}{namespace}:g{generation}:{entity}[:{discriminator}]`. Invalidation is a single `INCR`
  (`bump()`), so the whole namespace is orphaned in O(1) instead of a `SCAN`/`DEL` sweep; old
  entries expire on their own TTL.
- **Bounded payloads.** Values whose JSON exceeds `CacheRedis::MAX_PAYLOAD_BYTES` (256 KiB) are
  skipped so one hot key cannot dominate memory.
- **Bounded lifetime.** Every write requires a positive TTL, jittered by ±10% so keys written at
  the same time do not expire in one synchronized wave against the database.
- **Key prefix.** `REDIS_SERVER_PREFIX` (default `v1:`) separates environments sharing a Redis
  instance; bump it when a cached payload changes shape.

## Configuration

| Variable | Default | Purpose |
|----------|---------|---------|
| `REDIS_SERVER_HOST` | empty | Redis host; caching is disabled when empty. |
| `REDIS_SERVER_PORT` | `6379` | Redis port; caching is disabled when empty. |
| `REDIS_SERVER_PASSWORD` | empty | Optional `AUTH` password. |
| `REDIS_SERVER_DATABASE` | `0` | Logical database (0–15; out-of-range falls back to 0). |
| `REDIS_SERVER_PREFIX` | `v1:` | Key prefix; one per environment when Redis is shared. |

Connect and read/write timeouts are 1 second.

## Usage

Resolve `cacheRedis` from the container and cache array payloads; the reference implementation is
`CustomerService::list()`:

```php
$cacheKey = $this->cache->namespaced('customer', 'list', sha1($keywords) . ':' . $page . ':' . $limit);

$result = $this->cache->rememberJson(
    $cacheKey,
    300,
    fn (): array => $this->loadList($keywords, $page, $limit),
    cacheEmpty: true
);
```

- `rememberJson()` returns the cached array or runs the producer once and caches the result (empty
  results only when `cacheEmpty` is true).
- `getJson()` / `setJson()` / `get()` / `set()` / `delete()` are available for lower-level use.
- `namespaced()` resolves the namespace generation (one Redis `GET`, memoized per request) and
  sanitizes the discriminator.

Writes bump the namespace so the next read is fresh:

```php
$this->customerModel->create($name);
$this->cache->bump('customer');
```

## Invalidation Map

| Namespace | Cached data | TTL | Invalidated by |
|-----------|-------------|-----|----------------|
| `customer` | Paginated/keyword customer lists (`list:{sha1(keywords)}:{page}:{limit}`) | 300 s | `CustomerService::create()`, `rename()`, `delete()` |

Add a row here whenever a new cached endpoint is introduced, together with the code path that
bumps its namespace.

## Operations

- **Not configured or down:** the API keeps working; responses are simply slower. Check
  `CacheRedis::isEnabled()` or logs if you expect cache hits.
- **Shared Redis:** give each environment a distinct `REDIS_SERVER_PREFIX` so keys cannot collide.
- **Manual inspection:** keys look like `v1:customer:g3:list:<sha1>:1:20`. Prefer `bump()`
  semantics in code; use `redis-cli --scan` only for break-glass cleanup.
- **Deploys:** keys survive deploys; bump the prefix when a payload shape changes in a way the
  namespace generation cannot cover.

## Limitations

- Writes made outside the application (manual SQL, another service) leave cached lists stale until
  the TTL expires or a write through the API bumps the namespace.
- There is no cache-fill locking or stale-while-revalidate; concurrent misses simply recompute.
- Queue Redis delivery is not wired yet: the queue uses database polling. A Redis queue driver
  needs its own blocking-friendly client (`read_write_timeout = -1`) separate from this cache.

## Testing

Cache tests run against a real Redis and are skipped when it is not reachable:

```bash
REDIS_SERVER_HOST=127.0.0.1 REDIS_SERVER_PORT=6379 composer run test
```

CI starts a Redis service for the test step; everywhere else the suite covers the fail-open path.

## Related Docs

- [Architecture](architecture.md)
- [Database](database.md)
- [Development](development.md)
- [Deployment](deployment.md)
