# Customer DB — Slim 4 API

[![CI](https://github.com/nerdv2/slim4-starter-pack/actions/workflows/ci.yml/badge.svg)](https://github.com/nerdv2/slim4-starter-pack/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP 8.3+](https://img.shields.io/badge/php-8.3%2B-777bb4.svg)](composer.json)

A production-ready customer database API built on a hardened
[Slim 4](https://www.slimframework.com/) foundation: a no-ORM query builder, access + refresh token
authentication, a durable background queue with CSV export/import, avatar uploads, optional Redis
caching and generated OpenAPI documentation. The matching Vue 3 + Vite frontend lives in the
sibling [vue3-starter-pack](https://github.com/nerdv2/vue3-starter-pack) repository.

## Features

**HTTP & API**

- Slim 4 scaffolding with PSR-7, PSR-11 and PSR-15 implementations
- Standard JSON response envelope (`status`/`message`/`data`), pagination metadata and
  `application/problem+json` error output with Monolog logging
- `X-Request-ID` request tracing, env-driven CORS and cookie-aware credentials for the SPA
- OpenAPI 3 specification generated from `#[OA\...]` attributes (swagger-php 6) with a bundled
  Swagger UI

**Authentication**

- Access + refresh token sessions: short-lived JWT access tokens, opaque HttpOnly refresh cookies
- Refresh rotation inside session families with reuse detection (a replayed token revokes the
  family)
- `admin` / `staff` roles enforced by PSR-15 middleware, bcrypt passwords, change-password with
  selective session revocation

**Customer management**

- Full CRUD with soft deletes, keyword search, status filter and pagination
- Avatar uploads (JPEG/PNG/WebP) with automatic cleanup of replaced files
- Dashboard statistics cached in Redis when configured

**Data layer**

- [oeltimacreation/php-simplequery](https://github.com/oeltimacreation/php-simplequery) query
  builder (MySQL, MariaDB and SQLite; no ORM) with a shared `BaseModel`, pagination and managed
  transactions
- Phinx migrations and seeders (schema, bootstrap admin, demo customers), plus an optional read
  replica connection

**Background jobs**

- [oeltimacreation/php-simplequeue](https://github.com/oeltimacreation/php-simplequeue) queue with a
  worker binary, progress reporting, retries, stuck-job recovery and graceful recycling
- Queued **CSV export** (filters, progress, authenticated download) and **CSV import** (per-row
  validation, chunked transactional inserts, per-row error report)

**Performance & operations**

- Optional Redis cache with namespaced keys, O(1) invalidation and fail-open behaviour
- Compiled FastRoute dispatcher cache for production (`composer run routes:cache`)
- Health endpoints (`/health`, `/health/ready`, `/health/detailed`), optional Sentry error reporting
- Digest-pinned PHP 8.3 + Nginx container image (`Dockerfile`), separate worker container and a CI
  build/smoke test

**Quality**

- PHPStan level 5, PHPCS (PSR-12) and PHPUnit unit/integration suites on SQLite — no database server
  required for tests

## Requirements

- PHP 8.3+ with `pdo_mysql` (`pdo_sqlite` for SQLite), `mbstring`, `json`, `openssl`, `curl` and
  `fileinfo` extensions
- Composer 2.x
- MySQL 8.0+ / MariaDB 10.6+, or SQLite for a quick start
- Redis and S3 credentials are optional

## Quick start

```bash
git clone https://github.com/nerdv2/slim4-starter-pack.git
cd slim4-starter-pack
composer install
cp .env.example .env
```

Then pick one of the two setup paths.

**SQLite — zero configuration.** Shell environment variables override `.env`:

```bash
DB_DRIVER=sqlite DB_NAME=storage/dev_database.sqlite JWT_SECRET="$(openssl rand -hex 32)" composer run migrate
DB_DRIVER=sqlite DB_NAME=storage/dev_database.sqlite JWT_SECRET="$(openssl rand -hex 32)" composer run seed
DB_DRIVER=sqlite DB_NAME=storage/dev_database.sqlite JWT_SECRET="$(openssl rand -hex 32)" composer run dev
```

**MySQL / MariaDB.** Edit `.env` with the database credentials and a `JWT_SECRET`
(`openssl rand -hex 32`), then:

```bash
composer run migrate
composer run seed
composer run dev
```

`composer run dev` starts the webserver together with the background worker; `composer run serve`
starts the webserver alone. The server listens on http://127.0.0.1:8080. Make sure `storage/` is
writable: the application writes the parsed env cache, compiled Twig templates, the route cache,
exports/imports and logs there.

The seeder creates the bootstrap administrator and demo customers. Change the password before
deploying anywhere:

| Email | Password |
|-------|----------|
| `admin@example.com` | `Admin123!` |

Smoke checks:

```bash
curl -s localhost:8080/ | jq                 # application status envelope
curl -s localhost:8080/health/ready | jq     # database readiness
```

Swagger UI is available at http://127.0.0.1:8080/swaggerui.

## API overview

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| `POST` | `/auth/register` | — | Create a `staff` account and start a session. |
| `POST` | `/auth/login` | — | Email + password → access token + refresh cookie. |
| `POST` | `/auth/refresh` | cookie | Rotate the refresh cookie, issue a new access token. |
| `POST` | `/auth/logout` | cookie | Revoke the session family. |
| `GET` | `/auth/me` | token | Current account. |
| `PUT` | `/auth/profile` | token | Update the display name. |
| `POST` | `/auth/change-password` | token | Change the password and revoke other sessions. |
| `GET` | `/customer` | token | Paginated list (`page`, `limit`, `keywords`, `status`). |
| `GET` | `/customer/stats` | token | Dashboard counts per status. |
| `GET` | `/customer/{id}` | token | Customer detail. |
| `POST` | `/customer` | admin | Create a customer. |
| `PUT` | `/customer/{id}` | admin | Update a customer. |
| `DELETE` | `/customer/{id}` | admin | Soft-delete a customer. |
| `POST` | `/customer/{id}/avatar` | admin | Upload/replace the avatar (multipart `avatar`). |
| `DELETE` | `/customer/{id}/avatar` | admin | Remove the avatar. |
| `POST` | `/customer/export` | token | Queue a CSV export (`keywords`, `status`). |
| `GET` | `/customer/export/{id}/download` | token | Download a completed export. |
| `POST` | `/customer/import` | admin | Queue a CSV import (multipart `file`). |
| `GET` | `/jobs/{id}` | token | Background job status, progress and result. |

Authentication details, cookie attributes and reuse detection: [docs/authentication.md](docs/authentication.md).

### Sessions with curl

```bash
# Login and keep the refresh cookie in a jar
curl -s -c cookies.txt -X POST http://localhost:8080/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"Admin123!"}' | jq

TOKEN=$(curl -s -c cookies.txt -X POST http://localhost:8080/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"Admin123!"}' | jq -r '.data.access_token')

# Authenticated request
curl -s -H "Authorization: Bearer $TOKEN" 'http://localhost:8080/customer?limit=5' | jq

# Rotate the session (uses the stored cookie)
curl -s -b cookies.txt -c cookies.txt -X POST http://localhost:8080/auth/refresh | jq
```

For local API testing without a user table, `composer run token -- id=1 type=admin` signs a
long-lived development JWT.

### CSV export and import

Exports and imports run through the queue, so start the worker (`composer run dev` or
`composer run worker`). The import CSV must have a `name` column; `email`, `phone`, `company`,
`status`, `address` and `notes` are optional. Rows with an existing name/email are skipped, invalid
rows are reported individually.

```bash
# Queue an export of active customers
curl -s -X POST http://localhost:8080/customer/export \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"status":"active"}' | jq

# Poll the job, then download
curl -s -H "Authorization: Bearer $TOKEN" http://localhost:8080/jobs/1 | jq
curl -s -H "Authorization: Bearer $TOKEN" -OJ http://localhost:8080/customer/export/1/download

# Import
curl -s -X POST http://localhost:8080/customer/import \
  -H "Authorization: Bearer $TOKEN" -F 'file=@customers.csv' | jq
```

## Project structure

```text
src/App/             bootstrap, container, routes and middleware wiring
src/Controller/      HTTP handlers (Auth, Customer, CustomerTransfer, Job, Health, ...)
src/Service/         business rules (AuthService, CustomerService, CustomerTransferService)
src/Model/           SimpleQuery data access
src/DTO/Request/     validated request payloads
src/Middleware/      authentication, authorization, health token, request id
src/Jobs/            background job handlers (CSV export/import)
src/Helper/          JsonResponse, Pagination, JwtHelper, RefreshCookie, UploadHelper, Storage, ...
db/migrations/       Phinx migrations and seeders
public/              front controller, Swagger UI and generated OpenAPI files
tests/               PHPUnit unit and integration suites (SQLite)
docs/                developer guides
```

## Commands

| Command | Purpose |
|---------|---------|
| `composer run serve` | Development server on http://127.0.0.1:8080. |
| `composer run dev` | Development server + background worker with prefixed output. |
| `composer run worker` | Background worker for the default queue. |
| `composer run migrate` | Apply Phinx migrations. |
| `composer run migrate:rollback` | Roll back the last migration. |
| `composer run seed` | Seed the admin account and demo customers. |
| `composer run token -- id=1 type=admin` | Generate a development JWT. |
| `composer run generate-openapi-docs` | Regenerate `public/openapi.yaml` and `.json`. |
| `composer run routes:cache` | Compile the production route cache. |
| `composer run check` | `composer validate` + PHPStan level 5 + PHPCS (PSR-12). |
| `composer run test` | PHPUnit suites (SQLite, no database server required). |
| `composer run test-unit` / `test-integration` | Run a single test suite. |

## Documentation

| Document | Description |
|----------|-------------|
| [Architecture](docs/architecture.md) | Stack, bootstrap sequence, request lifecycle and layering. |
| [Authentication](docs/authentication.md) | Access/refresh tokens, rotation, cookies and roles. |
| [API Conventions](docs/api-conventions.md) | Response envelope, authentication, pagination, errors and CORS. |
| [Database](docs/database.md) | Connections, `BaseModel`, query patterns and migrations. |
| [Development](docs/development.md) | Local setup, environment variables, commands and adding an endpoint. |
| [Background Jobs](docs/background-jobs.md) | Queue architecture, the CSV jobs, the worker and the dev runner. |
| [Caching](docs/caching.md) | Redis design, key format, invalidation map and operations. |
| [Deployment](docs/deployment.md) | Container image, migrations, worker containers, Compose and CI. |
| [AGENTS.md](AGENTS.md) | Architecture rules, layering and contributor conventions. |
| [public/openapi.yaml](public/openapi.yaml) | Generated API reference. |

## Server deployment

The recommended path is the digest-pinned container image (`Dockerfile`), including a separate
worker container and a CI build/smoke test — see [Deployment](docs/deployment.md).

The application also runs on a plain PHP-FPM host: point the webserver at the `public/` folder and
use the virtual host setup for NGINX running on Ubuntu Server 24.04 LTS below.

```nginx
server {
        listen 80;
        server_name     your_domain;

        root /var/www/slim4-starter-pack/public/;
        index index.php index.html;

        location / {
                try_files $uri $uri/ /index.php$is_args$args;
        }

        location ~ \.php$ {
                include snippets/fastcgi-php.conf;
                fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        }

        error_page 404 /index.php;
}
```

CORS is env-driven (`CORS_ENABLED`) and defaults to on for `development`/`testing`. The Vue dev
server expects `CORS_ALLOWED_ORIGINS="http://localhost:5173"` and `CORS_ALLOW_CREDENTIALS=true`
(credentials are required for the refresh cookie). In production, either allow the exact frontend
origin and keep credentials enabled, or serve the SPA from the same origin and disable CORS. Keep
`DISPLAY_ERROR_DETAILS=false` and always set `JWT_SECRET` and `HEALTHCHECK_TOKEN` in deployed
environments.

## Contributing

Contributions are welcome. Start with [CONTRIBUTING.md](CONTRIBUTING.md) for the setup, quality
gates and commit conventions; [AGENTS.md](AGENTS.md) documents the architecture rules the code
follows and [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md) covers community expectations.

## Security

Report suspected vulnerabilities privately through
[GitHub security advisories](https://github.com/nerdv2/slim4-starter-pack/security/advisories/new)
or by email, as described in [SECURITY.md](SECURITY.md). Do not open a public issue for a security
problem.

## License

Released under the [MIT License](LICENSE).
