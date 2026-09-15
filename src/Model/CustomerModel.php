<?php

declare(strict_types=1);

namespace App\Model;

final class CustomerModel extends BaseModel
{
    public function get(string $keywords = '', ?int $page = null, ?int $limit = null): array
    {
        $query = $this->db()->table('customer')
            ->select('customer.id', 'customer.name');

        $this->applyKeywordSearch($query, 'customer.name', $keywords);
        $this->applyPagination($query, $page, $limit);

        return $query->orderBy('customer.id', 'asc')->get();
    }

    public function count_get(string $keywords = ''): int
    {
        $query = $this->db()->table('customer');
        $this->applyKeywordSearch($query, 'customer.name', $keywords);

        return $query->count();
    }

    public function add(string $name): bool
    {
        $exists = $this->db()->table('customer')
            ->where('customer.name', '=', $name)
            ->count();

        if ($exists > 0) {
            return false;
        }

        return $this->db()->table('customer')->insert([
            'name' => $name,
            'created' => $this->now(),
        ]) > 0;
    }

    public function update(int|string $id, string $name): bool
    {
        $conflict = $this->db()->table('customer')
            ->where('customer.name', '=', $name)
            ->whereNot('customer.id', '=', $id)
            ->count();

        if ($conflict === 0) {
            $this->db()->table('customer')
                ->where('customer.id', '=', $id)
                ->update(['name' => $name]);
        }

        return true;
    }

    public function delete(int|string $id): bool
    {
        $this->db()->table('customer')
            ->where('customer.id', '=', $id)
            ->delete();

        return true;
    }
}
