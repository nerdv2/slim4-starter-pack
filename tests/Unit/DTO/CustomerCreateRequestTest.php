<?php

declare(strict_types=1);

namespace Tests\Unit\DTO;

use App\DTO\Request\CustomerCreateRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

final class CustomerCreateRequestTest extends TestCase
{
    public function testFromRequestTrimsAndNormalizes(): void
    {
        $dto = CustomerCreateRequest::fromRequest($this->request([
            'name' => '  Acme  ',
            'email' => 'Sales@Acme.Example',
            'phone' => '  +62 811  ',
            'company' => ' Acme Inc ',
            'status' => ' ACTIVE ',
            'address' => ' ',
            'notes' => ' vip ',
        ]));

        self::assertSame('Acme', $dto->name);
        self::assertSame('sales@acme.example', $dto->email);
        self::assertSame('+62 811', $dto->phone);
        self::assertSame('Acme Inc', $dto->company);
        self::assertSame('active', $dto->status);
        self::assertNull($dto->address);
        self::assertSame('vip', $dto->notes);
        self::assertTrue($dto->isValid());
    }

    public function testStatusDefaultsToLead(): void
    {
        $dto = CustomerCreateRequest::fromRequest($this->request(['name' => 'Acme']));

        self::assertSame('lead', $dto->status);
        self::assertTrue($dto->isValid());
    }

    public function testMissingOrEmptyNameFailsValidation(): void
    {
        $dto = CustomerCreateRequest::fromRequest($this->request(['name' => '   ']));

        self::assertFalse($dto->isValid());
        self::assertSame(['name' => 'Name is required.'], $dto->validate());
    }

    public function testInvalidStatusIsRejected(): void
    {
        $dto = CustomerCreateRequest::fromRequest($this->request(['name' => 'Acme', 'status' => 'customer']));

        self::assertFalse($dto->isValid());
        self::assertArrayHasKey('status', $dto->validate());
    }

    public function testInvalidEmailIsRejected(): void
    {
        $dto = CustomerCreateRequest::fromRequest($this->request(['name' => 'Acme', 'email' => 'not-an-email']));

        self::assertArrayHasKey('email', $dto->validate());
    }

    public function testRequestWithoutParsedBodyIsHandled(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/customer');

        self::assertFalse(CustomerCreateRequest::fromRequest($request)->isValid());
    }

    /**
     * @param array<string, mixed> $body
     */
    private function request(array $body): ServerRequestInterface
    {
        return (new ServerRequestFactory())
            ->createServerRequest('POST', '/customer')
            ->withParsedBody($body);
    }
}
