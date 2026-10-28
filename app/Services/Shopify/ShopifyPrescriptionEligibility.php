<?php

namespace App\Services\Shopify;

use DateTimeImmutable;
use DateTimeZone;

final class ShopifyPrescriptionEligibility
{
    public const REASON = 'PRESCRIPTION_NOT_ORDERABLE';

    /** @var list<string> */
    public const ORDERABLE_STATUSES = ['completed'];

    public static function isValidDurationDays(mixed $durationDays): bool
    {
        return (is_int($durationDays) || (is_string($durationDays) && ctype_digit(trim($durationDays))))
            && (int) $durationDays > 0;
    }

    public static function calculateValidUntil(string $createdAt, mixed $durationDays): ?string
    {
        if (! self::isValidDurationDays($durationDays)) {
            return null;
        }

        try {
            $timezone = new DateTimeZone((string) (config('App')->appTimezone ?? date_default_timezone_get()));
            $created = new DateTimeImmutable($createdAt, $timezone);

            return $created->modify('+' . (int) $durationDays . ' days')->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    public static function today(): string
    {
        $timezone = (string) (config('App')->appTimezone ?? date_default_timezone_get());

        try {
            return (new DateTimeImmutable('today', new DateTimeZone($timezone)))->format('Y-m-d');
        } catch (\Throwable) {
            return (new DateTimeImmutable('today'))->format('Y-m-d');
        }
    }

    /** @param array<string,mixed> $prescription */
    public static function isOrderable(array $prescription, ?string $today = null): bool
    {
        $status = strtolower(trim((string) ($prescription['status'] ?? '')));
        $validUntil = trim((string) ($prescription['valid_until'] ?? ''));
        $today ??= self::today();

        if (! in_array($status, self::ORDERABLE_STATUSES, true) || ! self::isValidDurationDays($prescription['duration_days'] ?? null) || $validUntil === '') {
            return false;
        }

        $validDate = DateTimeImmutable::createFromFormat('!Y-m-d', $validUntil);
        $todayDate = DateTimeImmutable::createFromFormat('!Y-m-d', $today);

        return $validDate !== false
            && $todayDate !== false
            && $validDate->format('Y-m-d') === $validUntil
            && $todayDate->format('Y-m-d') === $today
            && $validDate >= $todayDate;
    }
}
