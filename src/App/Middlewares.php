<?php

declare(strict_types=1);

use App\Middleware\RequestIdMiddleware;
use Slim\App;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Selective\BasePath\BasePathMiddleware;

return function (App $app, $customErrorHandler): void {
    $app->addRoutingMiddleware();

    // BasePath only matters when the app runs under a sub-directory.
    if (!empty($_SERVER['SLIM_BASH_PATH'])) {
        $app->add(new BasePathMiddleware($app));
    }

    $app->addBodyParsingMiddleware();
    $displayError = filter_var(
        $_SERVER['DISPLAY_ERROR_DETAILS'] ?? false,
        FILTER_VALIDATE_BOOLEAN
    );
    $errorMiddleware = $app->addErrorMiddleware($displayError, true, $displayError);
    $errorMiddleware->setDefaultErrorHandler($customErrorHandler);

    // Registered after the error middleware so the request id is available to
    // the error handler and echoed on error responses as well.
    $app->add(new RequestIdMiddleware());

    // Create Twig — compiled templates are cached in storage; development
    // re-checks template mtimes so edits apply immediately.
    $twigCacheDir = __DIR__ . '/../../storage/cache/twig';
    $twigCache = is_dir($twigCacheDir) || @mkdir($twigCacheDir, 0775, true)
        ? $twigCacheDir
        : false;
    $twig = Twig::create(__DIR__ . '/../View/', [
        'cache' => $twigCache,
        'auto_reload' => $displayError,
    ]);

    // Add Twig-View Middleware
    $app->add(TwigMiddleware::create($app, $twig));
};
