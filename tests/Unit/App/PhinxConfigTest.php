<?php

declare(strict_types=1);

namespace Tests\Unit\App;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PhinxConfigTest extends TestCase
{
    /** @var array<string, string|false|null> */
    private array $originalEnvironment = [];

    protected function tearDown(): void
    {
        $this->restoreEnvironment();

        parent::tearDown();
    }

    public function testSqliteDriverProducesASqliteAdapterWithoutSuffix(): void
    {
        $config = $this->configWith(['DB_DRIVER' => 'sqlite', 'DB_NAME' => '/tmp/starter.sqlite']);
        $connection = $config['environments']['development'];

        self::assertIsArray($connection);
        self::assertSame('sqlite', $connection['adapter']);
        self::assertSame('/tmp/starter.sqlite', $connection['name']);
        self::assertSame('', $connection['suffix']);
    }

    public function testMysqlDriverKeepsMysqlConnectionSettings(): void
    {
        $config = $this->configWith([
            'DB_DRIVER' => 'mysql',
            'DB_HOST' => 'db.internal',
            'DB_PORT' => '3307',
            'DB_NAME' => 'starter',
            'DB_USER' => 'app',
            'DB_PASS' => 'secret',
        ]);
        $connection = $config['environments']['development'];

        self::assertIsArray($connection);
        self::assertSame('mysql', $connection['adapter']);
        self::assertSame('db.internal', $connection['host']);
        self::assertSame('3307', $connection['port']);
        self::assertSame('starter', $connection['name']);
        self::assertSame('app', $connection['user']);
        self::assertSame('secret', $connection['pass']);
        self::assertSame('utf8mb4', $connection['charset']);
    }

    public function testUnsupportedDriverIsRejected(): void
    {
        $this->overrideEnvironment(['DB_DRIVER' => 'pgsql']);

        $this->expectException(InvalidArgumentException::class);

        require dirname(__DIR__, 3) . '/phinx.php';
    }

    /**
     * @param array<string, string> $values
     * @return array<string, mixed>
     */
    private function configWith(array $values): array
    {
        $this->overrideEnvironment($values);

        /** @var array<string, mixed> $config */
        $config = require dirname(__DIR__, 3) . '/phinx.php';

        return $config;
    }

    /**
     * @param array<string, string> $values
     */
    private function overrideEnvironment(array $values): void
    {
        foreach ($values as $name => $value) {
            $this->originalEnvironment[$name] = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

            $_SERVER[$name] = $_ENV[$name] = $value;
            putenv($name . '=' . $value);
        }
    }

    private function restoreEnvironment(): void
    {
        foreach ($this->originalEnvironment as $name => $original) {
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);

            if (is_string($original)) {
                $_SERVER[$name] = $_ENV[$name] = $original;
                putenv($name . '=' . $original);
            }
        }

        $this->originalEnvironment = [];
    }
}
