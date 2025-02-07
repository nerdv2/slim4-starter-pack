<?php

declare(strict_types=1);

// Start Route
$app->get('/', 'App\Controller\Hello:getStatus')->setName('main');
$app->get('/hello', 'App\Controller\Hello:getStatusAPI')->setName('main_api');

// Swagger Route
$app->get('/swaggerui', 'App\Controller\Hello:openSwaggerUI');