<?php

use App\Services\Audit\AuditLogger;
use CodeIgniter\Test\CIUnitTestCase;
/** @internal */
final class AuditLoggerTest extends CIUnitTestCase
{
    public function testCorrelationIdIsStableAndSafe(): void
    {
        $logger = new AuditLogger();
        $first = $logger->correlationId();
        $second = $logger->correlationId();
        $this->assertSame($first, $second);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9._:-]{1,64}$/', $first);
    }
    public function testAuditEventReturnsCorrelationId(): void
    {
        $logger = new AuditLogger();
        $correlationId = $logger->record('security.test', ['password' => 'must-not-be-logged', 'record_id' => 42]);
        $this->assertSame($logger->correlationId(), $correlationId);
    }
}
