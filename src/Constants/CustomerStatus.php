<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Customer lifecycle status. Values are stored as short strings and validated
 * against this allowlist before they reach a model.
 */
final class CustomerStatus
{
    public const string LEAD = 'lead';
    public const string PROSPECT = 'prospect';
    public const string ACTIVE = 'active';
    public const string INACTIVE = 'inactive';

    public const string DEFAULT = self::LEAD;

    /** @var list<string> */
    public const array ALL = [self::LEAD, self::PROSPECT, self::ACTIVE, self::INACTIVE];

    public static function isValid(mixed $status): bool
    {
        return is_string($status) && in_array($status, self::ALL, true);
    }

    /**
     * Normalize a status value: lowercased, trimmed and validated against the
     * allowlist. Unknown values fall back to null so callers can decide between
     * rejecting the input and using the default.
     */
    public static function normalize(mixed $status): ?string
    {
        if (!is_string($status)) {
            return null;
        }

        $normalized = strtolower(trim($status));

        return self::isValid($normalized) ? $normalized : null;
    }
}
