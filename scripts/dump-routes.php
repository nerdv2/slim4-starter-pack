#!/usr/bin/env php
<?php

/**
 * Compile the Slim/FastRoute dispatcher cache for a deployment.
 *
 * Usage: php scripts/dump-routes.php
 * Route changes require re-running this script (or removing the cache file).
 */

declare(strict_types=1);

$baseDir = dirname(__DIR__);

require $baseDir . '/vendor/autoload.php';

$app = require $baseDir . '/src/App/App.php';

$routeCacheFile = $app->getRouteCollector()->getCacheFile();
if ($routeCacheFile === null) {
    fwrite(STDERR, "route cache disabled (ROUTE_CACHE / DISPLAY_ERROR_DETAILS); nothing to do\n");
    exit(0);
}

// Force the dispatcher to compile and write the cache; the matched handler is
// never invoked because this route cannot exist.
$dispatcher = new Slim\Routing\Dispatcher($app->getRouteCollector());
$dispatcher->dispatch('GET', '/__route_cache_warmup__');

if (!is_file($routeCacheFile)) {
    fwrite(STDERR, sprintf("route cache was not written: %s\n", $routeCacheFile));
    exit(1);
}

printf("route cache: %s (%d bytes)\n", $routeCacheFile, (int) filesize($routeCacheFile));
