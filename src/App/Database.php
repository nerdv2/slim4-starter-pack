<?php

declare(strict_types=1);

use Pimple\Container;

/** @var Container $container */
$container['db'] = static function () : Pecee\Pixie\Connection {
    $config = [
        'driver'    => 'mysql', // Db driver
        'host'      => $_SERVER['DB_HOST'],
        'database'  => $_SERVER['DB_NAME'],
        'username'  => $_SERVER['DB_USER'],
        'password'  => $_SERVER['DB_PASS'],
        'charset'   => 'utf8', // Optional
        'collation' => 'utf8_unicode_ci', // Optional
        'options'   => [ // PDO constructor options, optional
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ];
    $connection = new \Pecee\Pixie\Connection('mysql', $config);
    return $connection;
};

$container['db_read'] = static function () : Pecee\Pixie\Connection {
    $config = [
        'driver'    => 'mysql', // Db driver
        'host'      => $_SERVER['DB_HOST_READ'],
        'database'  => $_SERVER['DB_NAME_READ'],
        'username'  => $_SERVER['DB_USER_READ'],
        'password'  => $_SERVER['DB_PASS_READ'],
        'charset'   => 'utf8', // Optional
        'collation' => 'utf8_unicode_ci', // Optional
        'options'   => [ // PDO constructor options, optional
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ];
    $connection = new \Pecee\Pixie\Connection('mysql', $config);
    return $connection;
};