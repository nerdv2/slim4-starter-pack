<?php

declare(strict_types=1);

namespace App\Model;

use App\Constants\DateFormat;
use App\Helper\Pagination;
use App\Interfaces\ModelInterface;
use InvalidArgumentException;
use Oeltima\SimpleQuery\ConditionGroup;
use Oeltima\SimpleQuery\Connection;
use Oeltima\SimpleQuery\Expression\RawExpression;
use Oeltima\SimpleQuery\QueryBuilder;
use PDO;

abstract class BaseModel implements ModelInterface
{
    public function __construct(
        protected readonly Connection $database
    ) {
    }

    protected function db(): Connection
    {
        return $this->database;
    }

    public function pdo(): PDO
    {
        return $this->database->pdo();
    }

    /**
     * Run a callback inside a managed transaction (nested calls use savepoints).
     *
     * @template T
     * @param \Closure(Connection): T $callback
     * @return T
     */
    public function transaction(\Closure $callback): mixed
    {
        return $this->database->transaction($callback);
    }

    protected function now(): string
    {
        return date(DateFormat::DATETIME);
    }

    protected function existsById(
        string $table,
        int|string $id,
        string $idColumn = 'id',
        bool $excludeDeleted = true
    ): bool {
        $query = $this->db()->table($table)->where($idColumn, '=', $id);
        if ($excludeDeleted) {
            $query->whereNull('deleted_at');
        }

        return $query->first() !== null;
    }

    protected function softDelete(string $table, string $idColumn, int|string $id): bool
    {
        return $this->db()->table($table)
            ->where($idColumn, '=', $id)
            ->update(['deleted_at' => $this->now()]) > 0;
    }

    /**
     * @param list<int|string> $ids
     */
    protected function softDeleteBulk(string $table, string $idColumn, array $ids): bool
    {
        if ($ids === []) {
            return true;
        }

        return $this->db()->table($table)
            ->whereIn($idColumn, $ids)
            ->update(['deleted_at' => $this->now()]) > 0;
    }

    protected function escapeLikeKeyword(string $keyword): string
    {
        return addcslashes($keyword, '%_\\');
    }

    /**
     * Add a case-insensitive LIKE predicate across the given columns.
     *
     * The explicit ESCAPE clause keeps `%` and `_` in the keyword literal on
     * every supported driver (MySQL treats backslash as an escape by default,
     * SQLite does not).
     *
     * @param string|list<string> $column
     */
    protected function applyKeywordSearch(QueryBuilder $query, string|array $column, ?string $keywords): QueryBuilder
    {
        $columns = is_array($column) ? $column : [$column];
        $keywords = trim((string) $keywords);

        if ($keywords === '' || $columns === []) {
            return $query;
        }

        $pattern = '%' . strtolower($this->escapeLikeKeyword($keywords)) . '%';

        $query->where(function (ConditionGroup $group) use ($columns, $pattern): void {
            foreach ($columns as $index => $column) {
                $expression = $this->columnExpression('LOWER', $column);
                $predicate = $this->db()->raw(
                    $expression->sql . " LIKE ? ESCAPE '\\'",
                    [$pattern]
                );

                if ($index === 0) {
                    $group->where($predicate);
                    continue;
                }

                $group->orWhere($predicate);
            }
        });

        return $query;
    }

    /**
     * Leave the query unchanged only when both values are null; a partial
     * page/limit pair is a programming error.
     */
    protected function applyPagination(QueryBuilder $query, ?int $page, ?int $limit): QueryBuilder
    {
        if ($page === null && $limit === null) {
            return $query;
        }

        if ($page === null || $limit === null) {
            throw new InvalidArgumentException('Page and limit must be provided together.');
        }

        Pagination::apply($query, $page, $limit);

        return $query;
    }

    /**
     * Create an allowlisted function expression for a validated column identifier.
     */
    protected function columnExpression(string $function, string $column): RawExpression
    {
        $normalizedFunction = strtoupper($function);
        if (!in_array($normalizedFunction, ['DATE', 'LOWER', 'MONTH', 'YEAR'], true)) {
            throw new InvalidArgumentException('Unsupported SQL column function: ' . $function);
        }

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/D', $column) !== 1) {
            throw new InvalidArgumentException('Invalid SQL column identifier: ' . $column);
        }

        return $this->database->raw($normalizedFunction . '(' . $column . ')');
    }
}
