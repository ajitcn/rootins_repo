<?php

namespace App\Services\Common;

use DateTimeImmutable;

/** Shared, display-only formatting for authorized ROOTINS print documents. */
final class PrintFormatter
{
    public static function date(?string $value): string
    {
        $date = $value ? DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10)) : false;

        return $date ? $date->format('d-m-Y') : '';
    }

    public static function time(?string $value): string
    {
        $time = $value ? (DateTimeImmutable::createFromFormat('!H:i:s', substr($value, 0, 8)) ?: DateTimeImmutable::createFromFormat('!H:i', substr($value, 0, 5))) : false;

        return $time ? $time->format('h:i A') : '';
    }

    public static function dateTime(?string $value): string
    {
        $date = $value ? DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', substr($value, 0, 19)) : false;

        return $date ? $date->format('d-m-Y h:i A') : self::date($value);
    }

    public static function currency(float|int|string|null $amount, string $currency = 'INR'): string
    {
        return trim($currency) . ' ' . number_format((float) $amount, 2);
    }

    /** @return array{clinic_name:string,address:string,mobile:string,email:string} */
    public static function clinic(array $record): array
    {
        return [
            'clinic_name' => (string) ($record['clinic_name'] ?? 'ROOTINS Clinic'),
            'address' => (string) ($record['clinic_address'] ?? $record['address'] ?? ''),
            'mobile' => (string) ($record['clinic_mobile'] ?? $record['mobile'] ?? ''),
            'email' => (string) ($record['clinic_email'] ?? $record['email'] ?? ''),
        ];
    }

    /** @return array{name:string,registration_number:string,mobile:string,address?:string} */
    public static function patient(array $record): array
    {
        return [
            'name' => trim(implode(' ', array_filter([(string) ($record['patient_first_name'] ?? ''), (string) ($record['patient_middle_name'] ?? ''), (string) ($record['patient_last_name'] ?? '')]))),
            'registration_number' => (string) ($record['registration_number'] ?? ''),
            'mobile' => (string) ($record['patient_mobile'] ?? $record['mobile_number'] ?? ''),
            'address' => (string) ($record['patient_address'] ?? $record['address'] ?? ''),
        ];
    }

    public static function label(?string $value): string
    {
        return ucwords(str_replace('_', ' ', trim((string) $value)));
    }
}
