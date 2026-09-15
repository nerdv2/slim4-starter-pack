<?php

declare(strict_types=1);

use App\Jobs\ExampleJob;
use App\Model\CustomerModel;
use App\Service\CustomerService;
use Oeltima\SimpleQueue\JobDispatcher;
use Oeltima\SimpleQueue\JobRegistry;
use Oeltima\SimpleQueue\QueueManager;
use Oeltima\SimpleQueue\Storage\PdoJobStorage;
use Oeltima\SimpleQuery\Connection;
use Pimple\Container;
use Pimple\Psr11\Container as Psr11Container;

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

$container[ExampleJob::class] = static function (): ExampleJob {
    return new ExampleJob();
};

$container['jobRegistry'] = static function (Container $container): JobRegistry {
    $registry = new JobRegistry(new Psr11Container($container));
    $registry->register('example.hello', ExampleJob::class);

    return $registry;
};

$container['jobDispatcher'] = static function (Container $container): JobDispatcher {
    return new JobDispatcher($container['jobStorage'], $container['queueManager']);
};
