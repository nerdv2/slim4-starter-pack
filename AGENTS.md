# AGENTS.md — Contributor & Agent Guidelines

Rules for working on **Customer DB** (the `nerdv2/slim4-starter-pack` repository, a Slim 4 JSON API
with a Vue 3 frontend in `nerdv2/vue3-starter-pack`).

> **Status:** the codebase follows these conventions (phases P0–P7: configuration, data layer,
> HTTP contract, authentication, customer module, quality gates, documentation and the product
> build-out). Follow them for new and touched code.

---

## 1. Quick Commands

```bash
composer install                      # install dependencies
composer run serve                    # dev server at http://127.0.0.1:8080
composer run dev                      # dev server + background worker
composer run worker                   # background worker (default queue)
composer run migrate                  # run Phinx migrations
composer run migrate:rollback         # roll back the last migration
composer run seed                     # run Phinx seeders
composer run token -- id=1 type=admin # generate a development JWT
composer run generate-openapi-docs    # regenerate public/openapi.yaml + .json
composer run routes:cache             # compile the FastRoute dispatcher cache
composer run analyse                  # PHPStan level 5 (zero findings)
composer run phpcs                    # PSR-12 code style check
composer run check                    # composer validate + analyse + phpcs
composer run test                     # PHPUnit suites (SQLite)
composer run test-unit                # unit suite only
composer run test-integration         # integration suite only
php -l path/to/file.php               # syntax check
```

## 2. Documentation Map

| Document | Covers |
|----------|--------|
| [README.md](README.md) | Public overview, setup, deployment notes. |
| [docs/](docs/README.md) | Developer guides: architecture, authentication, API conventions, database, development, background jobs, deployment. Internal planning notes also live in `docs/` but are git-ignored. |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Contribution workflow, quality gates and commit conventions. |
| [SECURITY.md](SECURITY.md) | Vulnerability reporting and deployment hardening. |
| [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md) | Community expectations and enforcement. |
| [public/openapi.yaml](public/openapi.yaml) + [.json](public/openapi.json) | Generated API reference (regenerate with the command above). |

## 3. Golden Rules

1. **Do not break the API contract.** Never change existing routes, response keys or the
   `status`/`message`/`data` envelope without an explicit decision. When in doubt, extend instead
   of rename.
2. **Follow the architecture.** Routes → Controllers → Services → Models → MySQL. Controllers own
   HTTP, services own business rules, models own SQL, helpers stay stateless. No ORM, no new
   framework, no new architecture pattern.
3. **No new Composer dependency without an explicit decision** recorded before the change.
4. **`declare(strict_types=1);` in every PHP file**; classes are `final` unless designed for
   extension.
5. **Never commit secrets.** `.env` is git-ignored; credentials belong in environment variables.
6. **Match the file you touch.** Keep the existing naming and formatting; do not reformat
   unrelated code in the same change.

## 4. Architecture Cheat Sheet

```text
public/index.php
  └─ src/App/App.php
       ├─ DotEnv.php      (.env → $_SERVER / $_ENV; parse cached in storage/cache)
       ├─ Sentry.php      (optional error reporting from SENTRY_DSN)
       ├─ Container.php   (Pimple PSR-11 + Slim AppFactory)
       ├─ Logging.php     (Monolog channel → storage/log/error.log)
       ├─ ErrorHandler.php (JSON errors for uncaught exceptions)
       ├─ Middlewares.php (routing, body parsing, error handling, request id, Twig)
       ├─ Cors.php        (development only; gated by CORS_ENABLED)
       ├─ Database.php    ('db' primary, 'db_read' replica; mysql/mariadb/sqlite)
       ├─ Services.php    (container registration for models and services)
       ├─ Routes.php      (route definitions)
       └─ NotFound.php    (catch-all 404)
```

Target directory layout:

| Path | Contents |
|------|----------|
| `src/App/` | Bootstrap, container, routes, middleware configuration. |
| `src/Controller/` | Request handlers; extend `BaseController`. |
| `src/Service/` | Business rules; the only layer that coordinates models and throws typed exceptions. |
| `src/DTO/` | Request payloads with `fromRequest`, `validate` and `isValid`. |
| `src/Exceptions/` | Typed application exceptions (`AppException`, `ValidationException`, `NotFoundException`). |
| `src/Middleware/` | PSR-15 middleware (authentication, authorization) once P4 lands. |
| `src/Model/` | Data access; extend `BaseModel` once P2 lands. |
| `src/Jobs/` | Background job handlers registered in the queue `jobRegistry`. |
| `src/Helper/` | Stateless utilities (`JsonResponse`, `Pagination`, `JwtHelper`, ...). |
| `src/Constants/` | Named values (`HttpStatus`, `DateFormat`, `OpenApiTags`). |
| `src/Interfaces/` | Shared contracts (`ModelInterface`). |
| `src/View/` | Twig templates (Swagger UI, redirects). |
| `tests/` | PHPUnit suites (`Unit`, `Integration`), `TestCase` and `TestFactory`; SQLite-backed, never a real database. |

## 5. Implementation Conventions

### Controllers

- Extend `App\Controller\BaseController` instead of duplicating a container property.
- Validate and cast request input (`getQueryParams()`, `getParsedBody()`) before passing it to a
  model. Never let raw request values reach SQL.
- Always answer through `App\Helper\JsonResponse`; never assemble the envelope by hand.
- Annotate endpoints with `#[OA\...]` attributes using `App\Constants\OpenApiTags` and regenerate
  the specification with `composer run generate-openapi-docs`.

```php
public function list(Request $request, Response $response): Response
{
    $get = $request->getQueryParams();
    [$page, $limit] = Pagination::sanitize($get['page'] ?? null, $get['limit'] ?? null);

    $result = $this->exampleService->list($get['keywords'] ?? '', $page, $limit);

    return JsonResponse::success($response, $result['data'], 'Data ditemukan', [
        'total_page' => $result['total_page'],
        'total_data' => $result['total_data'],
    ]);
}
```

### DTOs

- Request payloads are `final readonly` classes under `src/DTO/Request/` with a static
  `fromRequest(ServerRequestInterface)` factory, `validate(): array` (field-keyed messages) and
  `isValid(): bool`.
- Controllers validate the DTO before calling a service; invalid payloads return
  `JsonResponse::error($response, 'Validation failed.', $dto->validate(), [], HttpStatus::BAD_REQUEST)`.

### Services

- Business rules live in `src/Service/` classes registered in `src/App/Services.php`; controllers
  resolve them from the container (for example `$container->get('customerService')`).
- Services throw typed exceptions (`ValidationException`, `NotFoundException`); controllers catch
  `AppException` and return the envelope through `BaseController::errorResponse()`.

### Authentication

- Access tokens are short-lived JWTs (`JwtHelper::buildToken`, `JWT_ACCESS_TTL`); refresh tokens are
  opaque, rotated inside session families and stored as SHA-256 hashes (`RefreshTokenModel`).
- Session logic lives in `AuthService`; the refresh cookie is built and read by `RefreshCookie` and
  only ever travels in `Set-Cookie` headers — never in a JSON body.
- Changing a password revokes every other session family; logout revokes the presented family.
- New protected routes follow the middleware order in the Routes section below.

### Jobs

- Handlers live in `src/Jobs/` and implement `Oeltima\SimpleQueue\Contract\JobHandlerInterface`.
- Register every job type in the `jobRegistry` in `src/App/Services.php` and dispatch through the
  `jobDispatcher` service.
- Keep payloads small and JSON-serializable; return a result value or throw to trigger retries.

### Caching

- Read caches use `App\Helper\CacheRedis` with `namespaced()` keys and `rememberJson()`; writes
  invalidate with `bump('<namespace>')`.
- Cache access must fail open (Redis is optional) and stay bounded: always pass a TTL and respect
  `MAX_PAYLOAD_BYTES`; document new cached endpoints and their invalidation in `docs/caching.md`.

### Models

- Extend `App\Model\BaseModel` and obtain a fresh builder per method with
  `$this->db()->table(...)`. Never cache a builder on the model.
- The query builder is `oeltimacreation/php-simplequery`; do not add an ORM or instantiate
  another model inside a model.
- Soft-deleted rows are filtered with `whereNull('deleted_at')` where the table supports it.
- Timestamps are set explicitly (`date(DateFormat::DATETIME)`); there is no ORM magic.
- Select only the columns you use; use a `COUNT(*)` companion for paginated lists.

### Responses

- Standard envelope: `{ "status": bool, "message": string, "data": mixed }`.
- Lists add `total_page` and `total_data` as top-level keys.
- Use `JsonResponse::success()`, `JsonResponse::error()`, `JsonResponse::notFound()`; keep
  `JsonResponse::withJson()` only for non-envelope payloads.
- Uncaught exceptions produce `application/problem+json`; file paths and raw messages are only
  exposed when `DISPLAY_ERROR_DETAILS=true` (development).

### Pagination

- `page` defaults to `1`, clamped to `>= 1`; `limit` defaults to `20`, clamped to `[1, 100]`.
- Use `App\Helper\Pagination` (`sanitize`, `apply`, `totalPages`, `clamp`); never hand-roll
  `LIMIT`/`OFFSET` math.
- Always provide a deterministic `ORDER BY` with a unique tie-breaker (`id`).

### Routes

- Register routes in the matching file under `src/App/routes/` (core, health, customer,
  background_jobs) and add new files to the manifest in `src/App/Routes.php`.
- Name every route (`->setName('...')`) and keep registrations flat.
- Route definitions must not depend on the request environment: the compiled route cache is built
  without a request, so a conditional route would shift identifiers and serve the wrong handler.
  Put environment-specific behaviour in middleware instead.
- Protected routes declare middleware next to the route. Slim runs the last-added middleware
  first, so add the authorization check first and authentication last:

  ```php
  ->add(new AuthorizationMiddleware(['admin']))
  ->add(new AuthenticationMiddleware())
  ```

- Keep the `NotFound.php` catch-all as the last registration, and run `composer run routes:cache`
  after changing routes in production (the cache is disabled while `DISPLAY_ERROR_DETAILS` is on).

### Configuration

- Every setting comes from `.env` through `$_SERVER`/`$_ENV` and is documented in `.env.example`.
- Never hardcode hosts, credentials, paths or feature flags.
- Development-only behaviour (CORS, error details, Twig auto-reload) is gated by an environment
  flag, never by the client address.

### Commit Messages

This repository uses [Conventional Commits](https://www.conventionalcommits.org/):

```text
type(scope): description

Optional body explaining why the change was made.

Optional footer (e.g. BREAKING CHANGE: ...)
```

- Types: `feat`, `fix`, `docs`, `refactor`, `perf`, `test`, `chore`, `ci`, `build`, `release`.
- `scope` is optional and names the area (`docs`, `gitignore`, `db`, `auth`, `http`, ...).
- Description is lowercase, imperative and has no trailing period.
- Add a body when the reason for the change is not obvious from the diff; keep one logical
  change per commit so the history stays reviewable.
- Mark incompatible changes with `BREAKING CHANGE:` in the footer and in the description (`!`
  after the type/scope is also accepted).

Examples:

```text
feat(auth): add jwt helper and authentication middleware
fix(upload): correct double file extension on upload validation
chore(gitignore): ignore internal planning docs
docs(agents): add contributor and agent guidelines
```

## 6. Security

- Validate and cast every request value before it reaches a model.
- JWT signing uses a dedicated `JWT_SECRET` (minimum 32 bytes, from the environment); never reuse
  `APP_KEY` or log tokens.
- CORS is a development aid. Production CORS is handled by the webserver/reverse proxy; do not
  loosen the allowlist without an explicit decision.
- Keep `DISPLAY_ERROR_DETAILS` disabled outside development.
- Uploads: validate extension/size and store with sanitized names; never trust the client
  filename.

## 7. Migration Status

| Phase | Scope | State |
|-------|-------|-------|
| P0 | Conventions locked in this file | done |
| P1 | Configuration & environment | done |
| P1b | Caching: env parse + Twig templates | done |
| P2 | SimpleQuery + `BaseModel` + `Pagination` | done |
| P3 | Response envelope, error handling, CORS | done |
| P4 | `JwtHelper`, auth middleware, `BaseController` | done |
| P5 | Example module + OpenAPI (swagger-php 6) | superseded by P7 |
| P6 | PHPStan/PHPCS, developer docs | done |
| P7 | Product build-out: user accounts + refresh sessions, customer module with avatars, queued CSV export/import, Vue 3 SPA | done |

## 8. Before You Finish

- [ ] `composer run check` passes (composer validate + PHPStan + PHPCS).
- [ ] `composer run test` passes; tests run on SQLite, never against a real database.
- [ ] New services and models are registered in `src/App/Services.php`.
- [ ] New background job types are registered in the `jobRegistry`.
- [ ] `php -l` passes for every changed PHP file.
- [ ] No new dependencies, no new frameworks, no hand-assembled response envelopes.
- [ ] Routes are registered in `src/App/Routes.php` and named.
- [ ] Request input is validated and cast before it reaches a model.
- [ ] Error responses do not leak file paths or raw exception messages outside development.
- [ ] `.env.example`, `README.md` and this file are updated when behaviour or conventions change.
- [ ] No secrets or credentials are committed or logged.
