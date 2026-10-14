<?php

namespace App\Modules\CoreApi\Services;

final class CoreApiContext
{
    /** @var array<string, mixed>|null */
    private static ?array $credential = null;

    /** @param array<string, mixed> $credential */
    public static function set(array $credential): void
    {
        self::$credential = $credential;
    }

    /** @return array<string, mixed>|null */
    public static function get(): ?array
    {
        return self::$credential;
    }
}
