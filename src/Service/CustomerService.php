<?php

declare(strict_types=1);

namespace App\Service;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Helper\Pagination;
use App\Model\CustomerModel;

final class CustomerService
{
    public function __construct(
        private readonly CustomerModel $customerModel
    ) {
    }

    /**
     * @return array{data: array<int, \stdClass>, total_data: int, total_page: int}
     */
    public function list(string $keywords, int $page, int $limit): array
    {
        $totalData = $this->customerModel->countGet($keywords);

        return [
            'data' => $this->customerModel->get($keywords, $page, $limit),
            'total_data' => $totalData,
            'total_page' => Pagination::totalPages($totalData, $limit),
        ];
    }

    public function create(string $name): void
    {
        if ($this->customerModel->existsByName($name)) {
            throw new ValidationException('Customer name already exists.', [
                'name' => 'Customer name already exists.',
            ]);
        }

        $this->customerModel->create($name);
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
    }

    public function delete(int|string $id): void
    {
        if (!$this->customerModel->exists($id)) {
            throw new NotFoundException('Customer not found.');
        }

        $this->customerModel->deleteById($id);
    }
}
