<?php

$envConfig = parse_ini_file('.env', false, INI_SCANNER_RAW);

return
[
    'paths' => [
        'migrations' => 'db/migrations',
        'seeds' => 'db/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'mysql',
            'host' => $envConfig['DB_HOST'],
            'name' => $envConfig['DB_NAME'],
            'user' => $envConfig['DB_USER'],
            'pass' => $envConfig['DB_PASS'],
            'port' => '3306',
            'charset' => 'utf8',
        ]
    ],
    'version_order' => 'creation'
];
