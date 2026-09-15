<?php

declare(strict_types=1);

use App\Model\CustomerModel;
use App\Service\CustomerService;
use Oeltima\SimpleQuery\Connection;
use Pimple\Container;

/** @var Container $container */
$container['customerModel'] = static function (Container $container): CustomerModel {
    /** @var Connection $db */
    $db = $container['db'];

    return new CustomerModel($db);
};

$container['customerService'] = static function (Container $container): CustomerService {
    /** @var CustomerModel $model */
    $model = $container['customerModel'];

    return new CustomerService($model);
};
