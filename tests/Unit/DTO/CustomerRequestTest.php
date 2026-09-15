<?php

declare(strict_types=1);

namespace Tests\Unit\DTO;

use App\DTO\Request\CustomerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

final class CustomerRequestTest extends TestCase
{
    public function testFromRequestTrimsTheName(): void
    {
        $dto = CustomerRequest::fromRequest($this->request(['name' => '  Acme  ']));

        self::assertSame('Acme', $dto->name);
        self::assertTrue($dto->isValid());
        self::assertSame([], $dto->validate());
    }

    public function testMissingOrEmptyNameFailsValidation(): void
    {
        $dto = CustomerRequest::fromRequest($this->request(['name' => '   ']));

        self::assertFalse($dto->isValid());
        self::assertSame(['name' => 'Name is required.'], $dto->validate());
    }

    public function testRequestWithoutParsedBodyIsHandled(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/customer/add');

        self::assertFalse(CustomerRequest::fromRequest($request)->isValid());
    }

    /**
     * @param array<string, mixed> $body
     */
    private function request(array $body): ServerRequestInterface
    {
        return (new ServerRequestFactory())
            ->createServerRequest('POST', '/customer/add')
            ->withParsedBody($body);
    }
}
