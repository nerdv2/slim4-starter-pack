<?php

declare(strict_types=1);

use Oeltima\SimpleQuery\Connection;
use Oeltima\SimpleQuery\ConnectionOptions;
use Oeltima\SimpleQuery\Driver;
use Pimple\Container;

// Resolve the SimpleQuery driver separately from the PDO transport because
// MariaDB uses PDO's mysql driver.
$resolveDatabaseDriver = static function (string $driver): array {
    return match (strtolower($driver)) {
        'mysql' => [Driver::MySql, 'mysql'],
        'mariadb' => [Driver::MariaDb, 'mysql'],
        'sqlite' => [Driver::Sqlite, 'sqlite'],
        default => throw new InvalidArgumentException("Unsupported database driver: {$driver}"),
    };
};

$createDatabaseConnection = static function (
    string $driverName,
    string $host,
    string $port,
    string $database,
    string $username,
    string $password,
    string $label
) use ($resolveDatabaseDriver): Connection {
    [$driver, $pdoDriver] = $resolveDatabaseDriver($driverName);

    if ($driver === Driver::Sqlite) {
        return Connection::connect(
            driver: $driver,
            dsn: 'sqlite:' . $database,
            connectionOptions: new ConnectionOptions(label: $label)
        );
    }

    $dsn = sprintf(
        '%s:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $pdoDriver,
        $host,
        $port,
        $database
    );

    return Connection::connect(
        driver: $driver,
        dsn: $dsn,
        username: $username,
        password: $password,
        pdoOptions: [
            PDO::ATTR_TIMEOUT => 5,
        ],
        connectionOptions: new ConnectionOptions(label: $label)
    );
};

/** @var Container $container */
$container['db'] = static function () use ($createDatabaseConnection): Connection {
    return $createDatabaseConnection(
        (string) ($_SERVER['DB_DRIVER'] ?? 'mysql'),
        (string) ($_SERVER['DB_HOST'] ?? '127.0.0.1'),
        (string) ($_SERVER['DB_PORT'] ?? '3306'),
        (string) ($_SERVER['DB_NAME'] ?? ''),
        (string) ($_SERVER['DB_USER'] ?? ''),
        (string) ($_SERVER['DB_PASS'] ?? ''),
        'primary'
    );
};

$container['db_read'] = static function () use ($createDatabaseConnection): Connection {
    // Fall back to the primary when the replica env is not configured, so
    // get('db_read') is always safe to use (local development).
    $readSetting = static function (string $readKey, string $primaryKey, string $default = ''): string {
        $readValue = trim((string) ($_SERVER[$readKey] ?? ''));

        return $readValue !== '' ? $readValue : (string) ($_SERVER[$primaryKey] ?? $default);
    };

    return $createDatabaseConnection(
        (string) ($_SERVER['DB_DRIVER'] ?? 'mysql'),
        $readSetting('DB_HOST_READ', 'DB_HOST', '127.0.0.1'),
        $readSetting('DB_PORT_READ', 'DB_PORT', '3306'),
        $readSetting('DB_NAME_READ', 'DB_NAME'),
        $readSetting('DB_USER_READ', 'DB_USER'),
        $readSetting('DB_PASS_READ', 'DB_PASS'),
        'read-replica'
    );
};
