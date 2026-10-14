<?php

namespace App\Modules\CoreApi\Services;

use RuntimeException;

final class CoreApiException extends RuntimeException
{
    /** @param array<string, string> $fields */
    public function __construct(public readonly int $httpStatus, public readonly string $errorCode, string $message, public readonly array $fields = [], public readonly ?int $retryAfter = null)
    {
        parent::__construct($message);
    }
}
