<?php

declare(strict_types=1);

$baseDir = __DIR__ . '/../../';

$dotenv = Dotenv\Dotenv::createImmutable($baseDir);
if (file_exists($baseDir . '.env')) {
    $dotenv->load();
    if (!empty($_SERVER['DEFAULT_TIMEZONE'])) {
        date_default_timezone_set($_SERVER['DEFAULT_TIMEZONE']);
    }
}