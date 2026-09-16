# slim4-starter-pack

[![CI](https://github.com/nerdv2/slim4-starter-pack/actions/workflows/ci.yml/badge.svg)](https://github.com/nerdv2/slim4-starter-pack/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP 8.3+](https://img.shields.io/badge/php-8.3%2B-777bb4.svg)](composer.json)

A production-ready [Slim 4](https://www.slimframework.com/) starter for building JSON REST APIs:
a no-ORM query builder, JWT authentication, a background job queue, optional Redis caching and
Twig templating — with the architecture rules, quality gates and deployment guides included.

## Background

This project started when I needed a replacement for CodeIgniter 3 for a more modern development
environment while retaining the familiar Model-View-Controller (MVC) structure, with a lightweight
enough framework to develop for. The conventions and structure follow what has been proven in
production projects built from this starter (see [AGENTS.md](AGENTS.md) and the
[documentation](docs/README.md)).

## Features

**HTTP & API**

- Slim 4 scaffolding with PSR-7, PSR-11 and PSR-15 implementations
- Standard JSON response envelope (`status`/`message`/`data`), pagination metadata and
  `application/problem+json` error output with Monolog logging
- `X-Request-ID` request tracing and env-driven CORS
- OpenAPI 3 specification generated from `#[OA\...]` attributes (swagger-php 6) with a bundled
  Swagger UI

**Data layer**

- [oeltimacreation/php-simplequery](https://github.com/oeltimacreation/php-simplequery) query
  builder (MySQL, MariaDB and SQLite; no ORM) with a shared `BaseModel`, pagination, keyword search
  and managed transactions
- Phinx migrations and seeders, plus an optional read replica connection

**Authentication**

- JWT (lcobucci/jwt) with PSR-15 authentication and role-based authorization middleware
- Development token command for local testing

**Background jobs**

- [oeltimacreation/php-simplequeue](https://github.com/oeltimacreation/php-simplequeue) queue with a
  worker binary, dispatch/status endpoints, retries, stuck-job recovery and graceful recycling

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
DB_DRIVER=sqlite DB_NAME=storage/test_database.sqlite composer run migrate
DB_DRIVER=sqlite DB_NAME=storage/test_database.sqlite JWT_SECRET="$(openssl rand -hex 32)" composer run serve
```

**MySQL / MariaDB.** Edit `.env` with the database credentials and a `JWT_SECRET`
(`openssl rand -hex 32`), then:

```bash
composer run migrate
composer run serve
```

The server listens on http://127.0.0.1:8080; `composer run dev` starts the webserver together with
the background worker. Make sure `storage/` is writable: the application writes the parsed env
cache, compiled Twig templates, the route cache and logs there.

Smoke checks:

```bash
curl -s localhost:8080/ | jq                 # application status envelope
curl -s localhost:8080/health/ready | jq     # database readiness
```

Swagger UI is available at http://127.0.0.1:8080/swaggerui.

## Authentication

Protected endpoints expect a JWT in the `Authorization` header (raw token or `Bearer <token>`).
The bundled command signs a development token with `JWT_SECRET`:

```bash
TOKEN=$(composer run token -- id=1 type=admin)

# Public list endpoint
curl -s 'http://localhost:8080/customer?page=1&limit=10' | jq

# Admin-only write endpoint
curl -s -X POST http://localhost:8080/customer/add \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"name":"Acme"}' | jq
```

See [API Conventions](docs/api-conventions.md) for token claims, roles and error responses.

## Project structure

```text
src/App/             bootstrap, container, routes and middleware wiring
src/Controller/      HTTP handlers
src/Service/         business rules
src/Model/           SimpleQuery data access
src/DTO/Request/     validated request payloads
src/Middleware/      authentication, authorization, health token, request id
src/Jobs/            background job handlers
src/Helper/          JsonResponse, Pagination, JwtHelper, UploadHelper, ...
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
| `composer run seed` | Run Phinx seeders. |
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
| [API Conventions](docs/api-conventions.md) | Response envelope, authentication, pagination, errors and CORS. |
| [Database](docs/database.md) | Connections, `BaseModel`, query patterns and migrations. |
| [Development](docs/development.md) | Local setup, environment variables, commands and adding an endpoint. |
| [Background Jobs](docs/background-jobs.md) | Queue architecture, job handlers, worker and dev runner. |
| [Caching](docs/caching.md) | Redis design, key format, invalidation map and operations. |
| [Deployment](docs/deployment.md) | Container image, migrations, worker containers, Compose and CI. |
| [AGENTS.md](AGENTS.md) | Architecture rules, layering and contributor conventions. |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Contribution workflow, quality gates and commit conventions. |
| [SECURITY.md](SECURITY.md) | Vulnerability reporting and deployment hardening. |
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

CORS is env-driven (`CORS_ENABLED`) and defaults to on for `development`/`testing`. In production,
either allow exact origins with `CORS_ALLOWED_ORIGINS`, or disable it and let the webserver or
reverse proxy manage CORS and preflight requests — do not do both at once, as duplicate
`Access-Control-Allow-*` headers confuse browsers. Keep `DISPLAY_ERROR_DETAILS=false` and always set
`JWT_SECRET` and `HEALTHCHECK_TOKEN` in deployed environments.

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
