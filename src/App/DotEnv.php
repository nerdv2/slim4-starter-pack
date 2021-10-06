<?php

declare(strict_types=1);

$baseDir = __DIR__ . '/../../';

$dotenv = Dotenv\Dotenv::createImmutable($baseDir);
if (file_exists($baseDir . '.env')) {
    $dotenv->load();
}