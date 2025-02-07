<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/DotEnv.php';
$app = require __DIR__ . '/Container.php';
$customErrorHandler = require __DIR__ . '/ErrorHandler.php';
(require __DIR__ . '/Middlewares.php')($app, $customErrorHandler);
if(in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1', 'localhost'))){
    (require __DIR__ . '/Cors.php')($app);
}
(require __DIR__ . '/Database.php');
(require __DIR__ . '/Routes.php');
(require __DIR__ . '/NotFound.php')($app);

return $app;
