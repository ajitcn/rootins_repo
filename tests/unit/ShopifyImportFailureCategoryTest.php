<?php

namespace Tests\Unit;

use App\Services\Shopify\ShopifyOrderImportFailure;
use PHPUnit\Framework\TestCase;

final class ShopifyImportFailureCategoryTest extends TestCase
{
    public function testAllSafeFailureCategoriesAreWhitelisted(): void
    {
        foreach ([
            'CUSTOMER_NOT_LINKED', 'PRESCRIPTION_MISSING', 'MEDICINE_NOT_MAPPED', 'ORDER_NOT_FOUND',
            'ORDER_ALREADY_CANCELLED', 'ORDER_ALREADY_IMPORTED', 'INVALID_FULFILLMENT_STATE',
            'REFUND_ORDER_NOT_FOUND', 'STOCK_UNAVAILABLE', 'PAYMENT_STATE_INVALID',
            'DELIVERY_STATE_INVALID', 'UNKNOWN_IMPORT_FAILURE',
        ] as $category) {
            self::assertSame($category, ShopifyOrderImportFailure::normalize($category));
        }
    }

    public function testUnknownValuesNeverReachTheAdminOrWebhookRecord(): void
    {
        self::assertSame(ShopifyOrderImportFailure::UNKNOWN_IMPORT_FAILURE, ShopifyOrderImportFailure::normalize('database password leaked'));
    }

    /** @dataProvider exceptionCategories */
    public function testExceptionMappingCoversOperationalFailureCategories(string $message, string $topic, string $expected): void
    {
        self::assertSame($expected, ShopifyOrderImportFailure::fromException(new \RuntimeException($message), $topic)->category);
    }

    /** @return array<string, array{0:string,1:string,2:string}> */
    public static function exceptionCategories(): array
    {
        return [
            'prescription' => ['approved prescription is missing', 'orders/create', 'PRESCRIPTION_MISSING'],
            'order' => ['imported order not found', 'orders/updated', 'ORDER_NOT_FOUND'],
            'refund order' => ['imported order not found', 'refunds/create', 'REFUND_ORDER_NOT_FOUND'],
            'cancelled' => ['order already cancelled', 'orders/cancelled', 'ORDER_ALREADY_CANCELLED'],
            'fulfillment' => ['fulfillment state is invalid', 'fulfillments/update', 'INVALID_FULFILLMENT_STATE'],
            'payment' => ['payment transition is not allowed', 'orders/updated', 'PAYMENT_STATE_INVALID'],
            'delivery' => ['delivery transition is not allowed', 'fulfillments/update', 'DELIVERY_STATE_INVALID'],
            'unknown' => ['unexpected import failure', 'orders/create', 'UNKNOWN_IMPORT_FAILURE'],
        ];
    }

    /** @dataProvider fulfilmentResults */
    public function testFulfilmentFailuresMapToSafeCategories(array $result, string $expected): void
    {
        self::assertSame($expected, ShopifyOrderImportFailure::fromFulfilmentResult($result));
    }

    /** @return array<string, array{0:array<string,mixed>,1:string}> */
    public static function fulfilmentResults(): array
    {
        return [
            'already imported' => [['http' => 409, 'body' => ['msg' => 'A medicine order already exists for this prescription.']], ShopifyOrderImportFailure::ORDER_ALREADY_IMPORTED],
            'medicine mapping' => [['http' => 422, 'body' => ['msg' => 'Medicine is missing or inactive.']], ShopifyOrderImportFailure::MEDICINE_NOT_MAPPED],
            'stock' => [['http' => 422, 'body' => ['msg' => 'Available stock is insufficient or expired.']], ShopifyOrderImportFailure::STOCK_UNAVAILABLE],
            'unknown' => [['http' => 500, 'body' => ['msg' => 'Operation failed.']], ShopifyOrderImportFailure::UNKNOWN_IMPORT_FAILURE],
        ];
    }
}
