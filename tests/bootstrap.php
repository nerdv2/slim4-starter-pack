<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

// Test runs always use a throwaway SQLite database, whatever the local .env
// says. The values are placed in $_ENV/$_SERVER before DotEnv runs so the
// immutable loader cannot overwrite them.
$database = dirname(__DIR__) . '/storage/test_database.sqlite';

foreach (['DB_DRIVER' => 'sqlite', 'DB_NAME' => $database] as $key => $value) {
    $_ENV[$key] = $_SERVER[$key] = $value;
    putenv($key . '=' . $value);
}

date_default_timezone_set('UTC');

$storage = dirname($database);
if (!is_dir($storage)) {
    mkdir($storage, 0775, true);
}

// Fresh schema for every run; the base TestCase applies the migrations once.
if (file_exists($database)) {
    unlink($database);
}
