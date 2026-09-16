<?php

declare(strict_types=1);

namespace App\Service;

use App\Constants\DateFormat;
use App\Constants\HttpStatus;
use App\Constants\UserType;
use App\DTO\Request\ChangePasswordRequest;
use App\DTO\Request\LoginRequest;
use App\DTO\Request\RegisterRequest;
use App\Exceptions\AppException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Helper\JwtHelper;
use App\Helper\RefreshCookie;
use App\Model\RefreshTokenModel;
use App\Model\UserModel;

/**
 * Access + refresh token authentication.
 *
 * - Access tokens are short-lived HS256 JWTs (stateless, validated by
 *   AuthenticationMiddleware).
 * - Refresh tokens are opaque random strings. Only their SHA-256 hash is
 *   stored; every use rotates the token inside its session family and reusing
 *   a rotated token revokes the whole family.
 */
final class AuthService
{
    /**
     * Valid BCrypt hash used to spend the same time on unknown emails as on a
     * wrong password, keeping login timing uniform.
     */
    private const string DUMMY_PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    public function __construct(
        private readonly UserModel $userModel,
        private readonly RefreshTokenModel $refreshTokenModel
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function register(RegisterRequest $dto, ?string $userAgent, ?string $ipAddress): array
    {
        if ($this->userModel->existsByEmail($dto->email)) {
            throw new ValidationException('Email is already registered.', [
                'email' => 'Email is already registered.',
            ]);
        }

        $userId = $this->userModel->create(
            $dto->name,
            $dto->email,
            password_hash($dto->password, PASSWORD_DEFAULT),
            UserType::STAFF
        );

        $user = $this->userModel->findById($userId);
        if ($user === null) {
            throw new AppException('The account could not be created.');
        }

        return $this->createSession($user, $userAgent, $ipAddress);
    }

    /**
     * @return array<string, mixed>
     */
    public function login(LoginRequest $dto, ?string $userAgent, ?string $ipAddress): array
    {
        $user = $this->userModel->findByEmail($dto->email);
        $hash = $user?->password_hash;
        $hash = is_string($hash) ? $hash : null;

        if ($hash === null || !password_verify($dto->password, $hash)) {
            if ($hash === null) {
                // Uniform timing for unknown accounts.
                password_verify($dto->password, self::DUMMY_PASSWORD_HASH);
            }

            throw new AppException('Invalid email or password.', HttpStatus::UNAUTHORIZED);
        }

        $this->userModel->touchLastLogin($user->id);
        $this->refreshTokenModel->revokeExpiredForUser($user->id);

        return $this->createSession($user, $userAgent, $ipAddress);
    }

    /**
     * Rotate a refresh token. Throws when the token is unknown, expired or has
     * already been rotated (reuse revokes the family).
     *
     * @return array<string, mixed>
     */
    public function refresh(string $token, ?string $userAgent, ?string $ipAddress): array
    {
        $row = $this->refreshTokenModel->findByHash($this->hashToken($token));
        if ($row === null) {
            throw new AppException('Session is invalid or has expired.', HttpStatus::UNAUTHORIZED);
        }

        if ($row->revoked_at !== null) {
            // A rotated token came back: assume the family leaked, kill it.
            $this->refreshTokenModel->revokeFamily((string) $row->family_id);

            throw new AppException('Session is no longer valid. Please sign in again.', HttpStatus::UNAUTHORIZED);
        }

        $expiresAt = strtotime((string) $row->expires_at);
        if ($expiresAt !== false && $expiresAt <= time()) {
            $this->refreshTokenModel->revoke((int) $row->id);

            throw new AppException('Session has expired. Please sign in again.', HttpStatus::UNAUTHORIZED);
        }

        $user = $this->userModel->findById($row->user_id);
        if ($user === null) {
            throw new AppException('The account is no longer available.', HttpStatus::UNAUTHORIZED);
        }

        $this->refreshTokenModel->revoke((int) $row->id);

        return $this->createSession($user, $userAgent, $ipAddress, (string) $row->family_id);
    }

    /**
     * Revoke the whole session family the token belongs to. Unknown tokens are
     * ignored so logout is always idempotent.
     */
    public function logout(string $token): void
    {
        $row = $this->refreshTokenModel->findByHash($this->hashToken($token));
        if ($row === null) {
            return;
        }

        $this->refreshTokenModel->revokeFamily((string) $row->family_id);
    }

    /**
     * @return array<string, mixed>
     */
    public function user(int|string $id): array
    {
        $user = $this->userModel->findById($id);
        if ($user === null) {
            throw new AppException('The account is no longer available.', HttpStatus::UNAUTHORIZED);
        }

        return $this->toPublicArray($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function updateProfile(int|string $id, string $name): array
    {
        if ($this->userModel->findById($id) === null) {
            throw new NotFoundException('User not found.');
        }

        $this->userModel->updateProfile($id, $name);

        return $this->user($id);
    }

    /**
     * Changing the password revokes every other session of the account; the
     * family behind the current refresh token stays signed in.
     */
    public function changePassword(
        int|string $id,
        ChangePasswordRequest $dto,
        ?string $currentRefreshToken = null
    ): void {
        $user = $this->userModel->findById($id);
        $hash = $user?->password_hash;
        $hash = is_string($hash) ? $hash : null;

        if ($hash === null || !password_verify($dto->currentPassword, $hash)) {
            throw new ValidationException('Current password is incorrect.', [
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        $this->userModel->updatePassword($id, password_hash($dto->newPassword, PASSWORD_DEFAULT));

        $keepFamily = null;
        if ($currentRefreshToken !== null) {
            $row = $this->refreshTokenModel->findByHash($this->hashToken($currentRefreshToken));
            $keepFamily = $row !== null ? (string) $row->family_id : null;
        }

        if ($keepFamily !== null) {
            $this->refreshTokenModel->revokeAllForUserExceptFamily($id, $keepFamily);
        } else {
            $this->refreshTokenModel->revokeAllForUser($id);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function createSession(
        object $user,
        ?string $userAgent,
        ?string $ipAddress,
        ?string $familyId = null
    ): array {
        $refreshToken = bin2hex(random_bytes(32));
        $this->refreshTokenModel->create(
            $user->id,
            $familyId ?? bin2hex(random_bytes(16)),
            $this->hashToken($refreshToken),
            date(DateFormat::DATETIME, time() + RefreshCookie::ttlSeconds()),
            $userAgent,
            $ipAddress
        );

        return [
            'access_token' => $this->buildAccessToken($user),
            'token_type' => 'Bearer',
            'expires_in' => $this->accessTokenLifetime(),
            'refresh_token' => $refreshToken,
            'user' => $this->toPublicArray($user),
        ];
    }

    private function buildAccessToken(object $user): string
    {
        return JwtHelper::buildToken([
            'id' => (int) $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'type' => $user->type,
        ], JwtHelper::accessTtl());
    }

    private function accessTokenLifetime(): int
    {
        $expiresAt = strtotime(JwtHelper::accessTtl());

        return $expiresAt === false ? 900 : max(60, $expiresAt - time());
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return array<string, mixed>
     */
    private function toPublicArray(object $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'type' => (string) $user->type,
            'last_login_at' => isset($user->last_login_at) ? (string) $user->last_login_at : null,
            'created_at' => isset($user->created_at) ? (string) $user->created_at : null,
        ];
    }
}
