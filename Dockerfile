# Production image: PHP 8.3 + Nginx from shinsenter/php.
#
# The base image is pinned by digest for reproducible builds. Refresh it with:
#   docker pull shinsenter/php:8.3-fpm-nginx-alpine
#   docker inspect --format='{{index .RepoDigests 0}}' shinsenter/php:8.3-fpm-nginx-alpine
ARG BASE_IMAGE=shinsenter/php:8.3-fpm-nginx-alpine@sha256:027e125b77e5f2c866d1f28ca8ca542215fc8fd0af9189690aff8c21e04da01e
FROM ${BASE_IMAGE}

# Serve the Slim front controller from public/.
ENV DOCUMENT_ROOT=public

# Runtime limits: no error display, sane upload sizes and OPcache for production.
ENV PHP_DISPLAY_ERRORS=0 \
    PHP_LOG_ERRORS=1 \
    PHP_MEMORY_LIMIT=512M \
    PHP_POST_MAX_SIZE=12M \
    PHP_UPLOAD_MAX_FILESIZE=10M \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0 \
    PHP_PM=ondemand \
    PHP_PM_MAX_CHILDREN=5 \
    PHP_PM_MAX_REQUESTS=500 \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# Install production dependencies first so this layer is cached between builds.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
    --optimize-autoloader --classmap-authoritative

# HTTP-only Nginx configuration; TLS terminates at the ingress/reverse proxy.
COPY --chmod=644 deploy/nginx/00-default.conf /etc/nginx/sites-enabled/00-default.conf
COPY --chmod=644 deploy/nginx/10-upload-protection.conf /etc/nginx/custom.d/10-upload-protection.conf
RUN rm -rf /etc/ssl/site /etc/nginx/custom.d/00-ext-http3.conf /etc/nginx/snippets/snakeoil.conf

# Application source (vendor/ is excluded by .dockerignore).
COPY --chown=$APP_USER:$APP_GROUP . .

# Rebuild the authoritative classmap now that src/ exists; the dependency layer
# above only had composer.json/composer.lock available.
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction

# Precompile the FastRoute dispatcher cache; route changes require a rebuild.
RUN php scripts/dump-routes.php

# Writable runtime directories: env cache, Twig cache, logs, uploads, route cache and worker locks.
RUN mkdir -p storage/cache storage/log storage/locks public/uploads .cache && \
    chown -R "${APP_USER}:${APP_GROUP}" storage public/uploads .cache && \
    find storage public/uploads -type d -exec chmod 0750 {} + && \
    find storage public/uploads -type f -exec chmod 0640 {} + && \
    chmod +x bin/background-worker bin/dev-server bin/generate-token

ARG IMAGE_VERSION=dev
ARG VCS_REF=unknown

ENV APP_VERSION="${IMAGE_VERSION}"

LABEL org.opencontainers.image.title="Slim 4 Starter Pack" \
      org.opencontainers.image.version="${IMAGE_VERSION}" \
      org.opencontainers.image.revision="${VCS_REF}" \
      org.opencontainers.image.base.name="shinsenter/php:8.3-fpm-nginx-alpine"

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl --fail --silent --show-error --max-time 5 http://localhost/health || exit 1
