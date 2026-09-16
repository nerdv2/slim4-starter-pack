<?php

declare(strict_types=1);

namespace Tests\Integration\Routing;

use Tests\TestCase;
use Tests\Traits\OverridesEnvironment;

final class RouteCacheDisabledTest extends TestCase
{
    use OverridesEnvironment;

    protected function setUp(): void
    {
        // An empty ROUTE_CACHE follows DISPLAY_ERROR_DETAILS, which phpunit.xml
        // forces to true, so the cache must stay off.
        $this->overrideEnvironment(['ROUTE_CACHE' => '']);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->restoreEnvironment();

        parent::tearDown();
    }

    public function testCollectorHasNoCacheFileWhenDisabled(): void
    {
        self::assertNull($this->app->getRouteCollector()->getCacheFile());
        self::assertSame(200, $this->handle($this->createRequest('GET', '/health'))->getStatusCode());
    }
}
