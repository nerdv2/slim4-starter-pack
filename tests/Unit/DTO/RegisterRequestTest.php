<?php

declare(strict_types=1);

namespace Tests\Unit\DTO;

use App\DTO\Request\RegisterRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

final class RegisterRequestTest extends TestCase
{
    public function testValidPayloadNormalizesEmailAndTrimsName(): void
    {
        $dto = RegisterRequest::fromRequest($this->request([
            'name' => '  Jane Doe ',
            'email' => 'JANE@Example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]));

        self::assertSame('Jane Doe', $dto->name);
        self::assertSame('jane@example.com', $dto->email);
        self::assertTrue($dto->isValid());
    }

    public function testShortPasswordIsRejected(): void
    {
        $dto = RegisterRequest::fromRequest($this->request([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]));

        self::assertArrayHasKey('password', $dto->validate());
    }

    public function testPasswordConfirmationMustMatch(): void
    {
        $dto = RegisterRequest::fromRequest($this->request([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Different123!',
        ]));

        self::assertArrayHasKey('password_confirmation', $dto->validate());
    }

    public function testInvalidEmailAndMissingFieldsAreReportedTogether(): void
    {
        $errors = RegisterRequest::fromRequest($this->request([
            'email' => 'not-an-email',
        ]))->validate();

        self::assertArrayHasKey('name', $errors);
        self::assertArrayHasKey('email', $errors);
        self::assertArrayHasKey('password', $errors);
        self::assertArrayHasKey('password_confirmation', $errors);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function request(array $body): ServerRequestInterface
    {
        return (new ServerRequestFactory())
            ->createServerRequest('POST', '/auth/register')
            ->withParsedBody($body);
    }
}
