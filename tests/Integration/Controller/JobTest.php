<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Oeltima\SimpleQueue\JobDispatcher;
use Tests\TestCase;

final class JobTest extends TestCase
{
    /** @var array<string, string> */
    private array $headers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->headers = ['Authorization' => $this->authHeader('staff', 2)];
    }

    public function testStatusRequiresAuthentication(): void
    {
        self::assertSame(401, $this->handle($this->createRequest('GET', '/jobs/1'))->getStatusCode());
    }

    public function testUnknownJobReturnsNotFound(): void
    {
        self::assertSame(
            404,
            $this->handle($this->createRequest('GET', '/jobs/99999', $this->headers))->getStatusCode()
        );
    }

    public function testJobStatusExposesProgressWithoutThePayload(): void
    {
        /** @var JobDispatcher $dispatcher */
        $dispatcher = $this->app->getContainer()->get('jobDispatcher');
        $jobId = $dispatcher->dispatch('customer.export', [
            'keywords' => 'private-search-term',
            'status' => null,
            'requested_by' => 2,
        ]);

        $response = $this->handle($this->createRequest('GET', '/jobs/' . $jobId, $this->headers));
        $data = $this->json($response)['data'];

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($jobId, $data['id']);
        self::assertSame('customer.export', $data['type']);
        self::assertSame('pending', $data['status']);
        self::assertArrayNotHasKey('payload', $data);
        self::assertArrayNotHasKey('download_url', $data, 'Not completed yet, so no download link.');
    }
}
