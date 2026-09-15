<?php

declare(strict_types=1);

namespace App\Helper;

use App\Constants\HttpStatus;
use Psr\Http\Message\ResponseInterface as Response;

final class JsonResponse
{
    public const string DEFAULT_SUCCESS_MESSAGE = 'Data ditemukan';
    public const string MESSAGE_NOT_FOUND = 'Data tidak ditemukan';

    /**
     * Standard success envelope: status/message/data plus optional extra
     * top-level keys (total_page, total_data, stats, ...).
     *
     * @param array<string, mixed> $extra
     */
    public static function success(
        Response $response,
        mixed $data = [],
        string $message = self::DEFAULT_SUCCESS_MESSAGE,
        array $extra = [],
        int $httpStatus = HttpStatus::OK
    ): Response {
        return self::withJson(
            $response,
            array_merge(['status' => true, 'message' => $message, 'data' => $data], $extra),
            $httpStatus
        );
    }

    /**
     * Standard failure envelope. Legacy endpoints answer with HTTP 200, so that
     * is the default; pass $httpStatus for endpoints that must return a real
     * 4xx/5xx code.
     *
     * @param array<string, mixed> $extra
     */
    public static function error(
        Response $response,
        string $message,
        mixed $data = [],
        array $extra = [],
        int $httpStatus = HttpStatus::OK
    ): Response {
        return self::withJson(
            $response,
            array_merge(['status' => false, 'message' => $message, 'data' => $data], $extra),
            $httpStatus
        );
    }

    /**
     * Canonical not-found failure, so the message lives in one place.
     *
     * @param array<string, mixed> $extra
     */
    public static function notFound(
        Response $response,
        mixed $data = [],
        array $extra = [],
        int $httpStatus = HttpStatus::OK
    ): Response {
        return self::error($response, self::MESSAGE_NOT_FOUND, $data, $extra, $httpStatus);
    }

    /**
     * Low-level writer for payloads that are not the standard envelope.
     *
     * @param array<string, mixed> $data
     */
    public static function withJson(
        Response $response,
        array $data,
        int $httpStatus = HttpStatus::OK
    ): Response {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            $body = json_encode(
                ['status' => false, 'message' => 'Failed to encode response'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            $httpStatus = HttpStatus::INTERNAL_SERVER_ERROR;
        }

        $response->getBody()->write((string) $body);

        return $response
            ->withHeader('Content-Type', 'application/json;charset=utf-8')
            ->withStatus($httpStatus);
    }
}
