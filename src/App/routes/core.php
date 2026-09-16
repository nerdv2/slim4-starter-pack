<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

return static function (App $app): void {
    $app->get('/', 'App\Controller\Hello:getStatus')->setName('main');
    $app->get('/status', 'App\Controller\Hello:getStatusAPI')->setName('api.status');
    $app->get('/swaggerui', 'App\Controller\Hello:openSwaggerUI')->setName('swagger_ui');

    // Preflight catch-all, always registered so the route table does not depend
    // on CORS_ENABLED (the compiled route cache is built without request env).
    // The CORS middleware adds the response headers when CORS is enabled.
    $app->options('/{routes:.+}', function (
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        return $response;
    });
};
