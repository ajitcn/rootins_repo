<?php

namespace App\Services;

use Admin\Models\Location\TelephoneCountryCode;
use InvalidArgumentException;

final class TelephoneContactService
{
    private ?TelephoneCountryCode $codes;

    public function __construct(?TelephoneCountryCode $codes = null)
    {
        $this->codes = $codes;
    }

    /**
     * Resolve a selected lookup ID or create a normalized manual code record.
     *
     * @return array<string, mixed>|null
     */
    public function resolve(?int $selectedId, bool $manual, ?string $manualCode, ?string $countryName): ?array
    {
        if ($manual) {
            $normalized = TelephoneCountryCode::normalizeCode((string) $manualCode);
            $name = TelephoneCountryCode::normalizeCountryName($countryName);

            if ($normalized === null || $name === null) {
                throw new InvalidArgumentException('A valid country name and telephone country code are required.');
            }

            return $this->codes()->resolveOrCreate($normalized, null, $name);
        }

        if ($selectedId === null || $selectedId < 1) {
            return null;
        }

        return $this->codes()
            ->where('id', $selectedId)
            ->where('status', 1)
            ->first();
    }

    public function normalizedMobile(?string $mobile): ?string
    {
        $normalized = preg_replace('/[^0-9]/', '', trim((string) $mobile));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Format a stored telephone code and mobile number for display.
     *
     * The telephone code is expected to come from the database-backed
     * lookup. The legacy value is only a safe fallback for older records
     * whose lookup reference is missing.
     */
    public function formatDisplay(?string $telephoneCode, ?string $mobile, ?string $legacyCode = null): string
    {
        $code = TelephoneCountryCode::normalizeCode((string) $telephoneCode);

        if ($code === null) {
            $code = TelephoneCountryCode::normalizeCode((string) $legacyCode);
        }

        $number = $this->normalizedMobile($mobile);

        if ($number === null) {
            return '—';
        }

        return $code === null ? $number : '+' . $code . ' ' . $number;
    }

    private function codes(): TelephoneCountryCode
    {
        return $this->codes ??= new TelephoneCountryCode();
    }
}
