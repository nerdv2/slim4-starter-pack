<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Middleware\RequestIdMiddleware;
use ArrayObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

final class RequestIdMiddlewareTest extends TestCase
{
    public function testGeneratedIdIsEchoedWithTheExpectedFormat(): void
    {
        $response = $this->process($this->createRequest());

        self::assertMatchesRegularExpression(
            '/^\d{8}-[a-f0-9]{8}-[a-f0-9]{4}$/',
            $response->getHeaderLine(RequestIdMiddleware::HEADER)
        );
    }

    public function testIncomingIdIsReusedAndExposedAsAnAttribute(): void
    {
        /** @var ArrayObject<string, mixed> $captured */
        $captured = new ArrayObject();
        $handler = new class ($captured) implements RequestHandlerInterface {
            public function __construct(private readonly ArrayObject $captured)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->captured['request_id'] = $request->getAttribute(RequestIdMiddleware::ATTRIBUTE);

                return new Response();
            }
        };

        $response = (new RequestIdMiddleware())->process(
            $this->createRequest()->withHeader(RequestIdMiddleware::HEADER, 'trace-42'),
            $handler
        );

        self::assertSame('trace-42', $response->getHeaderLine(RequestIdMiddleware::HEADER));
        self::assertSame('trace-42', $captured['request_id']);
    }

    private function process(ServerRequestInterface $request): ResponseInterface
    {
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response();
            }
        };

        return (new RequestIdMiddleware())->process($request, $handler);
    }

    private function createRequest(): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest('GET', '/');
    }
}
