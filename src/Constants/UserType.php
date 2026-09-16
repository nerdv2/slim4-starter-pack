<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * User account roles. The `type` claim in the access token drives
 * AuthorizationMiddleware.
 */
final class UserType
{
    public const string ADMIN = 'admin';
    public const string STAFF = 'staff';

    /** @var list<string> */
    public const array ALL = [self::ADMIN, self::STAFF];

    public static function isValid(mixed $type): bool
    {
        return is_string($type) && in_array($type, self::ALL, true);
    }
}
