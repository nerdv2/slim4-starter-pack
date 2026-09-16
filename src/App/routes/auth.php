<?php

declare(strict_types=1);

use App\Middleware\AuthenticationMiddleware;
use Slim\App;

return static function (App $app): void {
    $app->post('/auth/register', 'App\Controller\Auth:register')->setName('api.auth.register');
    $app->post('/auth/login', 'App\Controller\Auth:login')->setName('api.auth.login');
    $app->post('/auth/refresh', 'App\Controller\Auth:refresh')->setName('api.auth.refresh');
    $app->post('/auth/logout', 'App\Controller\Auth:logout')->setName('api.auth.logout');

    $app->get('/auth/me', 'App\Controller\Auth:me')
        ->setName('api.auth.me')
        ->add(new AuthenticationMiddleware());
    $app->put('/auth/profile', 'App\Controller\Auth:updateProfile')
        ->setName('api.auth.profile')
        ->add(new AuthenticationMiddleware());
    $app->post('/auth/change-password', 'App\Controller\Auth:changePassword')
        ->setName('api.auth.change_password')
        ->add(new AuthenticationMiddleware());
};
