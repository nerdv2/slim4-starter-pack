<?php

declare(strict_types=1);

namespace App\Service;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Helper\CacheRedis;
use App\Helper\Pagination;
use App\Model\CustomerModel;

final class CustomerService
{
    /**
     * Customer list cache lifetime in seconds. Writes bump the namespace, so
     * this is only the upper bound for externally modified data.
     */
    private const int LIST_TTL = 300;

    public function __construct(
        private readonly CustomerModel $customerModel,
        private readonly CacheRedis $cache
    ) {
    }

    /**
     * @return array{data: array<int, mixed>, total_data: int, total_page: int}
     */
    public function list(string $keywords, int $page, int $limit): array
    {
        $cacheKey = $this->cache->namespaced(
            'customer',
            'list',
            sha1(strtolower(trim($keywords))) . ':' . $page . ':' . $limit
        );

        /** @var array{data: array<int, mixed>, total_data: int, total_page: int} $result */
        $result = $this->cache->rememberJson(
            $cacheKey,
            self::LIST_TTL,
            function () use ($keywords, $page, $limit): array {
                $totalData = $this->customerModel->countGet($keywords);

                return [
                    'data' => $this->customerModel->get($keywords, $page, $limit),
                    'total_data' => $totalData,
                    'total_page' => Pagination::totalPages($totalData, $limit),
                ];
            },
            cacheEmpty: true
        );

        return $result;
    }

    public function create(string $name): void
    {
        if ($this->customerModel->existsByName($name)) {
            throw new ValidationException('Customer name already exists.', [
                'name' => 'Customer name already exists.',
            ]);
        }

        $this->customerModel->create($name);
        $this->cache->bump('customer');
    }

    public function rename(int|string $id, string $name): void
    {
        if (!$this->customerModel->exists($id)) {
            throw new NotFoundException('Customer not found.');
        }

        if ($this->customerModel->existsByName($name, $id)) {
            throw new ValidationException('Customer name already exists.', [
                'name' => 'Customer name already exists.',
            ]);
        }

        $this->customerModel->rename($id, $name);
        $this->cache->bump('customer');
    }

    public function delete(int|string $id): void
    {
        if (!$this->customerModel->exists($id)) {
            throw new NotFoundException('Customer not found.');
        }

        $this->customerModel->deleteById($id);
        $this->cache->bump('customer');
    }
}
