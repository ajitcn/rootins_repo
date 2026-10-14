<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

class CrmFollowUpWorkflowTest extends CIUnitTestCase
{
    private function read(string $path): string
    {
        return file_get_contents(ROOTPATH . $path);
    }

    public function testWorkflowUsesScopedTransactionalImmutableActivity(): void
    {
        $service = $this->read('app/Modules/Admin/Services/CrmFollowUpWorkflowService.php');
        foreach (['clinicId', 'scopedFollowUp', 'activity', 'transBegin', 'transCommit', 'transRollback', 'follow_up_comment_added', 'follow_up_rescheduled', 'follow_up_status_changed', 'follow_up_type_changed', 'follow_up_assigned_user_changed', 'follow_up_id', 'deleted_at'] as $needle) {
            self::assertStringContainsString($needle, $service);
        }
    }

    public function testRescheduleScopeUsesLeadOwnershipAndSchemaSafeUserLookup(): void
    {
        $service = $this->read('app/Modules/Admin/Services/CrmFollowUpWorkflowService.php');
        $migration = $this->read('app/Database/Migrations/2026-10-12-140000_CreateCrmLeadManagement.php');
        self::assertStringContainsString("l.clinic_id AS lead_clinic_id", $service);
        self::assertStringContainsString("where('l.clinic_id', \$this->clinicId())", $service);
        self::assertStringContainsString("join('doctors d', 'd.id = u.doctor_id', 'left')", $service);
        self::assertStringNotContainsString("where('clinic_id', \$this->clinicId())", $service);
        self::assertStringContainsString("'clinic_id' => ['type' => 'INT'", $migration);
    }

    public function testWorkflowValidatesCommentsDatesTimesAndConflicts(): void
    {
        $service = $this->read('app/Modules/Admin/Services/CrmFollowUpWorkflowService.php');
        foreach (['Comment must not exceed 2000 characters.', 'Enter a valid current or future follow-up date.', 'Enter a valid follow-up time.', 'already has an active follow-up'] as $needle) {
            self::assertStringContainsString($needle, $service);
        }
    }

    public function testRoutesAndUiExposeAllFollowUpActions(): void
    {
        $routes = $this->read('app/Modules/CRM/Config/Routes.php');
        $view = $this->read('app/Modules/Admin/Views/pages/crm/followups.php');
        foreach (['followUpDetails', 'followUpHistory', 'addFollowUpComment', 'rescheduleFollowUp', 'followUpAction', 'follow-ups/(:num)/history', 'permission:crm.history.view'] as $needle) {
            self::assertStringContainsString($needle, $routes . $this->read('app/Modules/Admin/Controllers/Crm.php'));
        }
        foreach (['View details', 'Add comment', 'Reschedule', 'Complete', 'Cancel', 'Mark missed', 'View history', 'js-crm-followup-history', 'data-followup-url', 'data-followup-activity', 'created', 'creator', 'updated', 'updater'] as $needle) {
            self::assertStringContainsString($needle, $view);
        }
    }

    public function testFollowUpJavascriptCancelsBeforeAjaxAndCleansUp(): void
    {
        $script = $this->read('public/assets/js/admin/crm.js');
        foreach (['confirmFormAction', 'event.preventDefault()', 'event.stopPropagation()', 'event.stopImmediatePropagation()', 'button.disabled = false', 'window.hide_loader', 'data-action-template', 'FormData(form)'] as $needle) {
            self::assertStringContainsString($needle, $script);
        }
        self::assertStringContainsString("form.closest('#crmFollowUpDetailsModal')", $script);
        self::assertStringContainsString('loadFollowupDetails({dataset:', $script);
        self::assertStringContainsString("closest('.js-crm-followup-history')", $script);
        self::assertStringContainsString("showCrmModal(document.querySelector('#crmFollowUpDetailsModal'), historyButton)", $script);
    }

    public function testDetailsModalKeepsSummaryConciseAndScrollable(): void
    {
        $script = $this->read('public/assets/js/admin/crm.js');
        $css = $this->read('public/assets/css/crm/followup-workflow.css');
        $view = $this->read('app/Modules/Admin/Views/pages/crm/followups.php');
        foreach (['created', 'creator', 'updated', 'updater', 'Recent activity', 'crm-followup-details-modal'] as $needle) {
            self::assertStringContainsString($needle, $script);
        }
        foreach (['modal-header', 'modal-footer', '.modal-body', '@media(max-width:575.98px)'] as $needle) {
            self::assertStringContainsString($needle, $css);
        }
        self::assertStringNotContainsString('crm-followup-timeline{max-height:', $css);
        self::assertStringNotContainsString('crm-followup-timeline{position:relative;display:grid;gap:.75rem;max-height:', $css);
        self::assertStringContainsString('modal-dialog modal-dialog-centered modal-lg', $view);
        self::assertStringNotContainsString('modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg', $view);
        self::assertStringContainsString('max-height:70vh;overflow-y:auto', $css);
        self::assertStringNotContainsString('modal-xl', $view . $css);
        self::assertStringNotContainsString('modal-fullscreen', $view . $css);
    }

    public function testDetailsModalLifecycleSupportsCloseEscapeBackdropAndCleanup(): void
    {
        $script = $this->read('public/assets/js/admin/crm.js');
        foreach (['hideCrmModal', 'showCrmModal', 'data-dismiss="modal"', "event.key !== 'Escape'", 'removeCrmBackdrops', 'rootins-modal-lock', 'stopImmediatePropagation'] as $needle) {
            self::assertStringContainsString($needle, $script);
        }
    }
}
