<?php

declare(strict_types=1);

namespace Tests\Integration\Cache;

use App\Helper\CacheRedis;
use PHPUnit\Framework\TestCase;
use Tests\Traits\RequiresRedis;

final class CacheRedisTest extends TestCase
{
    use RequiresRedis;

    private CacheRedis $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireRedis();
        $this->cache = new CacheRedis();
    }

    public function testJsonRoundTripAndDelete(): void
    {
        $key = 'test:roundtrip:' . bin2hex(random_bytes(4));

        self::assertTrue($this->cache->setJson($key, ['value' => 42], 60));
        self::assertSame(['value' => 42], $this->cache->getJson($key));
        self::assertTrue($this->cache->delete($key));
        self::assertNull($this->cache->getJson($key));
    }

    public function testRememberJsonProducesOnceAndServesCacheHits(): void
    {
        $key = 'test:remember:' . bin2hex(random_bytes(4));
        $calls = 0;
        $producer = static function () use (&$calls): array {
            $calls++;

            return ['calls' => $calls];
        };

        self::assertSame(['calls' => 1], $this->cache->rememberJson($key, 60, $producer));
        self::assertSame(['calls' => 1], $this->cache->rememberJson($key, 60, $producer));
        self::assertSame(1, $calls);

        $this->cache->delete($key);
    }

    public function testOversizedPayloadsAreSkipped(): void
    {
        $key = 'test:oversized:' . bin2hex(random_bytes(4));

        self::assertFalse($this->cache->setJson(
            $key,
            ['blob' => str_repeat('x', CacheRedis::MAX_PAYLOAD_BYTES + 1)],
            60
        ));
        self::assertNull($this->cache->getJson($key));
    }

    public function testNamespaceVersioningOrphansKeys(): void
    {
        $namespace = 'testns' . bin2hex(random_bytes(4));
        $this->cache->delete($namespace . ':version');

        $first = $this->cache->namespaced($namespace, 'entity', 'abc');
        self::assertTrue($this->cache->setJson($first, ['generation' => 'first'], 60));

        self::assertTrue($this->cache->bump($namespace));

        $second = $this->cache->namespaced($namespace, 'entity', 'abc');
        self::assertNotSame($first, $second);
        self::assertNull($this->cache->getJson($second));

        $this->cache->delete($namespace . ':version');
    }

    public function testFailsOpenWhenRedisIsUnreachable(): void
    {
        $originalHost = $_SERVER['REDIS_SERVER_HOST'] ?? null;
        $originalPort = $_SERVER['REDIS_SERVER_PORT'] ?? null;

        $_SERVER['REDIS_SERVER_HOST'] = $_ENV['REDIS_SERVER_HOST'] = '127.0.0.1';
        $_SERVER['REDIS_SERVER_PORT'] = $_ENV['REDIS_SERVER_PORT'] = '1';
        putenv('REDIS_SERVER_HOST=127.0.0.1');
        putenv('REDIS_SERVER_PORT=1');

        try {
            $cache = new CacheRedis();

            self::assertNull($cache->get('unreachable'));
            self::assertFalse($cache->set('unreachable', 'value', 60));
            self::assertFalse($cache->isEnabled());
            self::assertSame(
                ['fallback' => true],
                $cache->rememberJson('unreachable', 60, static fn (): array => ['fallback' => true])
            );
        } finally {
            $this->restoreRedisEnvironment($originalHost, $originalPort);
        }
    }

    private function restoreRedisEnvironment(?string $host, ?string $port): void
    {
        unset($_SERVER['REDIS_SERVER_HOST'], $_ENV['REDIS_SERVER_HOST']);
        unset($_SERVER['REDIS_SERVER_PORT'], $_ENV['REDIS_SERVER_PORT']);
        putenv('REDIS_SERVER_HOST');
        putenv('REDIS_SERVER_PORT');

        if (is_string($host)) {
            $_SERVER['REDIS_SERVER_HOST'] = $_ENV['REDIS_SERVER_HOST'] = $host;
            putenv('REDIS_SERVER_HOST=' . $host);
        }
        if (is_string($port)) {
            $_SERVER['REDIS_SERVER_PORT'] = $_ENV['REDIS_SERVER_PORT'] = $port;
            putenv('REDIS_SERVER_PORT=' . $port);
        }
    }
}
