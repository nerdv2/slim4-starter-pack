<?php

declare(strict_types=1);

namespace Tests\Unit\Helper;

use App\Helper\Pagination;
use PHPUnit\Framework\TestCase;

final class PaginationTest extends TestCase
{
    public function testSanitizeUsesDefaults(): void
    {
        self::assertSame([1, Pagination::DEFAULT_LIMIT], Pagination::sanitize(null, null));
        self::assertSame([1, Pagination::DEFAULT_LIMIT], Pagination::sanitize('', ''));
        self::assertSame([1, Pagination::DEFAULT_LIMIT], Pagination::sanitize(-3, 0));
    }

    public function testSanitizeCastsAndClamps(): void
    {
        self::assertSame([2, 5], Pagination::sanitize('2', '5'));
        self::assertSame([1, Pagination::MAX_LIMIT], Pagination::sanitize(0, 9999));
        self::assertSame([3, Pagination::DEFAULT_LIMIT], Pagination::sanitize(3, 0));
    }

    public function testTotalPages(): void
    {
        self::assertSame(7, Pagination::totalPages(65, 10));
        self::assertSame(0, Pagination::totalPages(0, 10));
        self::assertSame(0, Pagination::totalPages(10, 0));
    }

    public function testOffset(): void
    {
        self::assertSame(0, Pagination::offset(1, 20));
        self::assertSame(20, Pagination::offset(3, 10));
    }

    public function testClamp(): void
    {
        self::assertSame(1, Pagination::clamp(0));
        self::assertSame(1, Pagination::clamp(-10));
        self::assertSame(100, Pagination::clamp(5000));
        self::assertSame(7, Pagination::clamp(7));
    }
}
