<?php

declare(strict_types=1);

// Phinx configuration for the test suite (SQLite).
//
// Run with: vendor/bin/phinx -c phinx-testing.php migrate
// The test suite, .env.testing and the related composer scripts are wired in a
// later phase; this file only provides the configuration.

return [
    'paths' => [
        'migrations' => __DIR__ . '/db/migrations',
        'seeds' => __DIR__ . '/db/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'testing',
        'testing' => [
            'adapter' => 'sqlite',
            'name' => __DIR__ . '/storage/test_database.sqlite',
            // Phinx appends ".sqlite3" to SQLite paths by default; the
            // application connects to the exact DB_NAME, so keep it empty.
            'suffix' => '',
        ],
    ],
    'version_order' => 'creation',
];
