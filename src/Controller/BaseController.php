<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exceptions\AppException;
use App\Exceptions\ValidationException;
use App\Helper\JsonResponse;
use Oeltima\SimpleQuery\Connection;
use Pimple\Psr11\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

abstract class BaseController
{
    public function __construct(
        protected readonly Container $container
    ) {
    }

    /**
     * Primary connection for writes and default reads.
     */
    protected function db(): Connection
    {
        return $this->container->get('db');
    }

    /**
     * Read replica; falls back to the primary when not configured.
     */
    protected function dbRead(): Connection
    {
        return $this->container->get('db_read');
    }

    /**
     * Authenticated user set by AuthenticationMiddleware, or null on public routes.
     */
    protected function user(Request $request): ?\stdClass
    {
        $user = $request->getAttribute('user');

        return $user instanceof \stdClass ? $user : null;
    }

    /**
     * Convert a typed application exception into the standard error envelope.
     */
    protected function errorResponse(Response $response, AppException $exception): Response
    {
        $data = $exception instanceof ValidationException ? $exception->errors() : [];

        return JsonResponse::error($response, $exception->getMessage(), $data, [], $exception->httpStatus());
    }
}
