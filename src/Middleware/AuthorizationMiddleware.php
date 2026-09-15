<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Constants\HttpStatus;
use App\Helper\JsonResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

final class AuthorizationMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $allowedTypes Empty means "any authenticated user".
     */
    public function __construct(
        private readonly array $allowedTypes = []
    ) {
    }

    /**
     * Check the user type against the allowlist. Runs after
     * AuthenticationMiddleware (add it first so it wraps that middleware).
     */
    #[\Override]
    public function process(Request $request, RequestHandler $handler): Response
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof \stdClass) {
            return JsonResponse::error(
                new SlimResponse(),
                'Authentication required.',
                [],
                [],
                HttpStatus::UNAUTHORIZED
            );
        }

        if ($this->allowedTypes !== [] && !in_array($user->type ?? null, $this->allowedTypes, true)) {
            return JsonResponse::error(
                new SlimResponse(),
                'Access denied.',
                [],
                [],
                HttpStatus::FORBIDDEN
            );
        }

        return $handler->handle($request);
    }
}
