<?php

declare(strict_types=1);

namespace App\Helper;

final class CacheRedis
{
    public $client;

    public function __construct()
    {
        $REDIS_SERVER_HOST = isset($_SERVER['REDIS_SERVER_HOST']) ? $_SERVER['REDIS_SERVER_HOST'] : '';
        $REDIS_SERVER_PORT = isset($_SERVER['REDIS_SERVER_PORT']) ? $_SERVER['REDIS_SERVER_PORT'] : '';
        $REDIS_SERVER_DATABASE = isset($_SERVER['REDIS_SERVER_DATABASE']) ? $_SERVER['REDIS_SERVER_DATABASE'] : 0;
        $REDIS_SERVER_PASSWORD = isset($_SERVER['REDIS_SERVER_PASSWORD']) ? $_SERVER['REDIS_SERVER_PASSWORD'] : null;

        $config = [
            'schema' => 'tcp',
            'host' => $REDIS_SERVER_HOST,
            'port' => $REDIS_SERVER_PORT,
            'database' => $REDIS_SERVER_DATABASE,
            'password' => $REDIS_SERVER_PASSWORD
        ];
        $this->client = new \Predis\Client($config);
    }

    public function get($key)
    {
        return $this->client->get($key);
    }

    public function set($key, $value, $expire = null)
    {
        if (!empty($expire)) {
            $this->client->setex($key, $expire, $value);
        } else {
            $this->client->set($key, $value);
        }
    }

    public function del($key)
    {
        $this->client->del($key);
    }
}
