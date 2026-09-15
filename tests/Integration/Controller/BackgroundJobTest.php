<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Tests\TestCase;

final class BackgroundJobTest extends TestCase
{
    /** @var array<string, string> */
    private array $authHeaders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->authHeaders = ['Authorization' => $this->authHeader()];
    }

    public function testDispatchRequiresAuthentication(): void
    {
        $response = $this->handle($this->createRequest('POST', '/admin/background-jobs/example'));

        self::assertSame(401, $response->getStatusCode());
    }

    public function testAdminCanDispatchAndInspectAJob(): void
    {
        $dispatch = $this->handle($this->createRequest(
            'POST',
            '/admin/background-jobs/example',
            $this->authHeaders,
            ['message' => 'From the API']
        ));
        $payload = $this->json($dispatch);

        self::assertSame(200, $dispatch->getStatusCode());
        self::assertTrue($payload['status']);
        self::assertIsInt($payload['data']['job_id']);
        self::assertSame('pending', $payload['data']['status']);

        $status = $this->handle($this->createRequest(
            'GET',
            '/admin/background-jobs/' . $payload['data']['job_id'],
            $this->authHeaders
        ));
        $statusPayload = $this->json($status);

        self::assertSame(200, $status->getStatusCode());
        self::assertSame('example.hello', $statusPayload['data']['type']);
        self::assertSame('pending', $statusPayload['data']['status']);
    }

    public function testUnknownJobReturnsNotFound(): void
    {
        $response = $this->handle($this->createRequest(
            'GET',
            '/admin/background-jobs/999',
            $this->authHeaders
        ));

        self::assertSame(404, $response->getStatusCode());
    }
}
