<?php

use App\Services\Notification\NotificationDispatcher;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class NotificationDispatcherTest extends CIUnitTestCase
{
    public function testDispatchIsDryRunAndIdempotentByKey(): void
    {
        $dispatcher = new NotificationDispatcher();
        $first = $dispatcher->dispatch('sms', ['address' => '+919999999999'], 'appointment_reminder', ['appointment_id' => 7], 'appointment-7-reminder');
        $second = $dispatcher->dispatch('sms', ['address' => '+919999999999'], 'appointment_reminder', ['appointment_id' => 7], 'appointment-7-reminder');
        $this->assertSame('deferred', $first['status']);
        $this->assertSame('twilio_messaging', $first['provider']);
        $this->assertSame(0, $first['attempts']);
        $this->assertSame($first['notification_id'], $second['notification_id']);
    }

    public function testInvalidRequestReturnsFieldErrors(): void
    {
        $result = (new NotificationDispatcher())->dispatch('push', [], 'bad template');
        $this->assertFalse($result['status']);
        $this->assertArrayHasKey('channel', $result['errors']);
        $this->assertArrayHasKey('recipient', $result['errors']);
        $this->assertArrayHasKey('template', $result['errors']);
    }
}
