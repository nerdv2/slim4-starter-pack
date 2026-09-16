<?php

/**
 * Route manifest.
 *
 * One file per domain under src/App/routes/; each file returns a closure that
 * registers its routes. Add new files to the manifest below.
 */

declare(strict_types=1);

use Slim\App;

return static function (App $app): void {
    /** @var list<string> $manifest */
    $manifest = [
        'routes/core.php',
        'routes/health.php',
        'routes/customer.php',
        'routes/background_jobs.php',
    ];

    foreach ($manifest as $file) {
        /** @var callable(App): void $register */
        $register = require __DIR__ . '/' . $file;
        $register($app);
    }
};
