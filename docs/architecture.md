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
| Cache | Parsed `.env` and compiled Twig templates (filesystem) |
| Authentication | JWT (`lcobucci/jwt` 5) |
| API docs | OpenAPI via `zircote/swagger-php` 6 + bundled Swagger UI |
| Migrations | Phinx |
| Logging | Monolog (`storage/log/error.log`) |
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
│   ├── Middlewares.php  Routing, optional BasePath, body parser, error, Twig
│   ├── NotFound.php     Catch-all 404 route
│   └── Routes.php       Route definitions
├── Constants/           HttpStatus, DateFormat, OpenApiTags
├── Controller/          HTTP handlers (BaseController, Hello, Customer, OpenApi)
├── Helper/              Stateless utilities (JsonResponse, Pagination, JwtHelper, ...)
├── Interfaces/          ModelInterface
├── Middleware/          AuthenticationMiddleware, AuthorizationMiddleware
├── Model/               Data access (BaseModel, CustomerModel, HelloModel)
└── View/                Twig templates (Swagger UI)
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
3. `Container.php` — creates the Slim app with a Pimple PSR-11 container.
4. `ErrorHandler.php` — builds the custom error handler.
5. `Middlewares.php` — routing, optional BasePath (`SLIM_BASH_PATH`), body parsing, error handling
   and Twig (compiled templates cached in `storage/cache/twig`).
6. `Cors.php` — registered when `CORS_ENABLED` is true (default: development/testing or
   `localhost`).
7. `Database.php` — registers the `db` and `db_read` SimpleQuery connections.
8. `Routes.php` — registers every route.
9. `NotFound.php` — catch-all route that throws `HttpNotFoundException`.

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

## Configuration and Caching

- All configuration comes from environment variables (`.env` in development, real environment
  variables in deployments); see [Development](development.md#environment-variables).
- The parsed `.env` is cached in `storage/cache/env.cache.json`. Delete the file after an edit that
  keeps the same size and mtime (rare).
- Twig compiles templates into `storage/cache/twig`; `auto_reload` follows
  `DISPLAY_ERROR_DETAILS`, so production serves the compiled cache. Clear the directory after
  template changes when running with `DISPLAY_ERROR_DETAILS=false`.

## Error Handling

Uncaught exceptions go through `src/App/ErrorHandler.php`:

- HTTP status comes from the exception code when it is `400..599`, otherwise `500`.
- The response is `application/problem+json` with `{message, status: "error", code}`; `class` and
  `file` are only included when `DISPLAY_ERROR_DETAILS=true`.
- Errors are logged to `storage/log/error.log` with status, class and request path (plus the
  exception when log details are enabled).

## Related Docs

- [API Conventions](api-conventions.md)
- [Database](database.md)
- [Development](development.md)
