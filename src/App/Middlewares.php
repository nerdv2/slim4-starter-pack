<?php

declare(strict_types=1);

use Slim\App;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Selective\BasePath\BasePathMiddleware;

return function (App $app, $customErrorHandler): void {
    $app->addRoutingMiddleware();
    $app->add(new BasePathMiddleware($app));

    $app->addBodyParsingMiddleware();
    $displayError = filter_var(
        $_SERVER['DISPLAY_ERROR_DETAILS'] ?? false,
        FILTER_VALIDATE_BOOLEAN
    );
    $errorMiddleware = $app->addErrorMiddleware($displayError, true, true);
    $errorMiddleware->setDefaultErrorHandler($customErrorHandler);

    // Create Twig
    $twig = Twig::create(__DIR__ . '/../View/', ['cache' => false]);

    // Add Twig-View Middleware
    $app->add(TwigMiddleware::create($app, $twig));
};
