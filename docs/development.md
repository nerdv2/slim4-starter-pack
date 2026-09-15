# Development

Local setup, environment variables, commands and the workflow for adding endpoints.

## Requirements

- PHP 8.3+ with `pdo_mysql` (`pdo_sqlite` for SQLite), `mbstring`, `json`, `openssl`, `curl` and
  `fileinfo` extensions.
- Composer 2.x.
- MySQL 8.0+ / MariaDB 10.6+ (or SQLite for a quick local database).
- Redis and S3 credentials are optional; the starter runs without them.

## Setup

```bash
composer install
cp .env.example .env
# edit .env (database credentials at minimum, JWT_SECRET before using protected routes)
composer run serve
```

The server listens on `http://127.0.0.1:8080`. Make sure `storage/` is writable: the application
writes the parsed env cache (`storage/cache/env.cache.json`), compiled Twig templates
(`storage/cache/twig/`) and the error log (`storage/log/error.log`) there.

Minimum `.env` for the example module:

```dotenv
APP_BASE_URL="http://localhost:8080"
JWT_SECRET=0123456789abcdef0123456789abcdef   # openssl rand -hex 32
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=root
DB_PASS=secret
DB_NAME=slim4_starter
```

Then `composer run migrate` to create the example `customer` table.

## Environment Variables

| Variable | Purpose |
|----------|---------|
| `APP_NAME` | Application name returned by `GET /`. |
| `APP_VERSION` | Application version returned by `GET /`. |
| `APP_ENVIRONMENT` | `development`, `testing` or `production`; controls the CORS default. |
| `DEFAULT_TIMEZONE` | Application timezone (UTC when empty). |
| `APP_BASE_URL` | API base URL; JWT issuer/audience and Twig base URLs. |
| `APP_KEY` | Application secret (reserved; JWT uses `JWT_SECRET`). |
| `DISPLAY_ERROR_DETAILS` | Adds exception class/file to error payloads and enables Twig auto-reload. Never enable in production. |
| `SLIM_BASH_PATH` | URL base path when served from a sub-directory (leave empty otherwise). |
| `JWT_SECRET` | HMAC-SHA256 signing key, minimum 32 bytes. Required for protected routes. |
| `JWT_IDENTIFIER` | `jti` claim; defaults to the application value when empty. |
| `JWT_TTL` | Token lifetime, `strtotime` compatible (default `+7 day`). |
| `DB_DRIVER` | `mysql`, `mariadb` or `sqlite`. |
| `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME` | Primary connection (SQLite uses `DB_NAME` as the file path). |
| `DB_*_READ` | Optional read replica; each value falls back to its primary counterpart. |
| `DEFAULT_UPLOAD_TARGET` | `filesystem` (default) or `s3`. |
| `CORS_ENABLED` | Enables CORS; defaults to on for development/testing or `localhost`. |
| `CORS_ALLOWED_ORIGINS` | Comma-separated exact origins; empty means wildcard. |
| `CORS_ALLOW_CREDENTIALS` | Reflects credentials for allowed origins. |
| `CORS_ALLOWED_HEADERS`, `CORS_ALLOWED_METHODS`, `CORS_MAX_AGE` | CORS response values. |
| `REDIS_SERVER_*` | Optional Redis connection used by `CacheRedis`. |
| `S3_ENDPOINT`, `S3_REGION`, `S3_KEY`, `S3_SECRET`, `S3_BUCKET`, `S3_CDN_DOMAIN` | Object storage for `UploadHelper`. |

Environment variables are loaded by `src/App/DotEnv.php`; the parsed values are cached and
refreshed when `.env` changes.

## Commands

| Command | Purpose |
|---------|---------|
| `composer run serve` | Development server on `http://127.0.0.1:8080`. |
| `composer run migrate` | Apply Phinx migrations. |
| `composer run migrate:rollback` | Roll back the last migration. |
| `composer run seed` | Run Phinx seeders. |
| `composer run token -- id=1 type=admin` | Generate a development JWT. |
| `composer run generate-openapi-docs` | Regenerate `public/openapi.yaml` and `.json` from the `#[OA\...]` attributes. |
| `composer run analyse` | PHPStan level 5 over `src` and `bin` (zero findings). |
| `composer run phpcs` / `composer run phpcbf` | Check / fix PSR-12 code style. |
| `composer run check` | `composer validate` + PHPStan + PHPCS. |

Swagger UI is available at `http://127.0.0.1:8080/swaggerui`.

## Adding an Endpoint

1. **Add the query** to a model (or create one) extending `App\Model\BaseModel`:

   ```php
   final class ExampleModel extends BaseModel
   {
       public function get(string $keywords = '', ?int $page = null, ?int $limit = null): array
       {
           $query = $this->db()->table('example')->select('example.id', 'example.name');
           $this->applyKeywordSearch($query, 'example.name', $keywords);
           $this->applyPagination($query, $page, $limit);

           return $query->orderBy('example.id', 'asc')->get();
       }

       public function countGet(string $keywords = ''): int
       {
           $query = $this->db()->table('example');
           $this->applyKeywordSearch($query, 'example.name', $keywords);

           return $query->count();
       }
   }
   ```

2. **Add the controller** extending `App\Controller\BaseController`, validate/cast input, and
   answer through `JsonResponse`:

   ```php
   final class Example extends BaseController
   {
       private ExampleModel $exampleModel;

       public function __construct(Container $container)
       {
           parent::__construct($container);
           $this->exampleModel = new ExampleModel($this->db());
       }

       #[OA\Get(path: '/example', tags: [OpenApiTags::DEFAULT])]
       #[OA\Response(response: 200, description: 'Success')]
       public function get(Request $request, Response $response): Response
       {
           $get = $request->getQueryParams();
           [$page, $limit] = Pagination::sanitize($get['page'] ?? null, $get['limit'] ?? null);
           $keywords = trim((string) ($get['keywords'] ?? ''));

           $totalData = $this->exampleModel->countGet($keywords);
           $data = $this->exampleModel->get($keywords, $page, $limit);

           return JsonResponse::success($response, $data, JsonResponse::DEFAULT_SUCCESS_MESSAGE, [
               'total_page' => Pagination::totalPages($totalData, $limit),
               'total_data' => $totalData,
           ]);
       }
   }
   ```

3. **Register the route** in `src/App/Routes.php` with a name; add middleware for protected
   endpoints (`AuthorizationMiddleware` first, `AuthenticationMiddleware` last):

   ```php
   $app->get('/example', 'App\Controller\Example:get')->setName('api.example.list');
   ```

4. **Annotate** with `#[OA\...]` attributes and regenerate:
   `composer run generate-openapi-docs`.

5. **Verify** with `composer run check` and a manual request:

   ```bash
   curl -s 'http://localhost:8080/example?page=1&limit=10' | jq
   curl -s -X POST http://localhost:8080/example \
     -H "Authorization: Bearer $(composer run token -- id=1 type=admin)" \
     -d 'name=Acme' | jq
   ```

Before finishing, run the checklist in `AGENTS.md`: syntax check, `composer check`, envelope
preserved, routes named and registered, input validated.

## Testing

The test suite runs on PHPUnit with SQLite; no database server is required. `tests/bootstrap.php`
forces `DB_DRIVER=sqlite` and a throwaway `storage/test_database.sqlite`, `Tests\TestCase` applies
the Phinx migrations once per run, cleans the tables before every test and exposes HTTP helpers
(`createRequest`, `handle`, `json`, `authHeader`).

```bash
composer run test              # all suites
composer run test-unit         # tests/Unit
composer run test-integration  # tests/Integration
```

Layout:

```text
tests/
├── bootstrap.php              # forces the SQLite test environment
├── TestCase.php               # app bootstrap, migrations, HTTP helpers, cleanup
├── TestFactory.php            # Faker-backed row factories
├── Unit/                      # helpers and other isolated classes
└── Integration/Controller/    # endpoints exercised through the Slim app
```

Test configuration lives in `phpunit.xml`. Keep tests deterministic and never point them at a real
database. To run the application itself against SQLite locally:

```bash
DB_DRIVER=sqlite DB_NAME=storage/test_database.sqlite composer run migrate
```

## Related Docs

- [Architecture](architecture.md)
- [API Conventions](api-conventions.md)
- [Database](database.md)
- [AGENTS.md](../AGENTS.md) — contributor and agent rules.
