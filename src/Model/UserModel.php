<?php

declare(strict_types=1);

namespace App\Model;

final class UserModel extends BaseModel
{
    public function findById(int|string $id): ?object
    {
        return $this->db()->table('user')
            ->select(
                'user.id',
                'user.name',
                'user.email',
                'user.password_hash',
                'user.type',
                'user.last_login_at',
                'user.created_at',
                'user.updated_at'
            )
            ->where('user.id', '=', $id)
            ->whereNull('user.deleted_at')
            ->first();
    }

    public function findByEmail(string $email): ?object
    {
        return $this->db()->table('user')
            ->select(
                'user.id',
                'user.name',
                'user.email',
                'user.password_hash',
                'user.type',
                'user.last_login_at',
                'user.created_at',
                'user.updated_at'
            )
            ->where('user.email', '=', $email)
            ->whereNull('user.deleted_at')
            ->first();
    }

    public function existsByEmail(string $email): bool
    {
        return $this->db()->table('user')
            ->where('user.email', '=', $email)
            ->whereNull('user.deleted_at')
            ->count() > 0;
    }

    public function create(string $name, string $email, string $passwordHash, string $type): int
    {
        return (int) $this->db()->table('user')->insertGetId([
            'name' => $name,
            'email' => $email,
            'password_hash' => $passwordHash,
            'type' => $type,
            'last_login_at' => null,
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ]);
    }

    public function updateProfile(int|string $id, string $name): int
    {
        return $this->db()->table('user')
            ->where('user.id', '=', $id)
            ->update([
                'name' => $name,
                'updated_at' => $this->now(),
            ]);
    }

    public function updatePassword(int|string $id, string $passwordHash): int
    {
        return $this->db()->table('user')
            ->where('user.id', '=', $id)
            ->update([
                'password_hash' => $passwordHash,
                'updated_at' => $this->now(),
            ]);
    }

    public function touchLastLogin(int|string $id): int
    {
        return $this->db()->table('user')
            ->where('user.id', '=', $id)
            ->update([
                'last_login_at' => $this->now(),
            ]);
    }
}
