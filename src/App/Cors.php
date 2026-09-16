<?php

/**
 * CORS response headers.
 *
 * The OPTIONS preflight route lives in src/App/routes/core.php so the route
 * table stays identical whether CORS is enabled or not (the compiled route
 * cache is built without request environment).
 */

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;

return function (App $app): void {
    $app->add(function (Request $request, RequestHandlerInterface $handler): Response {
        $response = $handler->handle($request);

        $origin = $request->getHeaderLine('Origin');
        $allowedOrigins = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($_SERVER['CORS_ALLOWED_ORIGINS'] ?? ''))
        )));
        $originAllowed = $origin !== '' && in_array($origin, $allowedOrigins, true);

        // With an explicit allowlist, never emit CORS headers for other origins,
        // but keep the response cache-aware of the origin dimension.
        if ($allowedOrigins !== [] && !$originAllowed) {
            return $response->withHeader('Vary', 'Origin');
        }

        $allowedHeaders = (string) ($_SERVER['CORS_ALLOWED_HEADERS']
            ?? 'X-Requested-With, X-Client-Type, Content-Type, Accept, Origin, Authorization');
        $allowedMethods = (string) ($_SERVER['CORS_ALLOWED_METHODS']
            ?? 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        $maxAge = (string) ($_SERVER['CORS_MAX_AGE'] ?? '86400');
        $withCredentials = $originAllowed
            && filter_var($_SERVER['CORS_ALLOW_CREDENTIALS'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $originAllowed ? $origin : '*')
            ->withHeader('Access-Control-Allow-Headers', $allowedHeaders)
            ->withHeader('Access-Control-Allow-Methods', $allowedMethods)
            ->withHeader('Access-Control-Max-Age', $maxAge);

        if ($allowedOrigins !== []) {
            $response = $response->withHeader('Vary', 'Origin');
        }
        if ($withCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    });
};
