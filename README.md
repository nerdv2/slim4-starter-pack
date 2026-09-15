# slim4-starter-pack

A slim starter project to allow developing using Slim 4 easier, contains REST API, Query builder, and Twig templating engine.

## Background

This project started when I needed a replacement for CodeIgniter 3 for a more modern development environment while retaining the familiar Model-View-Controller (MVC) structure, with a lightweight enough framework to develop for.

The conventions and structure follow what has been proven in production projects built from this
starter (see [AGENTS.md](AGENTS.md) and the [documentation](docs/README.md)).

## Requirements

- PHP 8.3+ with `pdo_mysql` (`pdo_sqlite` for SQLite), `mbstring`, `json`, `openssl`, `curl` and
  `fileinfo` extensions
- Composer 2.x
- MySQL 8.0+ / MariaDB 10.6+ (SQLite works for a quick local setup)
- Redis and S3 credentials are optional

## Initial setup

```bash
composer install
cp .env.example .env
# edit .env: database credentials and JWT_SECRET (openssl rand -hex 32)
composer run migrate
```

- Make sure the `storage` folder is writable: the application writes the parsed env cache,
  compiled Twig templates and the error log there.

## Starting application

Execute `composer run serve` to start the development server, by default the application serves on
http://127.0.0.1:8080. Use `composer run dev` to start the webserver together with the background
worker.

Smoke checks:

```bash
curl -s localhost:8080/ | jq                       # application status envelope
curl -s localhost:8080/swaggerui                   # Swagger UI
composer run token -- id=1 type=admin              # development JWT
```

## Documentation

| Document | Description |
|----------|-------------|
| [docs/architecture.md](docs/architecture.md) | Stack, bootstrap sequence, request lifecycle and layering. |
| [docs/api-conventions.md](docs/api-conventions.md) | Response envelope, authentication, pagination, errors and CORS. |
| [docs/database.md](docs/database.md) | Connections, `BaseModel`, query patterns and migrations. |
| [docs/development.md](docs/development.md) | Local setup, environment variables, commands and adding an endpoint. |
| [AGENTS.md](AGENTS.md) | Contributor and AI-agent conventions. |

## Included components

- Slim 4 scaffolding, including PSR-7, PSR-11 and PSR-15 implementation
- Query builder built on [oeltimacreation/php-simplequery](https://github.com/oeltimacreation/php-simplequery) (MySQL, MariaDB and SQLite; no ORM) with a shared `BaseModel`, pagination, keyword search and managed transactions
- Standard JSON response envelope, pagination metadata and `application/problem+json` error output with Monolog logging
- JWT authentication (lcobucci/jwt) with PSR-15 authentication/authorization middleware and a development token command
- Database migration and seeder using [phinx](https://phinx.org/)
- Twig templating engine, used for the bundled Swagger UI page
- OpenAPI 3 specification generated from `#[OA\...]` attributes (swagger-php 6) with a bundled Swagger UI
- DotEnv integration with a cached parse, plus a compiled Twig template cache
- DTO validation, a service layer for business rules and typed exceptions that map to 4xx envelopes
- Health endpoints, `X-Request-ID` request tracing and optional Sentry error reporting
- Background job queue (php-simplequeue) with a worker, admin status endpoints and a combined development runner
- Optional Redis and object storage (AWS S3, DO Spaces, etc.) configuration, including an upload helper
- Quality gates: PHPStan level 5, PHPCS (PSR-12) and a combined `composer check`

## Note on CORS (Cross-Origin Resource Sharing) configuration

CORS is env-driven (`CORS_ENABLED`). By default it is enabled for `development`/`testing`
environments and for `localhost`, which helps local development and the PHP development server.

For production, either:

- let the application handle CORS with `CORS_ENABLED=true` and an exact
  `CORS_ALLOWED_ORIGINS` allowlist, or
- disable it (`CORS_ENABLED=false`) and let the webserver or reverse proxy manage CORS, including
  preflight requests.

Do not do both at once: duplicate `Access-Control-Allow-*` headers confuse browsers.

## Generating OpenAPI/Swagger files

- Run `composer run generate-openapi-docs`
- Files are generated in `public/openapi.json` and `public/openapi.yaml`
- When running the application, access the bundled Swagger UI at http://127.0.0.1:8080/swaggerui

## Quality checks

```bash
composer run check      # composer validate + PHPStan + PHPCS
composer run analyse    # PHPStan level 5
composer run phpcs      # PSR-12 code style
composer run phpcbf     # auto-fix code style
composer run test       # PHPUnit suites (SQLite, no database server required)
```

## Server deployment

When you deploy this application, make sure the webserver is pointing to the `public` folder by
default, or use the virtual host setup for NGINX running on Ubuntu Server 24.04 LTS provided below.
CORS is handled by the application only in development; the proxy can manage it in production (see
above).

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

Protected endpoints require `JWT_SECRET` to be set in the environment; keep
`DISPLAY_ERROR_DETAILS` disabled in production.
