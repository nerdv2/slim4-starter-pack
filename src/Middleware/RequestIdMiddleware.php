<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

final class RequestIdMiddleware implements MiddlewareInterface
{
    public const string HEADER = 'X-Request-ID';
    public const string ATTRIBUTE = 'request_id';

    /**
     * Accept an incoming X-Request-ID or generate one, expose it as a request
     * attribute and echo it on every response (including error responses when
     * the middleware is registered outside the error middleware).
     */
    #[\Override]
    public function process(Request $request, RequestHandler $handler): Response
    {
        $requestId = trim($request->getHeaderLine(self::HEADER));
        if ($requestId === '') {
            $requestId = $this->generate();
        }

        $response = $handler->handle($request->withAttribute(self::ATTRIBUTE, $requestId));

        return $response->withHeader(self::HEADER, $requestId);
    }

    private function generate(): string
    {
        return sprintf(
            '%s-%s-%s',
            date('Ymd'),
            substr(bin2hex(random_bytes(4)), 0, 8),
            substr(bin2hex(random_bytes(2)), 0, 4)
        );
    }
}
