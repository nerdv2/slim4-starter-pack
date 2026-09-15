<?php

declare(strict_types=1);

namespace Tests\Unit\DTO;

use App\DTO\Request\CustomerUpdateRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

final class CustomerUpdateRequestTest extends TestCase
{
    public function testFromRequestCastsAndTrims(): void
    {
        $dto = CustomerUpdateRequest::fromRequest($this->request(['id' => '7', 'name' => ' Acme ']));

        self::assertSame(7, $dto->id);
        self::assertSame('Acme', $dto->name);
        self::assertTrue($dto->isValid());
    }

    public function testInvalidIdIsRejected(): void
    {
        foreach (['abc', '0', '-3', ''] as $id) {
            $dto = CustomerUpdateRequest::fromRequest($this->request(['id' => $id, 'name' => 'Acme']));

            self::assertFalse($dto->isValid(), "Id should be invalid: {$id}");
            self::assertArrayHasKey('id', $dto->validate());
        }
    }

    public function testMissingNameIsRejected(): void
    {
        $dto = CustomerUpdateRequest::fromRequest($this->request(['id' => '1', 'name' => '']));

        self::assertSame(['name' => 'Name is required.'], $dto->validate());
    }

    public function testAllErrorsAreReportedTogether(): void
    {
        $dto = CustomerUpdateRequest::fromRequest($this->request([]));

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
            ->createServerRequest('POST', '/customer/update')
            ->withParsedBody($body);
    }
}
