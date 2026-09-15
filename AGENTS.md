# AGENTS.md — Contributor & Agent Guidelines

Rules for working on the **Slim 4 Starter Pack** (`nerdv2/slim4-skeleton`).

> **Status:** this file describes the target conventions for this repository. The codebase is being
> migrated to them in phases P1–P6 (configuration, data layer, HTTP contract, authentication,
> example module, tooling). Follow these rules for new and touched code. Files that have not been
> migrated yet (Pixie models, `AuthToken`, hand-assembled responses) are tracked internally and
> will be rewritten phase by phase.

---

## 1. Quick Commands

```bash
composer install                      # install dependencies
composer run serve                    # dev server at http://127.0.0.1:8080
composer run migrate                  # run Phinx migrations
composer run migrate:rollback         # roll back the last migration
composer run seed                     # run Phinx seeders
composer run generate-openapi-docs    # regenerate public/openapi.yaml + .json
php -l path/to/file.php               # syntax check
```

Quality commands (`composer analyse`, `composer phpcs`, `composer check`) are added in phase P6.

## 2. Documentation Map

| Document | Covers |
|----------|--------|
| [README.md](README.md) | Public overview, setup, deployment notes. |
| [public/openapi.yaml](public/openapi.yaml) + [.json](public/openapi.json) | Generated API reference (regenerate with the command above). |
| `docs/` (local only, git-ignored) | Internal planning: reference analysis, gap analysis and the phased implementation plan. |

## 3. Golden Rules

1. **Do not break the API contract.** Never change existing routes, response keys or the
   `status`/`message`/`data` envelope without an explicit decision. When in doubt, extend instead
   of rename.
2. **Follow the architecture.** Routes → Controllers → Models → MySQL. Controllers own HTTP,
   models own SQL, helpers stay stateless. No ORM, no new framework, no new architecture pattern.
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
       ├─ Container.php   (Pimple PSR-11 + Slim AppFactory)
       ├─ ErrorHandler.php (JSON errors for uncaught exceptions)
       ├─ Middlewares.php (routing, body parsing, error handling, Twig)
       ├─ Cors.php        (development only; gated by CORS_ENABLED)
       ├─ Database.php    ('db' primary, 'db_read' replica)
       ├─ Routes.php      (route definitions)
       └─ NotFound.php    (catch-all 404)
```

Target directory layout:

| Path | Contents |
|------|----------|
| `src/App/` | Bootstrap, container, routes, middleware configuration. |
| `src/Controller/` | Request handlers; extend `BaseController` once P4 lands. |
| `src/Middleware/` | PSR-15 middleware (authentication, authorization) once P4 lands. |
| `src/Model/` | Data access; extend `BaseModel` once P2 lands. |
| `src/Helper/` | Stateless utilities (`JsonResponse`, `Pagination`, `JwtHelper`, ...). |
| `src/Constants/` | Named values (`HttpStatus`, `DateFormat`, `OpenApiTags`). |
| `src/Interfaces/` | Shared contracts (`ModelInterface`). |
| `src/View/` | Twig templates (Swagger UI, redirects). |

## 5. Implementation Conventions

### Controllers

- Extend `App\Controller\BaseController` (added in P4) instead of duplicating a container property.
- Validate and cast request input (`getQueryParams()`, `getParsedBody()`) before passing it to a
  model. Never let raw request values reach SQL.
- Always answer through `App\Helper\JsonResponse`; never assemble the envelope by hand.

```php
public function list(Request $request, Response $response): Response
{
    $get = $request->getQueryParams();
    [$page, $limit] = Pagination::sanitize($get['page'] ?? null, $get['limit'] ?? null);

    $data = $this->model->list($get['keywords'] ?? '', $page, $limit);

    return JsonResponse::success($response, $data, 'Data ditemukan', [
        'total_page' => Pagination::total_pages($total, $limit),
        'total_data' => $total,
    ]);
}
```

### Models

- Extend `App\Model\BaseModel` (added in P2) and obtain a fresh builder per method with
  `$this->db()->table(...)`. Never cache a builder on the model.
- The query builder is `oeltimacreation/php-simplequery` (target; Pixie is still in place until
  P2). Do not instantiate another model inside a model.
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
- Use `App\Helper\Pagination` (`sanitize`, `apply`, `total_pages`, `clamp`); never hand-roll
  `LIMIT`/`OFFSET` math.
- Always provide a deterministic `ORDER BY` with a unique tie-breaker (`id`).

### Routes

- Register routes in `src/App/Routes.php` with a name (`->setName('...')`).
- Protected routes declare middleware next to the route:
  `->add(new AuthenticationMiddleware())->add(new AuthorizationMiddleware(['admin']))`.
- Keep the `NotFound.php` catch-all as the last registration.

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
| P2 | SimpleQuery + `BaseModel` + `Pagination` | pending |
| P3 | Response envelope, error handling, CORS | pending |
| P4 | `JwtHelper`, auth middleware, `BaseController` | pending |
| P5 | Example module + OpenAPI (swagger-php 6) | pending |
| P6 | PHPStan/PHPCS, developer docs | pending |

## 8. Before You Finish

- [ ] `php -l` passes for every changed PHP file.
- [ ] No new dependencies, no new frameworks, no hand-assembled response envelopes.
- [ ] Routes are registered in `src/App/Routes.php` and named.
- [ ] Request input is validated and cast before it reaches a model.
- [ ] Error responses do not leak file paths or raw exception messages outside development.
- [ ] `.env.example`, `README.md` and this file are updated when behaviour or conventions change.
- [ ] No secrets or credentials are committed or logged.
