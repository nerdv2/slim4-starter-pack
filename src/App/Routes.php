<?php

declare(strict_types=1);

// Start Route
$app->get('/', 'App\Controller\Hello:getStatus')->setName('main');
$app->get('/hello', 'App\Controller\Hello:getStatusAPI')->setName('main_api');

// Swagger Route
$app->get('/swaggerui', 'App\Controller\Hello:openSwaggerUI');

// Customer API Route
$app->get('/customer', 'App\Controller\Customer:get');
$app->post('/customer/add', 'App\Controller\Customer:add');
$app->post('/customer/update', 'App\Controller\Customer:update');
$app->delete('/customer/delete', 'App\Controller\Customer:delete');