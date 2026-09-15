<?php

declare(strict_types=1);

namespace App\Helper;

use Oeltima\SimpleQuery\QueryBuilder;

final class Pagination
{
    public const int DEFAULT_LIMIT = 20;
    public const int MAX_LIMIT = 100;

    /**
     * Sanitize page/limit query parameters without changing the API contract.
     *
     * - page defaults to 1 and is clamped to a minimum of 1;
     * - limit defaults to $default when missing, empty or zero, and is clamped to [1, $max].
     *
     * @return array{int, int} [page, limit]
     */
    public static function sanitize(
        mixed $page,
        mixed $limit,
        int $default = self::DEFAULT_LIMIT,
        int $max = self::MAX_LIMIT
    ): array {
        $pageNumber = (int) ($page ?? 1);
        if ($pageNumber < 1) {
            $pageNumber = 1;
        }

        if ($limit === null || $limit === '' || (int) $limit <= 0) {
            $limitNumber = $default;
        } else {
            $limitNumber = (int) $limit;
        }

        if ($limitNumber < 1) {
            $limitNumber = $default;
        }
        if ($limitNumber > $max) {
            $limitNumber = $max;
        }

        return [$pageNumber, $limitNumber];
    }

    /**
     * Total page count without a division-by-zero error.
     */
    public static function totalPages(int $totalData, int $perPage): int
    {
        return ($totalData <= 0 || $perPage <= 0) ? 0 : (int) (($totalData - 1) / $perPage + 1);
    }

    /**
     * Offset for a 1-based page.
     */
    public static function offset(int $page, int $limit): int
    {
        return ($limit * $page) - $limit;
    }

    /**
     * Clamp a limit to [1, $max].
     */
    public static function clamp(mixed $limit, int $max = self::MAX_LIMIT): int
    {
        return min($max, max(1, (int) $limit));
    }

    /**
     * Add LIMIT/OFFSET to a query builder and return the effective limit.
     */
    public static function apply(
        QueryBuilder $query,
        int $page,
        int $limit,
        int $max = self::MAX_LIMIT
    ): int {
        $limit = self::clamp($limit, $max);
        $page = max(1, $page);

        $query->limit($limit)->offset(self::offset($page, $limit));

        return $limit;
    }
}
