<?php

declare(strict_types=1);

use App\Middleware\HealthTokenMiddleware;
use Slim\App;

return static function (App $app): void {
    $app->get('/health', 'App\Controller\Health:liveness')->setName('health.liveness');
    $app->get('/health/ready', 'App\Controller\Health:readiness')->setName('health.readiness');
    $app->get('/health/detailed', 'App\Controller\Health:detailed')
        ->setName('health.detailed')
        ->add(new HealthTokenMiddleware());
};
