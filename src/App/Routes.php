<?php

declare(strict_types=1);

// Start Route
$app->get('/', 'App\Controller\Hello:getStatus')->setName('main');
$app->get('/hello', 'App\Controller\Hello:getStatusAPI')->setName('api.status');

// Swagger Route
$app->get('/swaggerui', 'App\Controller\Hello:openSwaggerUI')->setName('swagger_ui');

// Customer API Route
$app->get('/customer', 'App\Controller\Customer:get')->setName('api.customer.list');
$app->post('/customer/add', 'App\Controller\Customer:add')->setName('api.customer.add');
$app->post('/customer/update', 'App\Controller\Customer:update')->setName('api.customer.update');
$app->delete('/customer/delete', 'App\Controller\Customer:delete')->setName('api.customer.delete');
