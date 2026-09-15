<?php

declare(strict_types=1);

namespace App\Controller;

use Oeltima\SimpleQuery\Connection;
use Pimple\Psr11\Container;
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
}
