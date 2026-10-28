<?php

namespace Tests\Unit;

use Admin\Services\DashboardService;
use CodeIgniter\Test\CIUnitTestCase;

class AdminDashboardMaximumTest extends CIUnitTestCase
{
    public function testArrayInputIsNormalizedAndHasMaximum(): void
    {
        self::assertSame([2, 9], DashboardService::normalizeNumericValues([2, '9', 'not-a-number']));
        self::assertSame(9, DashboardService::maximumNumericValue([2, '9', 'not-a-number']));
    }

    public function testIntegerInputIsTreatedAsOneValue(): void
    {
        self::assertSame([7], DashboardService::normalizeNumericValues(7));
        self::assertSame(7, DashboardService::maximumNumericValue(7));
    }

    public function testNullInputUsesTheSafeDefault(): void
    {
        self::assertSame([], DashboardService::normalizeNumericValues(null));
        self::assertSame(0, DashboardService::maximumNumericValue(null));
    }

    public function testEmptyArrayUsesTheSafeDefault(): void
    {
        self::assertSame([], DashboardService::normalizeNumericValues([]));
        self::assertSame(0, DashboardService::maximumNumericValue([]));
    }
}
