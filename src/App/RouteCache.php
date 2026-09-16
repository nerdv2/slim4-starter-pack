<?php

/**
 * Compiled FastRoute dispatcher cache.
 *
 * FastRoute compiles every route pattern into a PHP file on the first
 * dispatch; loading the compiled file is significantly cheaper. The cache is
 * disabled while DISPLAY_ERROR_DETAILS is on so local route edits apply
 * immediately. ROUTE_CACHE is a kill switch and ROUTE_CACHE_FILE overrides the
 * location. Compile it at deploy time with `composer run routes:cache`.
 */

declare(strict_types=1);

use Slim\App;

return static function (App $app): void {
    $displayErrorDetails = filter_var(
        $_SERVER['DISPLAY_ERROR_DETAILS'] ?? false,
        FILTER_VALIDATE_BOOLEAN
    );
    $setting = (string) ($_SERVER['ROUTE_CACHE'] ?? '');
    $enabled = $setting === ''
        ? !$displayErrorDetails
        : filter_var($setting, FILTER_VALIDATE_BOOLEAN);

    if (!$enabled) {
        return;
    }

    $cacheFile = (string) ($_SERVER['ROUTE_CACHE_FILE'] ?? __DIR__ . '/../../.cache/routes.cache.php');
    $cacheDir = dirname($cacheFile);

    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }

    // Read-only deployments can ship a precompiled file; otherwise the
    // dispatcher needs a writable directory to compile it on first use.
    if (is_file($cacheFile) ? is_readable($cacheFile) : is_writable($cacheDir)) {
        $app->getRouteCollector()->setCacheFile($cacheFile);
    }
};
