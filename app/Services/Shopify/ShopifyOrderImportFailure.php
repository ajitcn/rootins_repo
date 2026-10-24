<?php

namespace App\Services\Shopify;

use RuntimeException;
use Throwable;

final class ShopifyOrderImportFailure extends RuntimeException
{
    public const CUSTOMER_NOT_LINKED = 'CUSTOMER_NOT_LINKED';
    public const PRESCRIPTION_MISSING = 'PRESCRIPTION_MISSING';
    public const MEDICINE_NOT_MAPPED = 'MEDICINE_NOT_MAPPED';
    public const MEDICINE_INACTIVE = 'MEDICINE_INACTIVE';
    public const ORDER_NOT_FOUND = 'ORDER_NOT_FOUND';
    public const ORDER_IMPORT_RECORD_NOT_FOUND = 'ORDER_IMPORT_RECORD_NOT_FOUND';
    public const SHOPIFY_ORDER_IMPORT_STATUS_INVALID = 'SHOPIFY_ORDER_IMPORT_STATUS_INVALID';
    public const PHARMACY_ORDER_NOT_CREATED = 'PHARMACY_ORDER_NOT_CREATED';
    public const ORDER_ALREADY_CANCELLED = 'ORDER_ALREADY_CANCELLED';
    public const ORDER_ALREADY_IMPORTED = 'ORDER_ALREADY_IMPORTED';
    public const INVALID_FULFILLMENT_STATE = 'INVALID_FULFILLMENT_STATE';
    public const REFUND_ORDER_NOT_FOUND = 'REFUND_ORDER_NOT_FOUND';
    public const STOCK_UNAVAILABLE = 'STOCK_UNAVAILABLE';
    public const PAYMENT_STATE_INVALID = 'PAYMENT_STATE_INVALID';
    public const DELIVERY_STATE_INVALID = 'DELIVERY_STATE_INVALID';
    public const BILLING_CLINIC_UNAVAILABLE = 'BILLING_CLINIC_UNAVAILABLE';
    public const SHOPIFY_ORDER_PAYLOAD_INVALID = 'SHOPIFY_ORDER_PAYLOAD_INVALID';
    public const SHOPIFY_ORDER_IDENTITY_FAILURE = 'SHOPIFY_ORDER_IDENTITY_FAILURE';
    public const SHOPIFY_ORDER_LINE_IMPORT_FAILURE = 'SHOPIFY_ORDER_LINE_IMPORT_FAILURE';
    public const SHOPIFY_PRODUCT_MAPPING_FAILURE = 'SHOPIFY_PRODUCT_MAPPING_FAILURE';
    public const SHOPIFY_PATIENT_LINK_FAILURE = 'SHOPIFY_PATIENT_LINK_FAILURE';
    public const SHOPIFY_PAYMENT_STATE_INVALID = 'SHOPIFY_PAYMENT_STATE_INVALID';
    public const SHOPIFY_ORDER_DATABASE_FAILURE = 'SHOPIFY_ORDER_DATABASE_FAILURE';
    public const SHOPIFY_PRESCRIPTION_LINK_FAILURE = 'SHOPIFY_PRESCRIPTION_LINK_FAILURE';
    public const SHOPIFY_LINE_ITEMS_MISSING = 'SHOPIFY_LINE_ITEMS_MISSING';
    public const SHOPIFY_LINE_ITEM_ID_INVALID = 'SHOPIFY_LINE_ITEM_ID_INVALID';
    public const SHOPIFY_LINE_ITEM_DATABASE_FAILURE = 'SHOPIFY_LINE_ITEM_DATABASE_FAILURE';
    public const SHOPIFY_LINE_ITEM_MAPPING_FAILURE = 'SHOPIFY_LINE_ITEM_MAPPING_FAILURE';
    public const SHOPIFY_LINE_ITEM_ORDER_LINK_FAILURE = 'SHOPIFY_LINE_ITEM_ORDER_LINK_FAILURE';
    public const UNKNOWN_IMPORT_FAILURE = 'UNKNOWN_IMPORT_FAILURE';

    /** @var list<string> */
    private const CATEGORIES = [
        self::CUSTOMER_NOT_LINKED, self::PRESCRIPTION_MISSING, self::MEDICINE_NOT_MAPPED, self::MEDICINE_INACTIVE,
        self::ORDER_NOT_FOUND, self::ORDER_IMPORT_RECORD_NOT_FOUND, self::SHOPIFY_ORDER_IMPORT_STATUS_INVALID, self::PHARMACY_ORDER_NOT_CREATED, self::ORDER_ALREADY_CANCELLED, self::ORDER_ALREADY_IMPORTED,
        self::INVALID_FULFILLMENT_STATE, self::REFUND_ORDER_NOT_FOUND, self::STOCK_UNAVAILABLE,
        self::PAYMENT_STATE_INVALID, self::DELIVERY_STATE_INVALID, self::BILLING_CLINIC_UNAVAILABLE,
        self::SHOPIFY_ORDER_PAYLOAD_INVALID, self::SHOPIFY_ORDER_IDENTITY_FAILURE,
        self::SHOPIFY_ORDER_LINE_IMPORT_FAILURE, self::SHOPIFY_PRODUCT_MAPPING_FAILURE,
        self::SHOPIFY_PATIENT_LINK_FAILURE, self::SHOPIFY_PAYMENT_STATE_INVALID,
        self::SHOPIFY_ORDER_DATABASE_FAILURE, self::SHOPIFY_PRESCRIPTION_LINK_FAILURE,
        self::SHOPIFY_LINE_ITEMS_MISSING, self::SHOPIFY_LINE_ITEM_ID_INVALID,
        self::SHOPIFY_LINE_ITEM_DATABASE_FAILURE, self::SHOPIFY_LINE_ITEM_MAPPING_FAILURE,
        self::SHOPIFY_LINE_ITEM_ORDER_LINK_FAILURE,
        self::UNKNOWN_IMPORT_FAILURE,
    ];

    public function __construct(public readonly string $category, string $internalMessage = '')
    {
        parent::__construct($internalMessage !== '' ? $internalMessage : $category);
    }

    public static function normalize(?string $category): string
    {
        return in_array($category, self::CATEGORIES, true) ? $category : self::UNKNOWN_IMPORT_FAILURE;
    }

    /** @param array<string, mixed> $result */
    public static function fromFulfilmentResult(array $result): string
    {
        $message = strtolower(trim((string) ($result['body']['msg'] ?? '')) . ' ' . self::flatten($result['body']['errors'] ?? []));

        if (str_contains($message, 'already exists') || str_contains($message, 'already been converted')) return self::ORDER_ALREADY_IMPORTED;
        if (str_contains($message, 'stock') || str_contains($message, 'quantity') || str_contains($message, 'expired')) return self::STOCK_UNAVAILABLE;
        if (str_contains($message, 'medicine') || str_contains($message, 'inactive') || str_contains($message, 'missing')) return self::MEDICINE_NOT_MAPPED;
        if (str_contains($message, 'payment')) return self::PAYMENT_STATE_INVALID;
        if (str_contains($message, 'delivery')) return self::DELIVERY_STATE_INVALID;

        if (str_contains($message, 'database') || str_contains($message, 'sqlstate') || str_contains($message, 'query')) return self::SHOPIFY_ORDER_DATABASE_FAILURE;
        return self::UNKNOWN_IMPORT_FAILURE;
    }

    public static function fromException(Throwable $exception, string $topic = ''): self
    {
        if ($exception instanceof self) return $exception;

        $message = strtolower($exception->getMessage());
        $category = self::UNKNOWN_IMPORT_FAILURE;
        if (str_contains($message, 'payload') || str_contains($message, 'json')) $category = self::SHOPIFY_ORDER_PAYLOAD_INVALID;
        elseif (str_contains($message, 'identity') || str_contains($message, 'customer')) $category = self::SHOPIFY_PATIENT_LINK_FAILURE;
        elseif (str_contains($message, 'line')) $category = self::SHOPIFY_ORDER_LINE_IMPORT_FAILURE;
        elseif (str_contains($message, 'mapping') || str_contains($message, 'product') || str_contains($message, 'variant')) $category = self::SHOPIFY_PRODUCT_MAPPING_FAILURE;
        elseif (str_contains($message, 'database') || str_contains($message, 'sqlstate') || str_contains($message, 'query')) $category = self::SHOPIFY_ORDER_DATABASE_FAILURE;
        if (str_contains($message, 'prescription')) $category = self::PRESCRIPTION_MISSING;
        elseif (str_contains($message, 'payment')) $category = self::PAYMENT_STATE_INVALID;
        elseif (str_contains($message, 'delivery')) $category = self::DELIVERY_STATE_INVALID;
        elseif (str_contains($message, 'fulfillment') || str_contains($message, 'transition')) $category = self::INVALID_FULFILLMENT_STATE;
        if (str_contains($message, 'already cancelled')) $category = self::ORDER_ALREADY_CANCELLED;
        if (str_contains($message, 'order import record') && str_contains($message, 'not found')) $category = self::ORDER_IMPORT_RECORD_NOT_FOUND;
        elseif (str_contains($message, 'order') && str_contains($message, 'not found')) $category = $topic === 'refunds/create' ? self::REFUND_ORDER_NOT_FOUND : self::ORDER_NOT_FOUND;

        return new self($category, $exception->getMessage());
    }

    private static function flatten(mixed $value): string
    {
        if (is_array($value)) return implode(' ', array_map([self::class, 'flatten'], $value));

        return is_scalar($value) ? (string) $value : '';
    }
}
