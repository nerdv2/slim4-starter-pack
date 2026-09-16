# Deployment

How to build, run and operate the application.

## Container Image

`Dockerfile` builds a self-contained PHP 8.3 + Nginx image on
[shinsenter/php](https://github.com/shinsenter/php), pinned by digest for reproducible builds:

- document root `public/` (Slim front controller);
- production Composer install (`--no-dev --optimize-autoloader --classmap-authoritative`) at build
  time;
- `PHP_DISPLAY_ERRORS=0`, upload limits, OPcache (`validate_timestamps=0`), FPM process limits;
- `storage/{cache,log,locks}` and `public/uploads` created and owned by the application user;
- HTTP-only Nginx config (`deploy/nginx/00-default.conf`) with executable-upload protection
  (`deploy/nginx/10-upload-protection.conf`); terminate TLS at the ingress or reverse proxy;
- a container `HEALTHCHECK` against `GET /health`.

Refresh the base digest when needed:

```bash
docker pull shinsenter/php:8.3-fpm-nginx-alpine
docker inspect --format='{{index .RepoDigests 0}}' shinsenter/php:8.3-fpm-nginx-alpine
```

### Build

```bash
docker build \
  --build-arg IMAGE_VERSION="$(git describe --tags --always)" \
  --build-arg VCS_REF="$(git rev-parse HEAD)" \
  --tag slim4-starter-pack:latest .
```

## Running the API

```bash
docker volume create slim4-storage

docker run --detach --name slim4-api --publish 8080:80 \
  --mount source=slim4-storage,target=/var/www/html/storage \
  --env APP_ENVIRONMENT=production \
  --env APP_NAME="Customer DB" \
  --env APP_BASE_URL="https://api.example.com" \
  --env DEFAULT_TIMEZONE="Asia/Jakarta" \
  --env APP_KEY="change-me" \
  --env JWT_SECRET="$(openssl rand -hex 32)" \
  --env HEALTHCHECK_TOKEN="$(openssl rand -hex 16)" \
  --env DB_DRIVER=mysql \
  --env DB_HOST=mysql --env DB_PORT=3306 \
  --env DB_NAME=starter --env DB_USER=starter --env DB_PASS=secret \
  --env QUEUE_DRIVER=auto \
  slim4-starter-pack:latest
```

Mount `storage/` when you want the env cache, Twig cache, worker locks and logs to survive
container replacement (a named volume inherits the image directory ownership, so `www-data` can
write to it).

Key environment variables are documented in [Development](development.md#environment-variables) and
[Background Jobs](background-jobs.md#configuration). In production always set `JWT_SECRET`,
`HEALTHCHECK_TOKEN` and `DISPLAY_ERROR_DETAILS=false` (the default).

## Database Migrations

Apply migrations before (or as part of) a release. The Phinx configuration mirrors the application
driver (`DB_DRIVER`), so the same command works against MySQL, MariaDB or SQLite:

```bash
docker exec --user www-data slim4-api php vendor/bin/phinx migrate
docker exec --user www-data slim4-api php vendor/bin/phinx rollback     # one step
docker exec --user www-data slim4-api php vendor/bin/phinx seed:run
```

Run migrations from a one-off container in orchestrated deployments (Kubernetes job, Nomad
prestart task, Compose `depends_on` init service) before starting the new web containers. Running
as `www-data` keeps SQLite files writable by the application user.

## Route Cache

The container image precompiles the FastRoute dispatcher cache during the build, so production
containers serve routes from the compiled file. On VM deployments run it after every release:

```bash
composer run routes:cache
```

- The cache is enabled unless `DISPLAY_ERROR_DETAILS=true`; `ROUTE_CACHE=false` is a kill switch
  and `ROUTE_CACHE_FILE` overrides the location (default `.cache/routes.cache.php`).
- Regenerate whenever `src/App/routes/` changes: a stale cache can serve the wrong handler.
- Route definitions must stay independent of the request environment; environment-specific
  behaviour belongs in middleware.

## Queue Worker

The image contains the application; run the worker as a separate container from the same image,
overriding the entrypoint:

```bash
docker run --detach --name slim4-worker --restart unless-stopped \
  --user www-data:www-data \
  --entrypoint php \
  --mount source=slim4-storage,target=/var/www/html/storage \
  --env APP_ENVIRONMENT=production \
  --env DB_DRIVER=mysql --env DB_HOST=mysql --env DB_NAME=starter \
  --env DB_USER=starter --env DB_PASS=secret \
  --env QUEUE_DRIVER=auto \
  slim4-starter-pack:latest bin/background-worker
```

- Run the worker as `www-data` and mount the same `storage` volume as the API container: the
  worker takes its singleton lock under `storage/locks`, and the package refuses lock directories
  that are not owned by the current user.
- The worker takes a queue name argument (`bin/background-worker emails`); the default queue is
  `default`.
- Scale workers by running more containers with different queues; each worker takes its own lock in
  `storage/locks`.
- Worker logs go to stdout/stderr and `storage/log/worker.log`; mount `storage/` if you want logs
  to survive container replacement.
- Apply the same migrations before starting workers so the `background_job` table exists.

## Optional Redis

Set `REDIS_SERVER_HOST`, `REDIS_SERVER_PORT` and optionally `REDIS_SERVER_PASSWORD`,
`REDIS_SERVER_DATABASE` and `REDIS_SERVER_PREFIX` to enable the response cache. Leave them empty
and the application serves every request from the database. Use a distinct
`REDIS_SERVER_PREFIX` per environment when a Redis instance is shared. See [Caching](caching.md).

### Docker Compose Example

```yaml
services:
  api:
    build: .
    ports:
      - "8080:80"
    environment:
      APP_ENVIRONMENT: production
      APP_BASE_URL: http://localhost:8080
      JWT_SECRET: change-me-to-a-32-byte-secret
      HEALTHCHECK_TOKEN: change-me
      DB_DRIVER: mysql
      DB_HOST: mysql
      DB_NAME: starter
      DB_USER: starter
      DB_PASS: secret
      REDIS_SERVER_HOST: redis
      REDIS_SERVER_PORT: 6379
    depends_on:
      mysql:
        condition: service_healthy
    volumes:
      - storage:/var/www/html/storage

  worker:
    build: .
    entrypoint: php
    command: bin/background-worker
    user: www-data
    environment:
      APP_ENVIRONMENT: production
      JWT_SECRET: change-me-to-a-32-byte-secret
      DB_DRIVER: mysql
      DB_HOST: mysql
      DB_NAME: starter
      DB_USER: starter
      DB_PASS: secret
      REDIS_SERVER_HOST: redis
      REDIS_SERVER_PORT: 6379
    depends_on:
      mysql:
        condition: service_healthy
    volumes:
      - storage:/var/www/html/storage

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: starter
      MYSQL_USER: starter
      MYSQL_PASSWORD: secret
      MYSQL_ROOT_PASSWORD: root-secret
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5
    volumes:
      - mysql:/var/lib/mysql

  redis:
    image: redis:7-alpine
    volumes:
      - redis:/data

volumes:
  mysql:
  redis:
  storage:
```

Run `docker compose run --rm --user www-data api php vendor/bin/phinx migrate` once before starting
the services.

## CI

`.github/workflows/ci.yml` runs the quality gates and tests, then builds the image and smoke-tests
it: the container starts, `GET /health` answers, migrations apply inside the container,
`GET /health/ready` probes the database and the Composer autoloader loads.

## VM / Reverse Proxy Deployment

The image is the recommended path, but the application also runs on a plain PHP-FPM host. Point the
virtual host document root at `public/`, manage CORS at the proxy (keep `CORS_ENABLED=false` in
production), and use the nginx example in the [README](../README.md#server-deployment). Keep
`storage/` writable and run `php bin/background-worker` under a process supervisor
(systemd, supervisor, s6) alongside PHP-FPM.

## Related Docs

- [Development](development.md) — environment variables and commands.
- [Background Jobs](background-jobs.md) — queue configuration and worker behaviour.
- [Architecture](architecture.md) — bootstrap sequence and health endpoints.
