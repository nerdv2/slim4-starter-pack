<?php

declare(strict_types=1);

use App\Helper\CacheRedis;
use App\Helper\UploadHelper;
use App\Jobs\CustomerExportJob;
use App\Jobs\CustomerImportJob;
use App\Model\CustomerModel;
use App\Model\RefreshTokenModel;
use App\Model\UserModel;
use App\Service\AuthService;
use App\Service\CustomerService;
use App\Service\CustomerTransferService;
use Oeltima\SimpleQueue\JobDispatcher;
use Oeltima\SimpleQueue\JobRegistry;
use Oeltima\SimpleQueue\QueueManager;
use Oeltima\SimpleQueue\Storage\PdoJobStorage;
use Oeltima\SimpleQuery\Connection;
use Pimple\Container;
use Pimple\Psr11\Container as Psr11Container;

/** @var Container $container */
$container['cacheRedis'] = static function (): CacheRedis {
    return new CacheRedis();
};

$container['uploadHelper'] = static function (): UploadHelper {
    // Public filesystem uploads (customer avatars); S3 when DEFAULT_UPLOAD_TARGET=s3.
    return new UploadHelper();
};

$container['userModel'] = static function (Container $container): UserModel {
    /** @var Connection $db */
    $db = $container['db'];

    return new UserModel($db);
};

$container['refreshTokenModel'] = static function (Container $container): RefreshTokenModel {
    /** @var Connection $db */
    $db = $container['db'];

    return new RefreshTokenModel($db);
};

$container['authService'] = static function (Container $container): AuthService {
    /** @var UserModel $userModel */
    $userModel = $container['userModel'];
    /** @var RefreshTokenModel $refreshTokenModel */
    $refreshTokenModel = $container['refreshTokenModel'];

    return new AuthService($userModel, $refreshTokenModel);
};

$container['customerModel'] = static function (Container $container): CustomerModel {
    /** @var Connection $db */
    $db = $container['db'];

    return new CustomerModel($db);
};

$container['customerService'] = static function (Container $container): CustomerService {
    /** @var CustomerModel $model */
    $model = $container['customerModel'];
    /** @var CacheRedis $cache */
    $cache = $container['cacheRedis'];
    /** @var UploadHelper $uploadHelper */
    $uploadHelper = $container['uploadHelper'];

    return new CustomerService($model, $cache, $uploadHelper);
};

$container['jobStorage'] = static function (Container $container): PdoJobStorage {
    // Reuse the application connection so the queue always targets the same database.
    return new PdoJobStorage(
        static function () use ($container): \PDO {
            /** @var Connection $connection */
            $connection = $container['db'];

            return $connection->pdo();
        },
        'background_job'
    );
};

$container['queueManager'] = static function (Container $container): QueueManager {
    $driver = strtolower(trim((string) ($_SERVER['QUEUE_DRIVER'] ?? 'auto')));
    if ($driver === 'database') {
        $driver = 'db';
    }
    if (!in_array($driver, ['auto', 'db', 'redis'], true)) {
        throw new InvalidArgumentException('QUEUE_DRIVER must be auto, redis, database or db.');
    }

    // Redis delivery is not wired yet; "auto" falls back to database polling.
    return QueueManager::create(
        $driver,
        null,
        $container['jobStorage'],
        (string) ($_SERVER['QUEUE_REDIS_PREFIX'] ?? 'slim4')
    );
};

$container['jobDispatcher'] = static function (Container $container): JobDispatcher {
    return new JobDispatcher($container['jobStorage'], $container['queueManager']);
};

$container['customerTransferService'] = static function (Container $container): CustomerTransferService {
    /** @var CustomerModel $model */
    $model = $container['customerModel'];
    /** @var JobDispatcher $jobDispatcher */
    $jobDispatcher = $container['jobDispatcher'];

    return new CustomerTransferService($model, $jobDispatcher);
};

$container[CustomerExportJob::class] = static function (Container $container): CustomerExportJob {
    /** @var CustomerModel $model */
    $model = $container['customerModel'];

    return new CustomerExportJob($model);
};

$container[CustomerImportJob::class] = static function (Container $container): CustomerImportJob {
    /** @var CustomerModel $model */
    $model = $container['customerModel'];

    return new CustomerImportJob($model);
};

$container['jobRegistry'] = static function (Container $container): JobRegistry {
    $registry = new JobRegistry(new Psr11Container($container));
    $registry->register('customer.export', CustomerExportJob::class);
    $registry->register('customer.import', CustomerImportJob::class);

    return $registry;
};
