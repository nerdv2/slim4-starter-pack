<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Constants\HttpStatus;
use App\Helper\JsonResponse;
use App\Helper\JwtHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

final class AuthenticationMiddleware implements MiddlewareInterface
{
    /**
     * Validate the Authorization header and expose the authenticated user as
     * the `user` request attribute. Answers 401 with the standard envelope when
     * the token is missing or invalid.
     */
    #[\Override]
    public function process(Request $request, RequestHandler $handler): Response
    {
        $user = JwtHelper::requestUser($request);
        if ($user === null) {
            return JsonResponse::error(
                new SlimResponse(),
                'Authorization token is missing or invalid.',
                [],
                [],
                HttpStatus::UNAUTHORIZED
            );
        }

        return $handler->handle($request->withAttribute('user', $user));
    }
}
