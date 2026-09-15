<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/DotEnv.php';
$app = require __DIR__ . '/Container.php';
$customErrorHandler = require __DIR__ . '/ErrorHandler.php';
(require __DIR__ . '/Middlewares.php')($app, $customErrorHandler);

// CORS is a development aid; production CORS is handled by the edge/proxy.
// CORS_ENABLED wins when set; otherwise it follows the environment.
$corsSetting = $_SERVER['CORS_ENABLED'] ?? null;
$appEnvironment = (string) ($_SERVER['APP_ENVIRONMENT'] ?? 'production');
$corsEnabled = ($corsSetting === null || $corsSetting === '')
    ? in_array($appEnvironment, ['development', 'testing'], true)
        || ($_SERVER['SERVER_NAME'] ?? '') === 'localhost'
    : filter_var($corsSetting, FILTER_VALIDATE_BOOLEAN);

if ($corsEnabled) {
    (require __DIR__ . '/Cors.php')($app);
}

(require __DIR__ . '/Database.php');
(require __DIR__ . '/Routes.php');
(require __DIR__ . '/NotFound.php')($app);

return $app;
