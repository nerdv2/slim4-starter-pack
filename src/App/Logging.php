<?php

declare(strict_types=1);

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Pimple\Container;

/** @var Container $container */
$container['logger'] = static function (): Logger {
    $logPath = __DIR__ . '/../../storage/log/';
    if (!is_dir($logPath)) {
        @mkdir($logPath, 0775, true);
    }

    $logger = new Logger('app');
    $logger->pushHandler(new StreamHandler($logPath . 'error.log', Level::Error));

    return $logger;
};
