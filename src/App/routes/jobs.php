<?php

declare(strict_types=1);

use App\Middleware\AuthenticationMiddleware;
use Slim\App;

return static function (App $app): void {
    $app->get('/jobs/{id}', 'App\Controller\Job:status')
        ->setName('api.jobs.status')
        ->add(new AuthenticationMiddleware());
};
