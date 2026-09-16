<?php

declare(strict_types=1);

namespace Tests;

use Faker\Factory;
use Faker\Generator;

final class TestFactory
{
    private static ?Generator $faker = null;

    public static function faker(): Generator
    {
        return self::$faker ??= Factory::create();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function customer(array $overrides = []): array
    {
        return array_merge([
            'name' => self::faker()->unique()->company(),
            'email' => self::faker()->unique()->safeEmail(),
            'phone' => self::faker()->phoneNumber(),
            'company' => self::faker()->company(),
            'status' => 'lead',
            'address' => null,
            'notes' => null,
            'avatar_path' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'deleted_at' => null,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function user(array $overrides = []): array
    {
        return array_merge([
            'name' => self::faker()->name(),
            'email' => self::faker()->unique()->safeEmail(),
            'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
            'type' => 'staff',
            'last_login_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'deleted_at' => null,
        ], $overrides);
    }
}
