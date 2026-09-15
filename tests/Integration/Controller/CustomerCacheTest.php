<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Tests\TestCase;
use Tests\Traits\RequiresRedis;

final class CustomerCacheTest extends TestCase
{
    use RequiresRedis;

    /** @var array<string, string> */
    private array $authHeaders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireRedis();
        $this->authHeaders = ['Authorization' => $this->authHeader()];
    }

    public function testListIsCachedAndWritesInvalidateTheNamespace(): void
    {
        $token = 'cachetest' . bin2hex(random_bytes(4));

        $this->connection()->table('customer')->insert([
            'name' => $token . ' One',
            'created' => date('Y-m-d H:i:s'),
        ]);

        $first = $this->json($this->handle($this->createRequest('GET', '/customer', [], [], [
            'keywords' => $token,
        ])));
        self::assertSame(1, $first['total_data']);

        // A row inserted directly (bypassing the service) is invisible while the
        // cached list entry is still valid.
        $this->connection()->table('customer')->insert([
            'name' => $token . ' Two',
            'created' => date('Y-m-d H:i:s'),
        ]);
        $cached = $this->json($this->handle($this->createRequest('GET', '/customer', [], [], [
            'keywords' => $token,
        ])));
        self::assertSame(1, $cached['total_data'], 'The cached list should still report one row.');

        // A write through the API bumps the namespace, so the next read is fresh.
        $created = $this->handle($this->createRequest(
            'POST',
            '/customer/add',
            $this->authHeaders,
            ['name' => $token . ' Three']
        ));
        self::assertSame(200, $created->getStatusCode());

        $fresh = $this->json($this->handle($this->createRequest('GET', '/customer', [], [], [
            'keywords' => $token,
        ])));
        self::assertSame(3, $fresh['total_data']);
    }
}
