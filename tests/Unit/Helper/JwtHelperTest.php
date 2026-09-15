<?php

declare(strict_types=1);

namespace Tests\Unit\Helper;

use App\Helper\JwtHelper;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Slim\Psr7\Factory\ServerRequestFactory;

final class JwtHelperTest extends TestCase
{
    private string|false|null $originalSecret = null;

    private bool $secretOverridden = false;

    protected function tearDown(): void
    {
        if ($this->secretOverridden) {
            $this->restoreSecret();
        }

        parent::tearDown();
    }

    public function testBuildAndValidateRoundTrip(): void
    {
        $token = JwtHelper::buildToken([
            'id' => 7,
            'email' => 'user@example.com',
            'name' => 'Test User',
            'type' => 'admin',
        ]);
        $claims = JwtHelper::claims($token);

        self::assertNotNull($claims);
        self::assertSame(7, $claims['id']);
        self::assertSame('admin', $claims['type']);
        self::assertSame('user@example.com', $claims['email']);
        self::assertArrayHasKey('iat', $claims);
        self::assertArrayHasKey('nbf', $claims);
        self::assertArrayHasKey('exp', $claims);
    }

    public function testMalformedTokensAreRejected(): void
    {
        foreach (['', '   ', 'garbage', 'a.b.c', 'eyJhbGciOiJIUzI1NiJ9.e30.signature'] as $token) {
            self::assertNull(JwtHelper::claims($token), "Token should be rejected: {$token}");
        }
    }

    public function testExpiredTokenIsRejected(): void
    {
        self::assertNull(JwtHelper::claims(JwtHelper::buildToken(['id' => 1], '-1 second')));
    }

    public function testTokenSignedWithAnotherSecretIsRejected(): void
    {
        $token = JwtHelper::buildToken(['id' => 1]);
        $this->overrideSecret(str_repeat('b', 32));

        self::assertNull(JwtHelper::claims($token));
    }

    public function testRequestUserAcceptsRawAndBearerHeaders(): void
    {
        $token = JwtHelper::buildToken(['id' => 9, 'type' => 'admin']);
        $factory = new ServerRequestFactory();

        $raw = $factory->createServerRequest('GET', '/example')->withHeader('Authorization', $token);
        $bearer = $factory->createServerRequest('GET', '/example')->withHeader('Authorization', 'Bearer ' . $token);
        $missing = $factory->createServerRequest('GET', '/example');

        self::assertSame(9, JwtHelper::requestUser($raw)?->id);
        self::assertSame(9, JwtHelper::requestUser($bearer)?->id);
        self::assertNull(JwtHelper::requestUser($missing));
    }

    public function testTokenWithoutIdIsRejected(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/example')
            ->withHeader('Authorization', JwtHelper::buildToken(['type' => 'admin']));

        self::assertNull(JwtHelper::requestUser($request));
    }

    public function testMissingSecretRaisesConfigurationError(): void
    {
        $this->overrideSecret(null);

        try {
            JwtHelper::secretKey();
            self::fail('Expected a configuration error for a missing JWT_SECRET.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('JWT_SECRET is not configured', $exception->getMessage());
        }
    }

    public function testShortSecretRaisesConfigurationError(): void
    {
        $this->overrideSecret('too-short');

        try {
            JwtHelper::secretKey();
            self::fail('Expected a configuration error for a short JWT_SECRET.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('32 bytes', $exception->getMessage());
        }
    }

    private function overrideSecret(?string $secret): void
    {
        if (!$this->secretOverridden) {
            $this->originalSecret = $_ENV['JWT_SECRET'] ?? $_SERVER['JWT_SECRET'] ?? getenv('JWT_SECRET');
            $this->secretOverridden = true;
        }

        unset($_ENV['JWT_SECRET'], $_SERVER['JWT_SECRET']);
        putenv('JWT_SECRET');

        if ($secret !== null) {
            $_ENV['JWT_SECRET'] = $_SERVER['JWT_SECRET'] = $secret;
            putenv('JWT_SECRET=' . $secret);
        }
    }

    private function restoreSecret(): void
    {
        unset($_ENV['JWT_SECRET'], $_SERVER['JWT_SECRET']);
        putenv('JWT_SECRET');

        if (is_string($this->originalSecret)) {
            $_ENV['JWT_SECRET'] = $_SERVER['JWT_SECRET'] = $this->originalSecret;
            putenv('JWT_SECRET=' . $this->originalSecret);
        }
    }
}
