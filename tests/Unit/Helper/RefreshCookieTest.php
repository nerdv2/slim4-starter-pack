<?php

declare(strict_types=1);

namespace Tests\Unit\Helper;

use App\Helper\RefreshCookie;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Traits\OverridesEnvironment;

final class RefreshCookieTest extends TestCase
{
    use OverridesEnvironment;

    protected function tearDown(): void
    {
        $this->restoreEnvironment();

        parent::tearDown();
    }

    public function testCreateSerializesTheExpectedAttributes(): void
    {
        $header = RefreshCookie::toHeader(RefreshCookie::create('token-value'));

        self::assertStringStartsWith('refresh_token=token-value', $header);
        self::assertStringContainsString('Path=/', $header);
        self::assertStringContainsString('Max-Age=2592000', $header);
        self::assertStringContainsString('HttpOnly', $header);
        self::assertStringContainsString('SameSite=Lax', $header);
        self::assertStringNotContainsString('Secure', $header);
    }

    public function testClearExpiresTheCookie(): void
    {
        $header = RefreshCookie::toHeader(RefreshCookie::clear());

        self::assertStringStartsWith('refresh_token=;', $header);
        self::assertStringContainsString('Max-Age=0', $header);
        self::assertStringContainsString('Expires=Thu, 01 Jan 1970', $header);
    }

    public function testFromRequestReadsTheCookieAndIgnoresEmptyValues(): void
    {
        $factory = new ServerRequestFactory();

        $present = $factory->createServerRequest('POST', '/auth/refresh')
            ->withCookieParams([RefreshCookie::NAME => 'abc123']);
        $empty = $factory->createServerRequest('POST', '/auth/refresh')
            ->withCookieParams([RefreshCookie::NAME => '   ']);
        $missing = $factory->createServerRequest('POST', '/auth/refresh');

        self::assertSame('abc123', RefreshCookie::fromRequest($present));
        self::assertNull(RefreshCookie::fromRequest($empty));
        self::assertNull(RefreshCookie::fromRequest($missing));
    }

    public function testSecureAndSameSiteFollowTheEnvironment(): void
    {
        $this->overrideEnvironment([
            'AUTH_COOKIE_SECURE' => 'true',
            'AUTH_COOKIE_SAMESITE' => 'Strict',
        ]);

        $header = RefreshCookie::toHeader(RefreshCookie::create('token-value'));

        self::assertStringContainsString('Secure', $header);
        self::assertStringContainsString('SameSite=Strict', $header);
    }

    public function testTokenValuesAreUrlEncoded(): void
    {
        $header = RefreshCookie::toHeader(RefreshCookie::create('a b+c'));

        self::assertStringStartsWith('refresh_token=a%20b%2Bc', $header);
    }
}
