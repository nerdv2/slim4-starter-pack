# Architecture

How the Slim 4 Starter Pack is structured and how a request flows through it.

## Technology Stack

| Component | Technology |
|-----------|------------|
| Framework | Slim 4 (`slim/slim`) |
| Language | PHP 8.3+ (`declare(strict_types=1)` everywhere) |
| Database | MySQL / MariaDB / SQLite through `oeltimacreation/php-simplequery` 0.6 (no ORM) |
| DI container | Pimple (PSR-11) |
| Templates | Twig 3 (`slim/twig-view`) |
| Cache | Optional Redis (`CacheRedis`) + parsed `.env` and compiled Twig templates (filesystem) |
| Authentication | JWT (`lcobucci/jwt` 5) |
| API docs | OpenAPI via `zircote/swagger-php` 6 + bundled Swagger UI |
| Migrations | Phinx |
| Background jobs | `oeltimacreation/php-simplequeue` (database driver) |
| Logging | Monolog (`storage/log/error.log`) |
| Error monitoring | Sentry (optional, `SENTRY_DSN`) |
| Observability | Health endpoints, `X-Request-ID` request tracing |
| Uploads | AWS S3 / local filesystem helper |

## Directory Layout

```text
public/                  Web root (index.php, openapi.*, Swagger UI assets)
src/
├── App/                 Bootstrap and configuration
│   ├── App.php          Wires the application together
│   ├── Container.php    Pimple container + Slim AppFactory
│   ├── Cors.php         Config-driven CORS middleware
│   ├── Database.php     `db` (primary) and `db_read` (replica) connections
│   ├── DotEnv.php       Loads .env (cached parse) and sets the timezone
│   ├── ErrorHandler.php JSON error handler for uncaught exceptions
│   ├── Logging.php      Container logger (storage/log/error.log)
│   ├── Middlewares.php  Routing, BasePath, body parser, error, request id, Twig
│   ├── NotFound.php     Catch-all 404 route
│   ├── Routes.php       Route definitions
│   ├── Sentry.php       Optional Sentry initialization from SENTRY_DSN
│   └── Services.php     Container registration for models and services
├── Constants/           HttpStatus, DateFormat, OpenApiTags
├── Controller/          HTTP handlers (BaseController, Hello, Customer, Health, OpenApi)
├── DTO/                 Request payloads (fromRequest/validate/isValid)
├── Exceptions/          Typed application exceptions
├── Helper/              Stateless utilities (JsonResponse, Pagination, JwtHelper, ...)
├── Interfaces/          ModelInterface
├── Jobs/                Background job handlers (ExampleJob)
├── Middleware/          AuthenticationMiddleware, AuthorizationMiddleware
├── Model/               Data access (BaseModel, CustomerModel, HelloModel)
├── Service/             Business rules (CustomerService)
└── View/                Twig templates (Swagger UI)
bin/background-worker    Queue worker
bin/dev-server           Webserver + worker development runner
bin/generate-token       Development token generator
db/migrations/           Phinx migrations
db/seeds/                Phinx seeders
storage/cache/           Env parse cache and compiled Twig templates
storage/log/             Application error log
```

## Bootstrap Sequence

`public/index.php` loads `src/App/App.php`, which runs in order:

1. Composer autoload.
2. `DotEnv.php` — loads `.env` when readable, caches the parse in
   `storage/cache/env.cache.json` (mtime + size invalidation, atomic write) and applies
   `DEFAULT_TIMEZONE` (UTC fallback).
3. `Sentry.php` — initializes error reporting when `SENTRY_DSN` is set (no request bodies, query
   strings stripped, sensitive headers sanitized).
4. `Container.php` — creates the Slim app with a Pimple PSR-11 container.
5. `Logging.php` — registers the Monolog `logger` channel (`storage/log/error.log`).
6. `ErrorHandler.php` — builds the custom error handler.
7. `Middlewares.php` — routing, optional BasePath (`SLIM_BASH_PATH`), body parsing, error handling,
   request id and Twig (compiled templates cached in `storage/cache/twig`).
8. `Cors.php` — registered when `CORS_ENABLED` is true (default: development/testing or
   `localhost`).
9. `Database.php` — registers the `db` and `db_read` SimpleQuery connections.
10. `Services.php` — registers models, services, the cache (`cacheRedis`) and the queue
    infrastructure (`jobStorage`, `queueManager`, `jobRegistry`, `jobDispatcher`) in the container.
11. `Routes.php` — registers every route.
12. `NotFound.php` — catch-all route that throws `HttpNotFoundException`.

Finally `public/index.php` calls `$app->run()`.

## Request Lifecycle

```text
Client
  │
  ▼
public/index.php
  │
  ▼
Slim App (src/App/App.php)
  │
  ├─ CORS middleware          (outermost, optional: Access-Control-* headers, OPTIONS replies)
  ├─ Twig middleware          (renderer for the Swagger UI page)
  ├─ Request ID middleware    (X-Request-ID attribute and response header)
  ├─ Error middleware         (JSON problem+json errors)
  ├─ Body parsing middleware  (getParsedBody() for form/JSON bodies)
  ├─ BasePath middleware      (only when SLIM_BASH_PATH is set)
  └─ Routing middleware
        │
        ▼
   Route middleware  ──►  Controller  ──►  Model  ──►  MySQL/MariaDB/SQLite
   (auth, authorization)      │
                              ▼
                        JsonResponse / TwigResponse
```

Slim middleware executes in reverse registration order. Route middleware follows the same rule:
the **last added runs first**, which is why protected routes add `AuthorizationMiddleware` before
`AuthenticationMiddleware`.

## Layers

### Routes

`src/App/Routes.php` holds flat, named route registrations. Protected routes declare their
middleware inline:

```php
$app->post('/customer/add', 'App\Controller\Customer:add')
    ->setName('api.customer.add')
    ->add(new AuthorizationMiddleware(['admin']))
    ->add(new AuthenticationMiddleware());
```

### Controllers

Controllers extend `App\Controller\BaseController`, which provides the container, typed
`db()`/`dbRead()` accessors and the authenticated `user(Request)`.

```php
final class Example extends BaseController
{
    private ExampleModel $exampleModel;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->exampleModel = new ExampleModel($this->db());
    }

    public function list(Request $request, Response $response): Response
    {
        $get = $request->getQueryParams();
        [$page, $limit] = Pagination::sanitize($get['page'] ?? null, $get['limit'] ?? null);

        $totalData = $this->exampleModel->countList($get['keywords'] ?? '');
        $data = $this->exampleModel->list($get['keywords'] ?? '', $page, $limit);

        return JsonResponse::success($response, $data, JsonResponse::DEFAULT_SUCCESS_MESSAGE, [
            'total_page' => Pagination::totalPages($totalData, $limit),
            'total_data' => $totalData,
        ]);
    }
}
```

Controllers own HTTP concerns: parsing and validating input, choosing the response, calling models.
They are annotated with `#[OA\...]` attributes for the generated specification.

### DTOs

Request payloads live in `src/DTO/Request/`. Each DTO is a `final readonly` class with a static
`fromRequest()` factory, field-keyed `validate()` errors and an `isValid()` check:

```php
final readonly class CustomerRequest
{
    public function __construct(public string $name) {}

    public static function fromRequest(ServerRequestInterface $request): self
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];

        return new self(name: trim((string) ($body['name'] ?? '')));
    }

    /** @return array<string, string> */
    public function validate(): array
    {
        return $this->name === '' ? ['name' => 'Name is required.'] : [];
    }

    public function isValid(): bool
    {
        return $this->validate() === [];
    }
}
```

### Services

Business rules live in `src/Service/` and are registered in `src/App/Services.php`. Services
coordinate models, apply rules and throw typed exceptions:

```php
final class CustomerService
{
    public function __construct(private readonly CustomerModel $customerModel) {}

    public function create(string $name): void
    {
        if ($this->customerModel->existsByName($name)) {
            throw new ValidationException('Customer name already exists.', [
                'name' => 'Customer name already exists.',
            ]);
        }

        $this->customerModel->create($name);
    }
}
```

Controllers catch `AppException` and turn it into the standard envelope through
`BaseController::errorResponse()`; an uncaught typed exception still maps to its HTTP status in the
global error handler.

### Models

Every model extends `App\Model\BaseModel`, which owns the SimpleQuery `Connection` and the shared
database helpers. Models own SQL and never construct other models.

```php
final class ExampleModel extends BaseModel
{
    public function list(string $keywords = '', ?int $page = null, ?int $limit = null): array
    {
        $query = $this->db()->table('example')
            ->select('example.id', 'example.name');

        $this->applyKeywordSearch($query, 'example.name', $keywords);
        $this->applyPagination($query, $page, $limit);

        return $query->orderBy('example.id', 'asc')->get();
    }
}
```

See [Database](database.md) for the full helper list and query patterns.

### Middleware

`src/Middleware/` holds PSR-15 middleware:

- `AuthenticationMiddleware` — validates the `Authorization` header via `JwtHelper` and exposes
  the user as the `user` request attribute (401 envelope otherwise).
- `AuthorizationMiddleware` — checks `$user->type` against its constructor allowlist (403
  envelope otherwise).

### Helpers

Stateless utilities under `src/Helper/`:

| Helper | Responsibility |
|--------|----------------|
| `JsonResponse` | Standard `status`/`message`/`data` envelope, low-level JSON writer. |
| `Pagination` | `sanitize`, `apply`, `totalPages`, `clamp` for list endpoints. |
| `JwtHelper` | Build/validate JWTs, map claims to the user object. |
| `TwigResponse` | Render Twig templates as PSR-7 responses. |
| `CacheRedis` | Optional Predis wrapper (not wired into endpoints yet). |
| `UploadHelper` | Local/S3 upload helper (not wired into endpoints yet). |
| `CoreResponse` | Plain-text response helper. |

Helpers are called statically; they never touch the container.

### Views

`src/View/` contains the Twig template for the bundled Swagger UI. Render it through
`TwigResponse::render()`.

### Caching

Read caches use `App\Helper\CacheRedis` (optional Redis) with namespaced keys and O(1)
invalidation: `namespaced()` composes a key, `rememberJson()` fills it and writes call `bump()`
to orphan the namespace. Access fails open when Redis is unconfigured or unreachable. See
[Caching](caching.md).

### Background Jobs

Work is dispatched to the durable `background_job` table and executed by `bin/background-worker`
(`composer run worker`). Job handlers live in `src/Jobs/` and are registered in the `jobRegistry`
in `src/App/Services.php`; `composer run dev` runs the webserver and the worker together. See
[Background Jobs](background-jobs.md).

## Configuration and Caching

- All configuration comes from environment variables (`.env` in development, real environment
  variables in deployments); see [Development](development.md#environment-variables).
- The parsed `.env` is cached in `storage/cache/env.cache.json`. Delete the file after an edit that
  keeps the same size and mtime (rare).
- Twig compiles templates into `storage/cache/twig`; `auto_reload` follows
  `DISPLAY_ERROR_DETAILS`, so production serves the compiled cache. Clear the directory after
  template changes when running with `DISPLAY_ERROR_DETAILS=false`.

## Observability

- **Request ID**: `RequestIdMiddleware` accepts or generates `X-Request-ID`, stores it as the
  `request_id` request attribute and echoes it on every response, including errors. It is
  registered outside the error middleware so the error handler and its log entries can use it.
- **Health endpoints**: `GET /health` (liveness), `GET /health/ready` (database readiness) and
  `GET /health/detailed` (database, storage, memory; protected by `X-Health-Token` /
  `HEALTHCHECK_TOKEN`). Development and testing bypass the token when it is empty.
- **Logging**: the `logger` container service writes to `storage/log/error.log`; uncaught
  exceptions are logged with status, class, request path and request id.
- **Sentry**: `src/App/Sentry.php` initializes the SDK when `SENTRY_DSN` is set. Request bodies
  are never attached, query strings are stripped and authorization/cookie/health headers are
  sanitized; 5xx responses report the exception.

## Error Handling

Uncaught exceptions go through `src/App/ErrorHandler.php`:

- HTTP status comes from the exception code when it is `400..599`, otherwise `500`.
- The response is `application/problem+json` with `{message, status: "error", code}`; `class` and
  `file` are only included when `DISPLAY_ERROR_DETAILS=true`.
- Errors are logged to `storage/log/error.log` with status, class, request path and request id
  (plus the exception when log details are enabled).
- 5xx responses are reported to Sentry when it is configured.

## Related Docs

- [API Conventions](api-conventions.md)
- [Database](database.md)
- [Development](development.md)
