<?php

declare(strict_types=1);

namespace App\Model;

final class CustomerModel extends BaseModel
{
    public function get($keywords = ''): array
    {
        $query = $this->db()->table('customer')
            ->select('customer.id', 'customer.name');

        $this->applyKeywordSearch($query, 'customer.name', (string) $keywords);

        return $query->get();
    }

    public function add($name): bool
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

    public function update($id, $name): bool
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

    public function delete($id): bool
    {
        $this->db()->table('customer')
            ->where('customer.id', '=', $id)
            ->delete();

        return true;
    }
}
