<?php

declare(strict_types=1);

namespace Tests\Unit\DTO;

use App\DTO\Request\CustomerUpdateRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

final class CustomerUpdateRequestTest extends TestCase
{
    public function testIdComesFromTheRouteAndPayloadIsTrimmed(): void
    {
        $dto = CustomerUpdateRequest::fromRequest(
            $this->request(['name' => ' Acme ', 'status' => 'prospect']),
            '7'
        );

        self::assertSame(7, $dto->id);
        self::assertSame('Acme', $dto->payload->name);
        self::assertSame('prospect', $dto->payload->status);
        self::assertTrue($dto->isValid());
    }

    public function testInvalidIdIsRejected(): void
    {
        foreach (['abc', '0', '-3', '', null] as $id) {
            $dto = CustomerUpdateRequest::fromRequest($this->request(['name' => 'Acme']), $id);

            self::assertFalse($dto->isValid());
            self::assertArrayHasKey('id', $dto->validate());
        }
    }

    public function testMissingNameIsRejected(): void
    {
        $dto = CustomerUpdateRequest::fromRequest($this->request(['name' => '']), '1');

        self::assertSame(['name' => 'Name is required.'], $dto->validate());
    }

    public function testAllErrorsAreReportedTogether(): void
    {
        $dto = CustomerUpdateRequest::fromRequest($this->request([]), null);

        self::assertSame(
            ['id' => 'A positive id is required.', 'name' => 'Name is required.'],
            $dto->validate()
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    private function request(array $body): ServerRequestInterface
    {
        return (new ServerRequestFactory())
            ->createServerRequest('PUT', '/customer/1')
            ->withParsedBody($body);
    }
}
