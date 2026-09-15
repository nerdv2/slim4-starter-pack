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

final class HealthTokenMiddleware implements MiddlewareInterface
{
    public const string HEADER = 'X-Health-Token';

    /**
     * Protect detailed health diagnostics with HEALTHCHECK_TOKEN. Local
     * development and testing stay usable without a token; every deployed
     * environment must configure one.
     */
    #[\Override]
    public function process(Request $request, RequestHandler $handler): Response
    {
        $configuredToken = $this->environment('HEALTHCHECK_TOKEN');
        $environment = strtolower($this->environment('APP_ENVIRONMENT') ?: 'production');

        if ($configuredToken === '' && in_array($environment, ['development', 'testing'], true)) {
            return $handler->handle($request);
        }

        if ($configuredToken === '') {
            return JsonResponse::error(
                new SlimResponse(),
                'Health diagnostics are not configured.',
                [],
                [],
                HttpStatus::SERVICE_UNAVAILABLE
            );
        }

        $providedToken = $request->getHeaderLine(self::HEADER);
        if ($providedToken === '' || !hash_equals($configuredToken, $providedToken)) {
            return JsonResponse::error(
                new SlimResponse(),
                'Health diagnostic authorization required.',
                [],
                [],
                HttpStatus::UNAUTHORIZED
            );
        }

        return $handler->handle($request);
    }

    private function environment(string $name): string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return is_string($value) ? $value : '';
    }
}
