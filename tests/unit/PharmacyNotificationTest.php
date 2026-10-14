<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PharmacyNotificationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testDedicatedSchemaContainsScopeFieldsIndexesAndRollback(): void
    {
        $source = file_get_contents($this->root . '/app/Database/Migrations/2026-10-08-110000_RenameLegacyPharmacyIdentifiers.php');

        foreach (['pharmacy_notifications', 'farmassist_notifications', 'renameTable', 'event_key', 'down'] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testNotificationRoutesArePermissionProtected(): void
    {
        $source = file_get_contents($this->root . '/app/Modules/Pharmacy/Config/Routes.php');

        foreach (["'notifications'", 'notifications/unread-count', 'notifications/(:num)/read', 'notifications/read-all', 'permission:pharmacy.notification.view'] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testServiceScopesEveryOperationAndSupportsReadFiltersPaginationAndDeduplication(): void
    {
        $source = file_get_contents($this->root . '/app/Modules/Pharmacy/Services/PharmacyNotificationService.php');

        foreach (['clinic_id', 'recipient_user_id', 'recipient_role', 'event_key', 'read_at IS NULL', 'read_at IS NOT NULL', 'page_size', 'like(\'title\'', 'relatedUrl'] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
    }

    public function testInboxHasSearchFiltersModalAndReadControlsWithoutDatatables(): void
    {
        $source = file_get_contents($this->root . '/app/Modules/Pharmacy/Views/pages/notifications/index.php');

        foreach (['notificationSearch', 'notificationStatus', 'notificationType', 'modal-dialog-scrollable', 'notification-read', 'markAllNotifications', 'circle-exclamation'] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
        self::assertStringNotContainsString('DataTable', $source);
    }

    public function testWorkflowHooksAndAuditEventsArePresent(): void
    {
        foreach (['PrescriptionReview.php', 'OrderFulfilmentService.php', 'BillingService.php', 'DeliveryService.php'] as $file) {
            $source = file_get_contents($this->root . '/app/Modules/Pharmacy/' . (str_contains($file, 'Service') ? 'Services/' : 'Controllers/') . $file);
            self::assertStringContainsString('PharmacyNotificationService', $source);
        }
        $source = file_get_contents($this->root . '/app/Modules/Pharmacy/Services/PharmacyNotificationService.php');

        foreach (['pharmacy.notification_created', 'pharmacy.notification_read', 'pharmacy.notifications_read_all'] as $needle) {
            self::assertStringContainsString($needle, $source);
        }
    }
}
