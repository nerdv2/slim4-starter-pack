<?php

declare(strict_types=1);

use App\Middleware\AuthenticationMiddleware;
use App\Middleware\AuthorizationMiddleware;
use Slim\App;

return static function (App $app): void {
    $app->post('/admin/background-jobs/example', 'App\Controller\BackgroundJob:dispatchExample')
        ->setName('api.admin.background_jobs.dispatch_example')
        ->add(new AuthorizationMiddleware(['admin']))
        ->add(new AuthenticationMiddleware());
    $app->get('/admin/background-jobs/{id}', 'App\Controller\BackgroundJob:status')
        ->setName('api.admin.background_jobs.status')
        ->add(new AuthorizationMiddleware(['admin']))
        ->add(new AuthenticationMiddleware());
};
