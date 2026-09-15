<?php

declare(strict_types=1);

namespace App\Helper;

use DateTimeImmutable;
use DateTimeZone;
use Lcobucci\Clock\FrozenClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\IdentifiedBy;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

final class JwtHelper
{
    public const string DEFAULT_TOKEN_ID = '4f1g23a12aa';
    public const string DEFAULT_TTL = '+7 day';

    /**
     * Minimum HMAC-SHA256 key length in bytes.
     */
    private const int MIN_SECRET_LENGTH = 32;

    /**
     * Signing secret from JWT_SECRET. A missing or short secret is a
     * configuration error and must never silently disable validation.
     */
    public static function secretKey(): string
    {
        $secret = $_SERVER['JWT_SECRET'] ?? $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET');
        if (!is_string($secret) || $secret === '') {
            throw new RuntimeException('JWT_SECRET is not configured.');
        }
        if (strlen($secret) < self::MIN_SECRET_LENGTH) {
            throw new RuntimeException(
                sprintf('JWT_SECRET must be at least %d bytes for HS256.', self::MIN_SECRET_LENGTH)
            );
        }

        return $secret;
    }

    /**
     * Issuer and audience for issued tokens.
     */
    public static function issuer(): string
    {
        $baseUrl = $_SERVER['APP_BASE_URL'] ?? $_ENV['APP_BASE_URL'] ?? '';
        if (!is_string($baseUrl) || $baseUrl === '') {
            throw new RuntimeException('APP_BASE_URL is not configured.');
        }

        return $baseUrl;
    }

    public static function tokenId(): string
    {
        $identifier = $_SERVER['JWT_IDENTIFIER'] ?? $_ENV['JWT_IDENTIFIER'] ?? '';

        return is_string($identifier) && $identifier !== '' ? $identifier : self::DEFAULT_TOKEN_ID;
    }

    public static function ttl(): string
    {
        $ttl = $_SERVER['JWT_TTL'] ?? $_ENV['JWT_TTL'] ?? '';

        return is_string($ttl) && $ttl !== '' ? $ttl : self::DEFAULT_TTL;
    }

    /**
     * Build a signed token with the fixed issuer/audience/id claims and TTL.
     *
     * @param array<string, mixed> $claims
     */
    public static function buildToken(array $claims, ?string $ttl = null): string
    {
        $configuration = self::configuration(self::secretKey());
        $now = self::now();

        $builder = $configuration->builder()
            ->issuedBy(self::issuer())
            ->permittedFor(self::issuer())
            ->identifiedBy(self::tokenId())
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify($ttl ?? self::ttl()));

        foreach ($claims as $name => $value) {
            $builder = $builder->withClaim((string) $name, $value);
        }

        return $builder
            ->getToken($configuration->signer(), $configuration->signingKey())
            ->toString();
    }

    /**
     * Parse and validate a token. Returns all claims when valid, null for any
     * token problem (malformed, expired, wrong signature/issuer/audience).
     *
     * @return array<string, mixed>|null
     */
    public static function claims(#[\SensitiveParameter] string $token): ?array
    {
        if (trim($token) === '') {
            return null;
        }

        $secret = self::secretKey();
        $configuration = self::configuration($secret);

        try {
            $parsed = $configuration->parser()->parse($token);
            if (!$parsed instanceof Plain) {
                return null;
            }

            $valid = $configuration->validator()->validate(
                $parsed,
                new IdentifiedBy(self::tokenId()),
                new IssuedBy(self::issuer()),
                new PermittedFor(self::issuer()),
                new SignedWith(new Sha256(), InMemory::plainText($secret)),
                new StrictValidAt(new FrozenClock(self::now()))
            );

            return $valid ? $parsed->claims()->all() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Read the Authorization header (raw token or "Bearer <token>"), validate
     * it and map the claims to a user object. Returns null when the header is
     * missing or the token is invalid.
     */
    public static function requestUser(ServerRequestInterface $request): ?\stdClass
    {
        $header = trim($request->getHeaderLine('Authorization'));
        if ($header === '') {
            return null;
        }

        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches) === 1) {
            $header = trim($matches[1]);
        }

        $claims = self::claims($header);
        if ($claims === null || empty($claims['id'])) {
            return null;
        }

        return self::userFromClaims($claims);
    }

    /**
     * @param array<string, mixed> $claims
     */
    public static function userFromClaims(array $claims): \stdClass
    {
        $user = new \stdClass();
        $user->id = $claims['id'] ?? null;
        $user->email = $claims['email'] ?? null;
        $user->name = $claims['name'] ?? null;
        $user->type = $claims['type'] ?? null;
        $user->logged_in = true;

        return $user;
    }

    private static function configuration(string $secret): Configuration
    {
        return Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($secret)
        );
    }

    private static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
