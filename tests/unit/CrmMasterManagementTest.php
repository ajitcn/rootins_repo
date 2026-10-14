<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CrmMasterManagementTest extends TestCase
{
    private string $root;
    protected function setUp(): void { $this->root = dirname(__DIR__, 2); }
    private function read(string $path): string { return (string) file_get_contents($this->root . '/' . $path); }

    public function testDedicatedMasterRoutesAndControllerExist(): void
    {
        $routes = $this->read('app/Modules/CRM/Config/Routes.php');
        $controller = $this->read('app/Modules/CRM/Controllers/Masters.php');
        foreach (['lead-statuses','lead-sources','lead-priorities','follow-up-types','follow-up-statuses'] as $slug) self::assertStringContainsString("'{$slug}' =>", $routes);
        self::assertStringContainsString('namespace CRM\\Controllers', $controller);
        self::assertStringContainsString('assertPermission', $controller);
        self::assertStringContainsString("view(CRM_VIEW . 'pages/masters'", $controller);
        self::assertStringNotContainsString("view(ADMIN_VIEW . 'pages/crm/masters'", $controller);
    }

    public function testMasterMigrationIsReversibleAndSeedsPriorities(): void
    {
        $migration = $this->read('app/Database/Migrations/2026-10-12-143000_CreateCrmMasterData.php');
        self::assertStringContainsString("crm_lead_priorities", $migration);
        self::assertStringContainsString('function down', $migration);
        foreach (['low','normal','high','urgent'] as $code) self::assertStringContainsString("'code' => '{$code}'", $migration);
    }

    public function testMasterPageHasSingleReusableCrudModalAndActions(): void
    {
        $view = $this->read('app/Modules/CRM/Views/pages/masters.php');
        foreach (['Search name or code','More Actions','data-crm-master','Archive','modal-dialog-scrollable','csrf_field'] as $needle) self::assertStringContainsString($needle, $view);
        self::assertSame(1, substr_count($view, 'id="crmMasterModal"'));
    }

    public function testMenuContainsPermissionAwareMasterLinks(): void
    {
        $menu = $this->read('app/Modules/Admin/Views/parts/side_menu.php');
        foreach (['Lead Statuses','Lead Sources','Lead Priorities','Follow-up Types','Follow-up Statuses','crm.master.view'] as $needle) self::assertStringContainsString($needle, $menu);
        self::assertStringContainsString('CRM Master', $menu);
        self::assertStringNotContainsString('CRM Masters</span>', $menu);
        self::assertStringContainsString('crmMastersMenu', $menu);
    }

    public function testLeadFormsReadPrioritiesAndFollowUpStatusesFromMasters(): void
    {
        $service = $this->read('app/Modules/Admin/Services/CrmLeadService.php');
        $leadView = $this->read('app/Modules/Admin/Views/pages/crm/leads.php');
        self::assertStringContainsString("crm_lead_priorities", $service);
        self::assertStringContainsString("'priorities'=>\$priorities", $service);
        self::assertStringContainsString('($priorities ?? [])', $leadView);
        self::assertStringContainsString("'followUpStatuses'=>\$followStatuses", $service);
    }
}
