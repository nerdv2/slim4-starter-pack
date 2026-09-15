<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

$baseDir = __DIR__ . '/../../';
$envFile = $baseDir . '.env';
$cacheFile = $baseDir . 'storage/cache/env.cache.json';

// Parsing .env on every request costs ~120 us. The parsed values are cached
// next to the log/cache files and reused while .env is unchanged (mtime + size).
// JSON is used instead of a PHP include so the cache stays correct when OPcache
// runs with validate_timestamps=0. Integration guide: docs/development.md.
if (is_readable($envFile)) {
    $dotenv = new Dotenv();
    $loaded = false;

    if (is_readable($cacheFile)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (
            is_array($cached)
            && ($cached['mtime'] ?? null) === filemtime($envFile)
            && ($cached['size'] ?? null) === filesize($envFile)
            && is_array($cached['values'] ?? null)
        ) {
            $dotenv->populate($cached['values']);
            $loaded = true;
        }
    }

    if (!$loaded) {
        $values = $dotenv->parse((string) file_get_contents($envFile), $envFile);
        $dotenv->populate($values);

        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        if (is_writable($cacheDir)) {
            try {
                $payload = json_encode([
                    'mtime' => filemtime($envFile),
                    'size' => filesize($envFile),
                    'values' => $values,
                ], JSON_THROW_ON_ERROR);

                // Atomic replace: readers only ever see a complete file. The
                // cache holds secrets, so it is written owner-only.
                $tmp = $cacheFile . '.' . getmypid() . '.tmp';
                if (file_put_contents($tmp, $payload) !== false) {
                    @chmod($tmp, 0600);
                    @rename($tmp, $cacheFile);
                }
            } catch (JsonException) {
                // Values are not UTF-8 encodable; keep parsing .env per request.
            }
        }
    }
} elseif (file_exists($envFile)) {
    throw new RuntimeException(sprintf('Environment file is not readable: %s', $envFile));
}

// Deterministic timezone: DEFAULT_TIMEZONE when configured, UTC otherwise.
$timezone = $_SERVER['DEFAULT_TIMEZONE'] ?? $_ENV['DEFAULT_TIMEZONE'] ?? getenv('DEFAULT_TIMEZONE');
if (!is_string($timezone) || $timezone === '') {
    $timezone = 'UTC';
}

date_default_timezone_set($timezone);
