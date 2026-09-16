<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use App\Middleware\RequestIdMiddleware;
use Tests\TestCase;
use Tests\Traits\OverridesEnvironment;

final class HealthTest extends TestCase
{
    use OverridesEnvironment;

    protected function tearDown(): void
    {
        $this->restoreEnvironment();

        parent::tearDown();
    }

    public function testLivenessIsPublic(): void
    {
        $response = $this->handle($this->createRequest('GET', '/health'));
        $payload = $this->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', $payload['status']);
        self::assertArrayHasKey('timestamp', $payload);
    }

    public function testReadinessChecksTheDatabase(): void
    {
        $response = $this->handle($this->createRequest('GET', '/health/ready'));
        $payload = $this->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', $payload['status']);
        self::assertTrue($payload['checks']['database']['healthy']);
    }

    public function testDetailedBypassesTheTokenInTesting(): void
    {
        $response = $this->handle($this->createRequest('GET', '/health/detailed'));
        $payload = $this->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($payload['checks']['database']['healthy']);
        self::assertTrue($payload['checks']['storage']['healthy']);
    }

    public function testDetailedRequiresTheConfiguredToken(): void
    {
        $this->overrideEnvironment(['HEALTHCHECK_TOKEN' => 'secret-token']);

        $missing = $this->handle($this->createRequest('GET', '/health/detailed'));
        self::assertSame(401, $missing->getStatusCode());

        $wrong = $this->handle($this->createRequest(
            'GET',
            '/health/detailed',
            ['X-Health-Token' => 'not-the-token']
        ));
        self::assertSame(401, $wrong->getStatusCode());

        $authorized = $this->handle($this->createRequest(
            'GET',
            '/health/detailed',
            ['X-Health-Token' => 'secret-token']
        ));
        self::assertSame(200, $authorized->getStatusCode());
    }

    public function testDetailedIsUnavailableInProductionWithoutToken(): void
    {
        $this->overrideEnvironment(['APP_ENVIRONMENT' => 'production', 'HEALTHCHECK_TOKEN' => '']);

        $response = $this->handle($this->createRequest('GET', '/health/detailed'));

        self::assertSame(503, $response->getStatusCode());
    }

    public function testRequestIdIsGeneratedAndEchoed(): void
    {
        $response = $this->handle($this->createRequest('GET', '/health'));

        self::assertMatchesRegularExpression(
            '/^\d{8}-[a-f0-9]{8}-[a-f0-9]{4}$/',
            $response->getHeaderLine(RequestIdMiddleware::HEADER)
        );
    }

    public function testIncomingRequestIdIsEchoedOnErrorResponses(): void
    {
        $response = $this->handle($this->createRequest(
            'GET',
            '/does-not-exist',
            [RequestIdMiddleware::HEADER => 'trace-42']
        ));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('trace-42', $response->getHeaderLine(RequestIdMiddleware::HEADER));
    }
}
