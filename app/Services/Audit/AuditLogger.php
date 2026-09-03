<?php

namespace App\Services\Audit;

use CodeIgniter\HTTP\IncomingRequest;
final class AuditLogger
{
    private ?string $correlationId = null;
    /**
     * Record a structured, non-clinical audit event in the application log.
     * Durable audit storage remains a separate schema-backed capability.
     *
     * @param array<string, mixed> $context
     */
    public function record(string $action, array $context = []): string
    {
        $event = ['event' => 'audit', 'action' => $this->safeString($action, 80), 'correlation_id' => $this->correlationId(), 'actor_user_id' => (int) ((session('user') ?? [])['id'] ?? 0), 'route' => $this->safeString(uri_string(), 160), 'occurred_at' => date('c'), 'context' => $this->sanitize($context)];
        $safeContext = $this->sanitize($context);
        $encodedContext = json_encode($safeContext, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $event['context'] = $safeContext;
        log_message('info', 'ROOTINS_AUDIT ' . json_encode($event, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        try {
            $db = db_connect();
            if ($db->tableExists('audit_logs')) {
                $db->table('audit_logs')->insert(['action' => $event['action'], 'correlation_id' => $event['correlation_id'], 'actor_user_id' => $event['actor_user_id'] ?: null, 'clinic_id' => isset($context['clinic_id']) ? (int) $context['clinic_id'] : null, 'route' => $event['route'], 'occurred_at' => date('Y-m-d H:i:s'), 'context' => $encodedContext, 'created_at' => date('Y-m-d H:i:s')]);
            }
        } catch (\Throwable $exception) {
            log_message('error', 'Durable audit write failed: ' . $exception->getMessage());
        }
        return $event['correlation_id'];
    }
    public function correlationId(): string
    {
        if ($this->correlationId !== null) {
            return $this->correlationId;
        }
        $request = service('request');
        $header = $request instanceof IncomingRequest ? $request->getHeaderLine('X-Correlation-ID') : '';
        $this->correlationId = preg_match('/^[A-Za-z0-9._:-]{1,64}$/', $header) ? $header : bin2hex(random_bytes(16));
        return $this->correlationId;
    }
    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function sanitize(array $values): array
    {
        $safe = [];
        foreach ($values as $key => $value) {
            $name = strtolower((string) $key);
            if (preg_match('/password|secret|token|authorization|cookie|credential|diagnosis|clinical|notes|message|email|mobile|phone|address/', $name)) {
                $safe[$key] = '[REDACTED]';
                continue;
            }
            if (is_array($value)) {
                $safe[$key] = $this->sanitize($value);
                continue;
            }
            $safe[$key] = is_scalar($value) || $value === null ? $this->safeString((string) $value, 160) : '[UNSUPPORTED]';
        }
        return $safe;
    }
    private function safeString(string $value, int $maxLength): string
    {
        $value = trim($value);
        return strlen($value) > $maxLength ? substr($value, 0, $maxLength) . '…' : $value;
    }
}
