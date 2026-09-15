<?php

declare(strict_types=1);

namespace Tests\Unit\DTO;

use App\DTO\Request\CustomerIdRequest;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class CustomerIdRequestTest extends TestCase
{
    public function testValidIdIsCastToInt(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('DELETE', '/customer/delete')
            ->withParsedBody(['id' => '12']);

        $dto = CustomerIdRequest::fromRequest($request);

        self::assertSame(12, $dto->id);
        self::assertTrue($dto->isValid());
    }

    public function testInvalidIdIsRejected(): void
    {
        foreach (['abc', '0', '-1', null] as $id) {
            $request = (new ServerRequestFactory())
                ->createServerRequest('DELETE', '/customer/delete')
                ->withParsedBody(['id' => $id]);

            $dto = CustomerIdRequest::fromRequest($request);

            self::assertFalse($dto->isValid(), 'Id should be invalid: ' . var_export($id, true));
            self::assertSame(['id' => 'A positive id is required.'], $dto->validate());
        }
    }
}
