<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;

final class CrmLeadManagementTest extends TestCase
{
    public function testCrmSchemaSeedsStatusesSourcesAndIsReversible(): void
    {
        $migration=file_get_contents(ROOTPATH.'app/Database/Migrations/2026-10-12-140000_CreateCrmLeadManagement.php');
        foreach(['crm_lead_statuses','crm_lead_sources','crm_leads','crm_lead_followups','crm_lead_history','appointment_booked','not_interested','public function down','addForeignKey(\'lead_id\''] as $needle) self::assertStringContainsString($needle,$migration);
    }
    public function testPermissionsRoutesAndControllerGuardsExist(): void
    {
        $migration=file_get_contents(ROOTPATH.'app/Database/Migrations/2026-10-12-141000_AddCrmPermissions.php');
        $routes=file_get_contents(ROOTPATH.'app/Modules/Admin/Config/Routes.php');
        $controller=file_get_contents(ROOTPATH.'app/Modules/Admin/Controllers/Crm.php');
        foreach(['crm.lead.view','crm.lead.create','crm.lead.edit','crm.lead.delete','crm.lead.convert','crm.followup.view','crm.followup.create','crm.followup.edit','crm.followup.complete','crm.followup.cancel','crm.history.view'] as $permission){self::assertStringContainsString($permission,$migration.$routes); }
        self::assertStringContainsString('hasPermission', $controller); self::assertStringContainsString('clinicId',file_get_contents(ROOTPATH.'app/Modules/Admin/Services/CrmLeadService.php'));
    }
    public function testLeadFollowUpHistoryAndConversionContractsArePresent(): void
    {
        $service=file_get_contents(ROOTPATH.'app/Modules/Admin/Services/CrmLeadService.php');
        foreach(['saveLead','saveFollowUp','transitionFollowUp','history','convertToPatient','convertToAppointment','converted_to_patient','converted_to_appointment'] as $needle) self::assertStringContainsString($needle,$service);
        self::assertStringContainsString('Date of birth is required before converting', $service);
        self::assertStringContainsString("'booking_source'=>'crm'", $service);
    }
    public function testAdminWorkspacesUseScopedFormsAndResponsiveTheme(): void
    {
        foreach(['app/Modules/Admin/Views/pages/crm/leads.php','app/Modules/Admin/Views/pages/crm/followups.php'] as $page){$view=file_get_contents(ROOTPATH.$page);self::assertStringContainsString('csrf_field()', $view);self::assertStringContainsString($page=== 'app/Modules/Admin/Views/pages/crm/leads.php' ? 'crm-form-row' : 'crm-form-grid',$view);self::assertStringContainsString('More actions',$view);}
        self::assertStringContainsString('modal-dialog-scrollable',file_get_contents(ROOTPATH.'app/Modules/Admin/Views/pages/crm/leads.php'));
        self::assertStringContainsString('@media(max-width:575.98px)',file_get_contents(ROOTPATH.'public/assets/css/admin/crm.css'));
        self::assertStringContainsString('finally',file_get_contents(ROOTPATH.'public/assets/js/admin/crm.js'));
    }
    public function testJoinedPaginationUsesPagerAndBoundedQueries(): void
    {
        $service=file_get_contents(ROOTPATH.'app/Modules/Admin/Services/CrmLeadService.php');
        self::assertStringContainsString('limit($perPage, $offset)', $service);
        self::assertStringContainsString('makeLinks($page, $perPage, $total', $service);
        self::assertStringNotContainsString('$q->orderBy(\'l.id\',\'DESC\')->paginate', $service);
        self::assertStringNotContainsString('$q->orderBy(\'f.follow_up_date\',\'DESC\')->orderBy(\'f.follow_up_time\',\'DESC\')->paginate', $service);
        self::assertStringContainsString("paginate(\$perPage,'crm_history')", $service);
    }
    public function testCRMModuleOwnsRoutesNamespacesViewsAndAssets(): void
    {
        $routes=file_get_contents(ROOTPATH.'app/Modules/CRM/Config/Routes.php');
        self::assertStringContainsString("namespace' => 'CRM\\Controllers'", $routes);
        self::assertStringContainsString('CRM_VIEW', file_get_contents(ROOTPATH.'app/Modules/Admin/Controllers/Crm.php'));
        self::assertFileExists(ROOTPATH.'app/Modules/CRM/Controllers/Crm.php');
        self::assertFileExists(ROOTPATH.'app/Modules/CRM/Services/CrmLeadService.php');
        self::assertFileExists(ROOTPATH.'app/Modules/CRM/Views/pages/leads.php');
        self::assertFileExists(ROOTPATH.'public/assets/css/crm/crm.css');
        self::assertFileExists(ROOTPATH.'public/assets/js/crm/crm.js');
    }
    public function testAdminSidebarPlacesCRMUnderOperationsWithPermissionAwareLinks(): void
    {
        $menu=file_get_contents(ROOTPATH.'app/Modules/Admin/Views/parts/side_menu.php');
        $operations=strpos($menu,'<li class="sidebar-section-title" aria-hidden="true">Operations</li>');
        $crm=strpos($menu,'id="crmMenu"');
        $masters=strpos($menu,'<li class="sidebar-section-title" aria-hidden="true">Masters</li>');
        self::assertNotFalse($operations);
        self::assertNotFalse($crm);
        self::assertNotFalse($masters);
        self::assertLessThan($masters,$crm);
        self::assertStringContainsString("\$can('crm.lead.view')", $menu);
        self::assertStringContainsString("\$can('crm.followup.view')", $menu);
        self::assertStringContainsString("base_url('admin/crm/leads')", $menu);
        self::assertStringContainsString("base_url('admin/crm/follow-ups')", $menu);
    }
    public function testCRMModalUsesSharedViewportScrollPatternAndCleansBackdrops(): void
    {
        $css=file_get_contents(ROOTPATH.'public/assets/css/crm/modal.css');
        $js=file_get_contents(ROOTPATH.'public/assets/js/admin/crm.js');
        foreach(['max-height: calc(100vh - 2rem)','overflow: hidden','overflow-y: auto','flex: 0 0 auto','min-height: 0'] as $needle) self::assertStringContainsString($needle,$css);
        self::assertStringContainsString('modal-backdrop.crm-modal-backdrop',$css);
        foreach(['shown.bs.modal','hidden.bs.modal','cleanupModalArtifacts','finally','modal-body'] as $needle) self::assertStringContainsString($needle,$js);
    }

    public function testLeadTelephoneCountryCodeUsesActiveMasterAndNormalizedStorage(): void
    {
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-12-142000_AddCrmLeadTelephoneCountryCode.php');
        $model = file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/CrmLeadModel.php');
        $service = file_get_contents(ROOTPATH . 'app/Modules/Admin/Services/CrmLeadService.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/crm/leads.php');

        foreach (['telephone_country_code_id', 'country_code', 'crm_leads_country_mobile_idx', 'addForeignKey', 'public function down'] as $needle) {
            self::assertStringContainsString($needle, $migration);
        }
        foreach (['telephone_country_code_id', 'country_code', 'mobile_normalized'] as $needle) {
            self::assertStringContainsString($needle, $model);
            self::assertStringContainsString($needle, $service);
        }
        foreach (['telephoneCodes', 'where(\'status\',1)', 'normalizedMobile', 'A lead with this country code and mobile number already exists'] as $needle) {
            self::assertStringContainsString($needle, $service);
        }
        self::assertStringContainsString('name="telephone_country_code_id"', $view);
        self::assertStringContainsString('data-phone-code=', $view);
        self::assertStringContainsString('name="mobile_number"', $view);
        self::assertStringContainsString('crm-phone-group', $view);
        self::assertStringContainsString('name="country_code_manual"', $view);
        self::assertStringContainsString('name="telephone_country_name"', $view);
        self::assertStringContainsString('data-select="#crmLeadCountryCode"', $view);
    }

    public function testLeadDisplayAndConversionUseSavedCountryCodeWithoutHardCoding(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Admin/Services/CrmLeadService.php');
        foreach (['mobile_display', 'formatDisplay', 'telephone_country_code_id', 'country_code'] as $needle) {
            self::assertStringContainsString($needle, $service);
        }
        self::assertStringNotContainsString("'country_code'=>'+91'", $service);
        foreach (['app/Modules/Admin/Views/pages/crm/view.php', 'app/Modules/Admin/Views/pages/crm/followups.php', 'app/Modules/Admin/Views/pages/crm/history.php'] as $page) {
            self::assertStringContainsString('mobile_display', file_get_contents(ROOTPATH . $page));
        }
        $js = file_get_contents(ROOTPATH . 'public/assets/js/admin/crm.js');
        self::assertStringContainsString('bindCountryCodeToggles', $js);
        self::assertStringContainsString('!row.telephone_country_code_id && !!row.country_code', $js);
    }

    public function testInterestedServiceUsesScopedActiveMasterIdsAndSafeLegacyDisplay(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Admin/Services/CrmLeadService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Admin/Controllers/Crm.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/crm/leads.php');
        foreach (['ServiceModel', 'forScope', "where('status',1)", 'services', 'service_name', 'ctype_digit'] as $needle) {
            self::assertStringContainsString($needle, $service . $controller);
        }
        self::assertStringContainsString('name="interested_service"', $view);
        self::assertStringContainsString('Select service', $view);
        self::assertStringContainsString('$row[\'service_name\']', $view);
        self::assertStringContainsString('inactive or legacy', file_get_contents(ROOTPATH . 'public/assets/js/admin/crm.js'));
        self::assertStringContainsString('service_name', file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/crm/view.php'));
        self::assertStringContainsString('service_name', file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/crm/followups.php'));
        self::assertStringContainsString('service_name', file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/crm/history.php'));
    }

    public function testHistoryAndFollowUpContractsIncludeScopeErrorsMastersAndCleanup(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Admin/Services/CrmLeadService.php');
        $history = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/crm/history.php');
        $followups = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/crm/followups.php');
        foreach (['historyRows', 'clinic_id', 'actor_name', 'created_at_display', 'transBegin', 'transCommit', 'transRollback', 'follow_up_type_id', 'follow_up_status', 'assigned_user_id'] as $needle) {
            self::assertStringContainsString($needle, $service);
        }
        self::assertStringContainsString("2[0-3]):[0-5]", file_get_contents(ROOTPATH . 'app/Modules/CRM/Services/CrmLeadService.php'));
        foreach (['Previous value', 'New value', 'Remarks', 'Actor', 'Date/time', 'No history found'] as $needle) {
            self::assertStringContainsString($needle, $history);
        }
        foreach (['name="lead_id"', 'name="follow_up_date"', 'name="follow_up_time"', 'name="follow_up_type_id"', 'name="follow_up_status"', 'name="assigned_user_id"', 'name="remarks"', 'data-no-global-loader="1"', 'finally'] as $needle) {
            self::assertStringContainsString($needle, $followups . file_get_contents(ROOTPATH . 'public/assets/js/admin/crm.js'));
        }
    }
}
