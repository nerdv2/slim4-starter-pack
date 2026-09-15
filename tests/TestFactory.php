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
     * @return array{name: string, created: string}
     */
    public static function customer(array $overrides = []): array
    {
        return array_merge([
            'name' => self::faker()->unique()->company(),
            'created' => date('Y-m-d H:i:s'),
        ], $overrides);
    }
}
