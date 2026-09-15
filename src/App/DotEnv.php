<?php

declare(strict_types=1);

use Dotenv\Dotenv;

$baseDir = __DIR__ . '/../../';
$envFile = $baseDir . '.env';

// Load .env into $_ENV/$_SERVER when present. The immutable loader never
// overwrites existing environment variables, so deployments can inject
// configuration without a .env file.
if (is_readable($envFile)) {
    Dotenv::createImmutable($baseDir)->safeLoad();
} elseif (file_exists($envFile)) {
    throw new RuntimeException(sprintf('Environment file is not readable: %s', $envFile));
}

// Deterministic timezone: DEFAULT_TIMEZONE when configured, UTC otherwise.
$timezone = $_SERVER['DEFAULT_TIMEZONE'] ?? $_ENV['DEFAULT_TIMEZONE'] ?? getenv('DEFAULT_TIMEZONE');
if (!is_string($timezone) || $timezone === '') {
    $timezone = 'UTC';
}

date_default_timezone_set($timezone);
