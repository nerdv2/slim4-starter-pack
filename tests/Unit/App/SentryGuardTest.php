<?php

declare(strict_types=1);

namespace Tests\Unit\App;

use PHPUnit\Framework\TestCase;
use Sentry\SentrySdk;

final class SentryGuardTest extends TestCase
{
    public function testSentryStaysDisabledWithoutDsn(): void
    {
        unset($_SERVER['SENTRY_DSN'], $_ENV['SENTRY_DSN']);
        putenv('SENTRY_DSN');

        require dirname(__DIR__, 3) . '/src/App/Sentry.php';

        self::assertNull(SentrySdk::getCurrentHub()->getClient());
    }
}
