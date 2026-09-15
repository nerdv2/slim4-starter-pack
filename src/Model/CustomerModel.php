<?php

declare(strict_types=1);

namespace App\Model;

final class CustomerModel extends BaseModel
{
    public function get(string $keywords = '', ?int $page = null, ?int $limit = null): array
    {
        $query = $this->db()->table('customer')
            ->select('customer.id', 'customer.name')
            ->whereNull('deleted_at');

        $this->applyKeywordSearch($query, 'customer.name', $keywords);
        $this->applyPagination($query, $page, $limit);

        return $query->orderBy('customer.id', 'asc')->get();
    }

    public function countGet(string $keywords = ''): int
    {
        $query = $this->db()->table('customer')->whereNull('deleted_at');
        $this->applyKeywordSearch($query, 'customer.name', $keywords);

        return $query->count();
    }

    public function exists(int|string $id): bool
    {
        return $this->existsById('customer', $id);
    }

    public function existsByName(string $name, int|string|null $exceptId = null): bool
    {
        $query = $this->db()->table('customer')
            ->where('customer.name', '=', $name)
            ->whereNull('deleted_at');

        if ($exceptId !== null) {
            $query->whereNot('customer.id', '=', $exceptId);
        }

        return $query->count() > 0;
    }

    public function create(string $name): int
    {
        return $this->db()->table('customer')->insert([
            'name' => $name,
            'created' => $this->now(),
        ]);
    }

    public function rename(int|string $id, string $name): int
    {
        return $this->db()->table('customer')
            ->where('customer.id', '=', $id)
            ->update(['name' => $name]);
    }

    public function deleteById(int|string $id): bool
    {
        return $this->softDelete('customer', 'id', $id);
    }
}
