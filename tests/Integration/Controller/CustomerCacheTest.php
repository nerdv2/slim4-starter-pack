<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Tests\TestCase;
use Tests\TestFactory;
use Tests\Traits\RequiresRedis;

final class CustomerCacheTest extends TestCase
{
    use RequiresRedis;

    /** @var array<string, string> */
    private array $authHeaders = [];

    /** @var array<string, string> */
    private array $adminHeaders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireRedis();
        $this->authHeaders = ['Authorization' => $this->authHeader('staff', 2)];
        $this->adminHeaders = ['Authorization' => $this->authHeader('admin', 1)];
    }

    public function testListIsCachedAndWritesInvalidateTheNamespace(): void
    {
        $token = 'cachetest' . bin2hex(random_bytes(4));

        $this->connection()->table('customer')->insert(TestFactory::customer(['name' => $token . ' One']));

        $first = $this->json($this->handle($this->createRequest('GET', '/customer', $this->authHeaders, [], [
            'keywords' => $token,
        ])));
        self::assertSame(1, $first['total_data']);

        // A row inserted directly (bypassing the service) is invisible while the
        // cached list entry is still valid.
        $this->connection()->table('customer')->insert(TestFactory::customer(['name' => $token . ' Two']));
        $cached = $this->json($this->handle($this->createRequest('GET', '/customer', $this->authHeaders, [], [
            'keywords' => $token,
        ])));
        self::assertSame(1, $cached['total_data'], 'The cached list should still report one row.');

        // A write through the API bumps the namespace, so the next read is fresh.
        $created = $this->handle($this->createRequest('POST', '/customer', $this->adminHeaders, [
            'name' => $token . ' Three',
        ]));
        self::assertSame(201, $created->getStatusCode());

        $fresh = $this->json($this->handle($this->createRequest('GET', '/customer', $this->authHeaders, [], [
            'keywords' => $token,
        ])));
        self::assertSame(3, $fresh['total_data']);
    }
}
