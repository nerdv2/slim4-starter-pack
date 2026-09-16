<?php

declare(strict_types=1);

namespace App\Model;

use App\Constants\CustomerStatus;
use Oeltima\SimpleQuery\Cursor;
use Oeltima\SimpleQuery\QueryBuilder;

final class CustomerModel extends BaseModel
{
    /**
     * @return array<int, object>
     */
    public function get(
        string $keywords = '',
        ?string $status = null,
        ?int $page = null,
        ?int $limit = null
    ): array {
        $query = $this->baseQuery();
        $this->applyFilters($query, $keywords, $status);
        $this->applyPagination($query, $page, $limit);

        return $query->orderBy('customer.id', 'asc')->get();
    }

    public function countGet(string $keywords = '', ?string $status = null): int
    {
        $query = $this->db()->table('customer')->whereNull('customer.deleted_at');
        $this->applyFilters($query, $keywords, $status);

        return $query->count();
    }

    public function findById(int|string $id): ?object
    {
        return $this->baseQuery()
            ->where('customer.id', '=', $id)
            ->first();
    }

    public function exists(int|string $id): bool
    {
        return $this->existsById('customer', $id);
    }

    public function existsByName(string $name, int|string|null $exceptId = null): bool
    {
        return $this->existsByColumn('name', $name, $exceptId);
    }

    public function existsByEmail(string $email, int|string|null $exceptId = null): bool
    {
        return $this->existsByColumn('email', $email, $exceptId);
    }

    /**
     * @param array<string, mixed> $data Allowlisted columns only.
     */
    public function create(array $data): int
    {
        return (int) $this->db()->table('customer')->insertGetId($data + [
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ]);
    }

    /**
     * Bulk insert used by the CSV import job (one insert per validated chunk).
     *
     * @param list<array<string, mixed>> $rows
     */
    public function createMany(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        return $this->db()->table('customer')->insertMany($rows);
    }

    /**
     * @param array<string, mixed> $data Allowlisted columns only.
     */
    public function update(int|string $id, array $data): int
    {
        return $this->db()->table('customer')
            ->where('customer.id', '=', $id)
            ->update($data + ['updated_at' => $this->now()]);
    }

    public function deleteById(int|string $id): bool
    {
        return $this->softDelete('customer', 'id', $id);
    }

    /**
     * Customer count grouped by status, plus the total.
     *
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $counts = [];
        foreach (CustomerStatus::ALL as $status) {
            $counts[$status] = $this->countGet('', $status);
        }
        $counts['total'] = $this->countGet();

        return $counts;
    }

    public function countCreatedSince(string $since): int
    {
        return $this->db()->table('customer')
            ->where('customer.created_at', '>=', $since)
            ->whereNull('customer.deleted_at')
            ->count();
    }

    /**
     * Stream matching customers without loading the table into memory.
     *
     * @return Cursor<array<string, mixed>>
     */
    public function iterateForExport(string $keywords = '', ?string $status = null): Cursor
    {
        $query = $this->baseQuery();
        $this->applyFilters($query, $keywords, $status);

        return $query->orderBy('customer.id', 'asc')->iterateAssociative();
    }

    private function baseQuery(): QueryBuilder
    {
        return $this->db()->table('customer')
            ->select(
                'customer.id',
                'customer.name',
                'customer.email',
                'customer.phone',
                'customer.company',
                'customer.status',
                'customer.address',
                'customer.notes',
                'customer.avatar_path',
                'customer.created_at',
                'customer.updated_at'
            )
            ->whereNull('customer.deleted_at');
    }

    private function applyFilters(QueryBuilder $query, string $keywords, ?string $status): void
    {
        $this->applyKeywordSearch($query, ['customer.name', 'customer.email', 'customer.company'], $keywords);

        if ($status !== null && $status !== '') {
            $query->where('customer.status', '=', $status);
        }
    }

    private function existsByColumn(string $column, string $value, int|string|null $exceptId = null): bool
    {
        $query = $this->db()->table('customer')
            ->where('customer.' . $column, '=', $value)
            ->whereNull('customer.deleted_at');

        if ($exceptId !== null) {
            $query->whereNot('customer.id', '=', $exceptId);
        }

        return $query->count() > 0;
    }
}
