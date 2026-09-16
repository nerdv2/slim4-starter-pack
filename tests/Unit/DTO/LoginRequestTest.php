<?php

declare(strict_types=1);

namespace Tests\Unit\DTO;

use App\DTO\Request\LoginRequest;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class LoginRequestTest extends TestCase
{
    public function testEmailIsLowercasedAndTrimmed(): void
    {
        $dto = LoginRequest::fromRequest(
            (new ServerRequestFactory())
                ->createServerRequest('POST', '/auth/login')
                ->withParsedBody(['email' => ' Admin@Example.COM ', 'password' => 'secret'])
        );

        self::assertSame('admin@example.com', $dto->email);
        self::assertSame('secret', $dto->password);
        self::assertTrue($dto->isValid());
    }

    public function testMissingCredentialsAreRejected(): void
    {
        $dto = LoginRequest::fromRequest(
            (new ServerRequestFactory())->createServerRequest('POST', '/auth/login')
        );

        self::assertSame(
            ['email' => 'Email is required.', 'password' => 'Password is required.'],
            $dto->validate()
        );
    }
}
