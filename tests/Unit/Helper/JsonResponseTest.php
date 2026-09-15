<?php

declare(strict_types=1);

namespace Tests\Unit\Helper;

use App\Helper\JsonResponse;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;

final class JsonResponseTest extends TestCase
{
    public function testSuccessEnvelopeIncludesExtraKeys(): void
    {
        $response = JsonResponse::success(new Response(), ['id' => 1], 'Data ditemukan', ['total_page' => 3]);
        $payload = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json;charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertTrue($payload['status']);
        self::assertSame('Data ditemukan', $payload['message']);
        self::assertSame(['id' => 1], $payload['data']);
        self::assertSame(3, $payload['total_page']);
    }

    public function testErrorEnvelopeKeepsHttpStatus(): void
    {
        $response = JsonResponse::error(new Response(), 'Name is required.', [], [], 400);
        $payload = $this->decode($response);

        self::assertSame(400, $response->getStatusCode());
        self::assertFalse($payload['status']);
        self::assertSame('Name is required.', $payload['message']);
        self::assertSame([], $payload['data']);
    }

    public function testNotFoundUsesCanonicalMessage(): void
    {
        $payload = $this->decode(JsonResponse::notFound(new Response()));

        self::assertFalse($payload['status']);
        self::assertSame('Data tidak ditemukan', $payload['message']);
    }

    public function testEncodingFailureFallsBackTo500(): void
    {
        $response = JsonResponse::success(new Response(), ['invalid' => "\xB1\x31"]);

        self::assertSame(500, $response->getStatusCode());
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
