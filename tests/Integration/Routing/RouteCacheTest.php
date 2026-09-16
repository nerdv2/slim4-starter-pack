<?php

declare(strict_types=1);

namespace Tests\Integration\Routing;

use Tests\TestCase;
use Tests\Traits\OverridesEnvironment;

final class RouteCacheTest extends TestCase
{
    use OverridesEnvironment;

    private string $cacheFile;

    protected function setUp(): void
    {
        $this->cacheFile = sys_get_temp_dir() . '/slim4-routes-' . bin2hex(random_bytes(4)) . '.php';

        $this->overrideEnvironment([
            'ROUTE_CACHE' => 'true',
            'ROUTE_CACHE_FILE' => $this->cacheFile,
        ]);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        @unlink($this->cacheFile);
        $this->restoreEnvironment();

        parent::tearDown();
    }

    public function testRoutesResolveThroughTheCompiledCache(): void
    {
        self::assertSame($this->cacheFile, $this->app->getRouteCollector()->getCacheFile());

        // First dispatch compiles and writes the cache file.
        $liveness = $this->handle($this->createRequest('GET', '/health'));
        self::assertSame(200, $liveness->getStatusCode());
        self::assertFileExists($this->cacheFile);

        // Subsequent requests are served from the compiled dispatcher cache.
        self::assertSame(200, $this->handle($this->createRequest('GET', '/health/ready'))->getStatusCode());
        self::assertSame(401, $this->handle($this->createRequest('GET', '/customer'))->getStatusCode());
        self::assertSame(404, $this->handle($this->createRequest('GET', '/missing'))->getStatusCode());
    }
}
