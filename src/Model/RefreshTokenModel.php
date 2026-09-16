<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Opaque refresh tokens, stored as SHA-256 hashes.
 *
 * Rotation keeps one token per session family. Reusing a rotated token means
 * the family leaks, so AuthService revokes the whole family on reuse.
 */
final class RefreshTokenModel extends BaseModel
{
    public function create(
        int|string $userId,
        string $familyId,
        string $tokenHash,
        string $expiresAt,
        ?string $userAgent = null,
        ?string $ipAddress = null
    ): int {
        return (int) $this->db()->table('refresh_token')->insertGetId([
            'user_id' => $userId,
            'family_id' => $familyId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'revoked_at' => null,
            'user_agent' => $userAgent === null ? null : substr($userAgent, 0, 255),
            'ip_address' => $ipAddress,
            'created_at' => $this->now(),
        ]);
    }

    public function findByHash(string $tokenHash): ?object
    {
        return $this->db()->table('refresh_token')
            ->where('refresh_token.token_hash', '=', $tokenHash)
            ->first();
    }

    public function revoke(int|string $id): int
    {
        return $this->db()->table('refresh_token')
            ->where('refresh_token.id', '=', $id)
            ->whereNull('refresh_token.revoked_at')
            ->update(['revoked_at' => $this->now()]);
    }

    public function revokeFamily(string $familyId): int
    {
        return $this->db()->table('refresh_token')
            ->where('refresh_token.family_id', '=', $familyId)
            ->whereNull('refresh_token.revoked_at')
            ->update(['revoked_at' => $this->now()]);
    }

    public function revokeAllForUser(int|string $userId): int
    {
        return $this->db()->table('refresh_token')
            ->where('refresh_token.user_id', '=', $userId)
            ->whereNull('refresh_token.revoked_at')
            ->update(['revoked_at' => $this->now()]);
    }

    /**
     * Revoke every session of the user except the family that keeps the
     * current device signed in.
     */
    public function revokeAllForUserExceptFamily(int|string $userId, string $familyId): int
    {
        return $this->db()->table('refresh_token')
            ->where('refresh_token.user_id', '=', $userId)
            ->whereNot('refresh_token.family_id', '=', $familyId)
            ->whereNull('refresh_token.revoked_at')
            ->update(['revoked_at' => $this->now()]);
    }

    public function revokeExpiredForUser(int|string $userId): int
    {
        return $this->db()->table('refresh_token')
            ->where('refresh_token.user_id', '=', $userId)
            ->where('refresh_token.expires_at', '<', $this->now())
            ->whereNull('refresh_token.revoked_at')
            ->update(['revoked_at' => $this->now()]);
    }
}
