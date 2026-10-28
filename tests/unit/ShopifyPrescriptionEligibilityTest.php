<?php

namespace Tests\Unit;

use App\Services\Shopify\ShopifyPrescriptionEligibility;
use PHPUnit\Framework\TestCase;

final class ShopifyPrescriptionEligibilityTest extends TestCase
{
    /** @dataProvider orderabilityProvider */
    public function testOnlyCompletedWithNonExpiredValidityIsOrderable(array $prescription, bool $expected): void
    {
        self::assertSame($expected, ShopifyPrescriptionEligibility::isOrderable($prescription, '2026-10-27'));
    }

    /** @return array<string,array{0:array<string,mixed>,1:bool}> */
    public static function orderabilityProvider(): array
    {
        return [
            'completed future' => [['status' => 'completed', 'duration_days' => 30, 'valid_until' => '2026-10-28'], true],
            'approved excluded' => [['status' => 'approved', 'duration_days' => 30, 'valid_until' => '2026-10-28'], false],
            'finalized excluded' => [['status' => 'finalized', 'duration_days' => 30, 'valid_until' => '2026-10-28'], false],
            'active excluded' => [['status' => 'active', 'duration_days' => 30, 'valid_until' => '2026-10-28'], false],
            'completed expired' => [['status' => 'completed', 'duration_days' => 30, 'valid_until' => '2026-10-26'], false],
            'draft excluded' => [['status' => 'draft', 'duration_days' => 30, 'valid_until' => '2026-10-28'], false],
            'cancelled excluded' => [['status' => 'cancelled', 'duration_days' => 30, 'valid_until' => '2026-10-28'], false],
            'null validity excluded' => [['status' => 'completed', 'duration_days' => 30, 'valid_until' => null], false],
            'missing duration excluded' => [['status' => 'completed', 'valid_until' => '2026-10-28'], false],
        ];
    }

    public function testSafeExclusionReasonIsStable(): void
    {
        self::assertSame('PRESCRIPTION_NOT_ORDERABLE', ShopifyPrescriptionEligibility::REASON);
    }

    public function testValidUntilUsesCreatedAtAndPositiveDurationDays(): void
    {
        self::assertSame('2026-11-27', ShopifyPrescriptionEligibility::calculateValidUntil('2026-10-28 14:30:00', 30));
        self::assertNull(ShopifyPrescriptionEligibility::calculateValidUntil('2026-10-28 14:30:00', null));
        self::assertNull(ShopifyPrescriptionEligibility::calculateValidUntil('2026-10-28 14:30:00', 0));
        self::assertNull(ShopifyPrescriptionEligibility::calculateValidUntil('2026-10-28 14:30:00', -1));
        self::assertNull(ShopifyPrescriptionEligibility::calculateValidUntil('2026-10-28 14:30:00', 'invalid'));
    }
}
