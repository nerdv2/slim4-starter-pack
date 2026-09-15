<?php

declare(strict_types=1);

use App\Middleware\AuthenticationMiddleware;
use App\Middleware\AuthorizationMiddleware;
use App\Middleware\HealthTokenMiddleware;

/** @var \Slim\App $app */

// Start Route
$app->get('/', 'App\Controller\Hello:getStatus')->setName('main');
$app->get('/status', 'App\Controller\Hello:getStatusAPI')->setName('api.status');

// Swagger Route
$app->get('/swaggerui', 'App\Controller\Hello:openSwaggerUI')->setName('swagger_ui');

// Health check routes
$app->get('/health', 'App\Controller\Health:liveness')->setName('health.liveness');
$app->get('/health/ready', 'App\Controller\Health:readiness')->setName('health.readiness');
$app->get('/health/detailed', 'App\Controller\Health:detailed')
    ->setName('health.detailed')
    ->add(new HealthTokenMiddleware());

// Background job routes
$app->post('/admin/background-jobs/example', 'App\Controller\BackgroundJob:dispatchExample')
    ->setName('api.admin.background_jobs.dispatch_example')
    ->add(new AuthorizationMiddleware(['admin']))
    ->add(new AuthenticationMiddleware());
$app->get('/admin/background-jobs/{id}', 'App\Controller\BackgroundJob:status')
    ->setName('api.admin.background_jobs.status')
    ->add(new AuthorizationMiddleware(['admin']))
    ->add(new AuthenticationMiddleware());

// Customer API Route
$app->get('/customer', 'App\Controller\Customer:get')->setName('api.customer.list');
$app->post('/customer/add', 'App\Controller\Customer:add')
    ->setName('api.customer.add')
    ->add(new AuthorizationMiddleware(['admin']))
    ->add(new AuthenticationMiddleware());
$app->post('/customer/update', 'App\Controller\Customer:update')
    ->setName('api.customer.update')
    ->add(new AuthorizationMiddleware(['admin']))
    ->add(new AuthenticationMiddleware());
$app->delete('/customer/delete', 'App\Controller\Customer:delete')
    ->setName('api.customer.delete')
    ->add(new AuthorizationMiddleware(['admin']))
    ->add(new AuthenticationMiddleware());
