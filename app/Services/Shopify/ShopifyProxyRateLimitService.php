<?php

namespace App\Services\Shopify;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class ShopifyProxyRateLimitService
{
    public function __construct(private readonly ?BaseConnection $db = null)
    {
    }

    /** @return array{allowed: bool, retry_after: int} */
    public function consume(int $configurationId, int $limit): array
    {
        if ($configurationId < 1 || $limit < 1) throw new RuntimeException('The Shopify proxy rate-limit scope is invalid.');
        $db = $this->db ?? db_connect();
        if (! in_array(strtolower((string) $db->DBDriver), ['mysqli', 'mysql'], true)) throw new RuntimeException('The Shopify proxy rate limiter requires an atomic database driver.');
        $window = gmdate('Y-m-d H:i:00');
        $db->table('shopify_proxy_rate_limits')->where('configuration_id', $configurationId)->where('window_start <', gmdate('Y-m-d H:i:s', time() - 120))->delete();
        $db->query('INSERT INTO shopify_proxy_rate_limits (configuration_id, window_start, attempts, created_at, updated_at) VALUES (?, ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE attempts = IF(attempts < ?, attempts + 1, attempts), updated_at = IF(attempts < ?, UTC_TIMESTAMP(), updated_at)', [$configurationId, $window, $limit, $limit]);
        return ['allowed' => $db->affectedRows() > 0, 'retry_after' => max(1, 60 - (int) gmdate('s'))];
    }
}
