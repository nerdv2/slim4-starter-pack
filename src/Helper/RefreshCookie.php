<?php

declare(strict_types=1);

namespace App\Helper;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Builds and reads the HttpOnly refresh-token cookie.
 *
 * The cookie only ever carries the opaque token; access tokens stay in the
 * client's memory. SameSite defaults to Lax, which blocks cross-site POSTs
 * (the CSRF vector for the refresh endpoint) while keeping the cookie on
 * same-site requests from the SPA.
 */
final class RefreshCookie
{
    public const string NAME = 'refresh_token';

    /**
     * @return array{name: string, value: string, attributes: array<string, string>}
     */
    public static function create(string $token): array
    {
        return [
            'name' => self::NAME,
            'value' => $token,
            'attributes' => [
                'Path' => '/',
                'Max-Age' => (string) self::ttlSeconds(),
                'HttpOnly' => '',
                'SameSite' => self::sameSite(),
            ] + self::secureAttribute(),
        ];
    }

    /**
     * Expired cookie for logout responses.
     *
     * @return array{name: string, value: string, attributes: array<string, string>}
     */
    public static function clear(): array
    {
        return [
            'name' => self::NAME,
            'value' => '',
            'attributes' => [
                'Path' => '/',
                'Max-Age' => '0',
                'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
                'HttpOnly' => '',
                'SameSite' => self::sameSite(),
            ] + self::secureAttribute(),
        ];
    }

    /**
     * Raw refresh token from the request cookies, or null when absent.
     */
    public static function fromRequest(ServerRequestInterface $request): ?string
    {
        $cookies = $request->getCookieParams();
        $token = $cookies[self::NAME] ?? null;
        if (!is_string($token) || trim($token) === '') {
            return null;
        }

        return $token;
    }

    /**
     * Serialize a cookie array into a Set-Cookie header value.
     *
     * @param array{name: string, value: string, attributes: array<string, string>} $cookie
     */
    public static function toHeader(array $cookie): string
    {
        $parts = [$cookie['name'] . '=' . rawurlencode($cookie['value'])];
        foreach ($cookie['attributes'] as $name => $value) {
            $parts[] = $value === '' ? $name : $name . '=' . $value;
        }

        return implode('; ', $parts);
    }

    public static function ttlSeconds(): int
    {
        $days = (int) ($_SERVER['REFRESH_TOKEN_TTL_DAYS'] ?? $_ENV['REFRESH_TOKEN_TTL_DAYS'] ?? 30);
        if ($days < 1) {
            $days = 30;
        }

        return $days * 86400;
    }

    private static function sameSite(): string
    {
        $sameSite = ucfirst(strtolower(trim((string) (
            $_SERVER['AUTH_COOKIE_SAMESITE'] ?? $_ENV['AUTH_COOKIE_SAMESITE'] ?? 'Lax'
        ))));
        if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            $sameSite = 'Lax';
        }

        return $sameSite;
    }

    /**
     * @return array<string, string>
     */
    private static function secureAttribute(): array
    {
        $configured = $_SERVER['AUTH_COOKIE_SECURE'] ?? $_ENV['AUTH_COOKIE_SECURE'] ?? null;
        if (is_bool($configured) || (is_string($configured) && trim($configured) !== '')) {
            $secure = filter_var($configured, FILTER_VALIDATE_BOOLEAN);
        } else {
            $environment = strtolower((string) (
                $_SERVER['APP_ENVIRONMENT'] ?? $_ENV['APP_ENVIRONMENT'] ?? 'production'
            ));
            $secure = !in_array($environment, ['development', 'testing', 'local'], true);
        }

        return $secure ? ['Secure' => ''] : [];
    }
}
