<?php

declare(strict_types=1);

use App\Middleware\AuthenticationMiddleware;
use App\Middleware\AuthorizationMiddleware;
use Slim\App;

return static function (App $app): void {
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
};
