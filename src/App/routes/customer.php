<?php

declare(strict_types=1);

use App\Constants\UserType;
use App\Middleware\AuthenticationMiddleware;
use App\Middleware\AuthorizationMiddleware;
use Slim\App;

return static function (App $app): void {
    // Reads: any authenticated user (admin or staff).
    $app->get('/customer', 'App\Controller\Customer:list')
        ->setName('api.customer.list')
        ->add(new AuthenticationMiddleware());
    $app->get('/customer/stats', 'App\Controller\Customer:stats')
        ->setName('api.customer.stats')
        ->add(new AuthenticationMiddleware());
    $app->get('/customer/export/{id}/download', 'App\Controller\CustomerTransfer:download')
        ->setName('api.customer.export.download')
        ->add(new AuthenticationMiddleware());
    $app->get('/customer/{id}', 'App\Controller\Customer:show')
        ->setName('api.customer.show')
        ->add(new AuthenticationMiddleware());

    // Exports are reads; imports and writes require an admin token.
    $app->post('/customer/export', 'App\Controller\CustomerTransfer:export')
        ->setName('api.customer.export')
        ->add(new AuthenticationMiddleware());
    $app->post('/customer/import', 'App\Controller\CustomerTransfer:import')
        ->setName('api.customer.import')
        ->add(new AuthorizationMiddleware([UserType::ADMIN]))
        ->add(new AuthenticationMiddleware());

    $app->post('/customer', 'App\Controller\Customer:create')
        ->setName('api.customer.create')
        ->add(new AuthorizationMiddleware([UserType::ADMIN]))
        ->add(new AuthenticationMiddleware());
    $app->put('/customer/{id}', 'App\Controller\Customer:update')
        ->setName('api.customer.update')
        ->add(new AuthorizationMiddleware([UserType::ADMIN]))
        ->add(new AuthenticationMiddleware());
    $app->delete('/customer/{id}', 'App\Controller\Customer:delete')
        ->setName('api.customer.delete')
        ->add(new AuthorizationMiddleware([UserType::ADMIN]))
        ->add(new AuthenticationMiddleware());
    $app->post('/customer/{id}/avatar', 'App\Controller\Customer:uploadAvatar')
        ->setName('api.customer.avatar.upload')
        ->add(new AuthorizationMiddleware([UserType::ADMIN]))
        ->add(new AuthenticationMiddleware());
    $app->delete('/customer/{id}/avatar', 'App\Controller\Customer:removeAvatar')
        ->setName('api.customer.avatar.remove')
        ->add(new AuthorizationMiddleware([UserType::ADMIN]))
        ->add(new AuthenticationMiddleware());
};
