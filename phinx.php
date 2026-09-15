<?php

declare(strict_types=1);

// Phinx configuration.
//
// Values are read from process environment variables first (deployments), then
// from the .env file next to this file (local development), then from the
// defaults below. Paths are absolute so migrations run from any working
// directory.

$envFile = __DIR__ . '/.env';
$fileEnv = is_readable($envFile) ? parse_ini_file($envFile, false, INI_SCANNER_RAW) : false;
if ($fileEnv === false) {
    $fileEnv = [];
}

$env = static function (string $key, string $default = '') use ($fileEnv): string {
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $fileEnv[$key] ?? $default;
    }

    return (string) $value;
};

return [
    'paths' => [
        'migrations' => __DIR__ . '/db/migrations',
        'seeds' => __DIR__ . '/db/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'mysql',
            'host' => $env('DB_HOST', '127.0.0.1'),
            'name' => $env('DB_NAME'),
            'user' => $env('DB_USER'),
            'pass' => $env('DB_PASS'),
            'port' => $env('DB_PORT', '3306'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],
    'version_order' => 'creation',
];
