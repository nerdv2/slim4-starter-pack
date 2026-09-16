# Database

The application talks to MySQL, MariaDB or SQLite through
[oeltimacreation/php-simplequery](https://github.com/oeltimacreation/php-simplequery) 0.6
(`^0.6`, exact version in `composer.lock`). There is no ORM; models own their SQL.

## Connections

`src/App/Database.php` registers two lazy Pimple services:

| Service | Environment variables | Used for |
|---------|-----------------------|----------|
| `db` | `DB_DRIVER`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | Writes and default reads. |
| `db_read` | `DB_DRIVER`, `DB_HOST_READ`, `DB_PORT_READ`, `DB_NAME_READ`, `DB_USER_READ`, `DB_PASS_READ` | Read-only endpoints; each value falls back to its primary counterpart. |

- `DB_DRIVER` selects the dialect: `mysql` (default), `mariadb` or `sqlite`. MySQL and MariaDB use
  PDO's `mysql` transport with `utf8mb4`; SQLite takes the database path as `DB_NAME` (relative
  paths resolve against the project root, so the built-in server’s `public/` working directory does
  not matter).
- MySQL-family connections use `PDO::ATTR_TIMEOUT = 5` and a `ConnectionOptions` label
  (`primary` / `read-replica`) for diagnostics.
- `db_read` is always registered, so `$container->get('db_read')` is safe locally even without a
  replica.

## Schema

| Table | Purpose |
|-------|---------|
| `user` | Accounts (`name`, `email`, bcrypt `password_hash`, `type` = `admin`/`staff`, `last_login_at`) with soft deletes. |
| `refresh_token` | Hashed, rotating refresh tokens (`user_id`, `family_id`, `token_hash`, `expires_at`, `revoked_at`, request metadata). |
| `customer` | Customers (`name`, `email`, `phone`, `company`, `status`, `address`, `notes`, `avatar_path`, timestamps) with soft deletes. |
| `background_job` | Durable queue state managed by SimpleQueue (see [Background Jobs](background-jobs.md)). |

`refresh_token` and `customer` only ever expose hashed/relative values: the raw refresh token lives
in the HttpOnly cookie and avatar files under `public/uploads/avatars/`.

Controllers receive the connection through `BaseController::db()` / `dbRead()`:

```php
final class Customer extends BaseController
{
    private CustomerModel $customerModel;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->customerModel = new CustomerModel($this->db()); // or $this->dbRead() for read-only endpoints
    }
}
```

## BaseModel

All models extend `App\Model\BaseModel` (`src/Model/BaseModel.php`), which implements
`App\Interfaces\ModelInterface` and owns the connection.

| Method | Description |
|--------|-------------|
| `db()` | Returns the injected SimpleQuery `Connection`. |
| `pdo()` | Returns the underlying `PDO`. |
| `transaction(\Closure $callback)` | Runs the callback in a managed transaction (nested calls use savepoints). |
| `now()` | Current datetime as `DateFormat::DATETIME`. |
| `existsById($table, $id, $idColumn = 'id', $excludeDeleted = true)` | Existence check, optionally ignoring soft-deleted rows. |
| `softDelete($table, $idColumn, $id)` | Sets `deleted_at` on one row. |
| `softDeleteBulk($table, $idColumn, $ids)` | Sets `deleted_at` on many rows. |
| `escapeLikeKeyword($keyword)` | Escapes `%`, `_` and `\` for LIKE searches. |
| `applyKeywordSearch($query, $column, $keywords)` | Adds a case-insensitive LIKE predicate for one or many columns. |
| `applyPagination($query, $page, $limit)` | Applies `Pagination::apply`; both values must be null (no pagination) or provided together. |
| `columnExpression($function, $column)` | Builds an allowlisted function expression (`DATE`, `LOWER`, `MONTH`, `YEAR`) for a validated column identifier. |

Constructor work in a model calls `parent::__construct($database)` first; otherwise the promoted
constructor is enough.

## Query Patterns

Always get a fresh builder inside the method:

```php
// List with keyword search and pagination
$query = $this->db()->table('customer')
    ->select('customer.id', 'customer.name');

$this->applyKeywordSearch($query, 'customer.name', $keywords);
$this->applyPagination($query, $page, $limit);

return $query->orderBy('customer.id', 'asc')->get();
```

```php
// Single row (nullable)
$row = $this->db()->table('customer')->where('id', $id)->first();

// Count for pagination metadata
$total = $this->db()->table('customer')
    ->whereNull('deleted_at')
    ->count();

// Insert
$id = $this->db()->table('customer')->insertGetId(['name' => $name]);

// Update / delete (return affected rows)
$affected = $this->db()->table('customer')->where('id', $id)->update(['name' => $name]);
$affected = $this->db()->table('customer')->where('id', $id)->delete();
```

Conventions:

- **Soft deletes**: filter with `whereNull('deleted_at')`.
- **Timestamps**: set `created_at`/`updated_at` explicitly with `$this->now()`; there is no ORM
  magic.
- **Raw SQL**: `raw()`/`query()` bindings must be ordered lists; dynamic identifiers have to be
  validated (see `columnExpression()`).
- **Keyword search** escapes `%`/`_` and compiles an explicit `ESCAPE '\'` clause so MySQL and
  SQLite behave identically.
- **Batch writes**: `insertMany()` for uniform rows; wrap multi-table writes in
  `$this->transaction()`.
- **Reads**: use the `db_read` service for read-only endpoints when a replica exists.

## Pagination

`App\Helper\Pagination` holds the shared rules:

```php
[$page, $limit] = Pagination::sanitize($get['page'] ?? null, $get['limit'] ?? null); // [1, 20]
$totalData = $this->model->countList($keywords);
$data = $this->model->list($keywords, $page, $limit);
$totalPage = Pagination::totalPages($totalData, $limit);
```

`page` defaults to 1 and is clamped to a minimum of 1; `limit` defaults to 20 and is clamped to
`[1, 100]`. Inside a model use `$this->applyPagination($query, $page, $limit)`; passing only one of
the two values throws `InvalidArgumentException`. Always order by a unique column (`id`) so pages
are stable.

Every paginated list has a `count*` sibling (for example `CustomerModel::countGet()`) that runs
`COUNT(*)` through the same filters instead of counting a full result set in PHP.

## Transactions

```php
$this->transaction(function (Connection $connection) use ($id, $data): void {
    $connection->table('customer')->where('id', $id)->update($data);
    $connection->table('audit_log')->insert(['customer_id' => $id, 'created_at' => date(DateFormat::DATETIME)]);
});
```

SimpleQuery commits on success, rolls back on any `Throwable` and uses savepoints for nested calls.
Do not call raw PDO commit/rollback inside the callback.

## Migrations

Schema changes use [Phinx](https://phinx.org/):

```bash
composer run migrate            # apply pending migrations
composer run migrate:rollback   # roll back the last migration
composer run seed               # run seeders
```

- Configuration: `phinx.php` reads process environment variables first, then `.env` next to the
  file, then defaults. Paths are absolute, so migrations run from any working directory.
- Migrations live in `db/migrations/` and seeders in `db/seeds/`.
- `phinx-testing.php` provides the SQLite configuration (`storage/test_database.sqlite`) used by
  `Tests\TestCase`, which migrates once per test run.

## Related Docs

- [Architecture](architecture.md)
- [API Conventions](api-conventions.md)
- [Development](development.md)
