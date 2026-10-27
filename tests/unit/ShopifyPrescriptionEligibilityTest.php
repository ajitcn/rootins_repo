<?php

namespace Tests\Unit;

use App\Services\Shopify\ShopifyPrescriptionEligibility;
use PHPUnit\Framework\TestCase;

final class ShopifyPrescriptionEligibilityTest extends TestCase
{
    /** @dataProvider orderabilityProvider */
    public function testOnlyApprovedOrFinalizedWithNonExpiredValidityIsOrderable(array $prescription, bool $expected): void
    {
        self::assertSame($expected, ShopifyPrescriptionEligibility::isOrderable($prescription, '2026-10-27'));
    }

    /** @return array<string,array{0:array<string,mixed>,1:bool}> */
    public static function orderabilityProvider(): array
    {
        return [
            'approved future' => [['status' => 'approved', 'valid_until' => '2026-10-28'], true],
            'finalized future' => [['status' => 'finalized', 'valid_until' => '2026-10-28'], true],
            'active excluded' => [['status' => 'active', 'valid_until' => '2026-10-28'], false],
            'completed excluded' => [['status' => 'completed', 'valid_until' => '2026-10-28'], false],
            'draft excluded' => [['status' => 'draft', 'valid_until' => '2026-10-28'], false],
            'cancelled excluded' => [['status' => 'cancelled', 'valid_until' => '2026-10-28'], false],
            'expired excluded' => [['status' => 'approved', 'valid_until' => '2026-10-26'], false],
            'null validity excluded' => [['status' => 'approved', 'valid_until' => null], false],
        ];
    }

    public function testSafeExclusionReasonIsStable(): void
    {
        self::assertSame('PRESCRIPTION_NOT_ORDERABLE', ShopifyPrescriptionEligibility::REASON);
    }
}
