<?php

namespace App\Modules\CoreApi\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class CoreApiRateLimitService
{
    public function __construct(private readonly ?BaseConnection $db = null)
    {
    }

    /** @return array{allowed: bool, retry_after: int} */
    public function consume(int $credentialId, int $limit): array
    {
        if ($credentialId < 1 || $limit < 1) {
            throw new RuntimeException('The API rate-limit scope is invalid.');
        }

        $db = $this->db ?? db_connect();
        $driver = strtolower((string) $db->DBDriver);
        $window = gmdate('Y-m-d H:i:00');

        // The deployed database is MySQL. The unique key plus this single
        // conditional upsert makes the counter decision atomic across PHP
        // workers and application instances.
        if (! in_array($driver, ['mysqli', 'mysql'], true)) {
            throw new RuntimeException('The API rate limiter requires an atomic database driver.');
        }

        // Keep the counter table bounded without touching the active or
        // immediately previous window.
        $db->table('core_api_rate_limits')
            ->where('credential_id', $credentialId)
            ->where('window_start <', gmdate('Y-m-d H:i:s', time() - 120))
            ->delete();

        $db->query(
            'INSERT INTO core_api_rate_limits (credential_id, window_start, attempts, created_at, updated_at) VALUES (?, ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()) '
            . 'ON DUPLICATE KEY UPDATE attempts = IF(attempts < ?, attempts + 1, attempts), updated_at = IF(attempts < ?, UTC_TIMESTAMP(), updated_at)',
            [$credentialId, $window, $limit, $limit],
        );

        $affected = $db->affectedRows();
        $retryAfter = max(1, 60 - (int) gmdate('s'));

        return ['allowed' => $affected > 0, 'retry_after' => $retryAfter];
    }
}
