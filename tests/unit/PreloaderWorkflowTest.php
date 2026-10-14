<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PreloaderWorkflowTest extends TestCase
{
    public function testSharedAjaxRequestOwnsCompleteCleanupAndFormRestoration(): void
    {
        $common = file_get_contents(ROOTPATH . 'public/assets/js/common.js');

        self::assertStringContainsString('document.addEventListener(\'submit\'', $common);
        self::assertStringContainsString('rootinsRestoreFormState(formState)', $common);
        self::assertStringContainsString('if (loaderVisible)', $common);
        self::assertStringContainsString("clearFormErrors(formState.form)", $common);
        self::assertStringContainsString("prop('disabled', true).attr('aria-busy', 'true')", $common);
        self::assertStringContainsString("button.prop('disabled', snapshot.disabled)", $common);
        self::assertStringContainsString(".html(snapshot.html)", $common);
        self::assertStringContainsString("settings.type || settings.method", $common);
        self::assertStringContainsString('timeout: 30000', $common);
        self::assertStringContainsString("requestStatus === 'timeout'", $common);
        self::assertStringContainsString("requestStatus === 'abort'", $common);
        self::assertStringContainsString('case 409:', $common);
        self::assertStringContainsString('handleFormAjaxError(formState ? formState.form : null', $common);
        self::assertStringContainsString('function rootinsFetch(url, options)', $common);
        self::assertStringContainsString('controller.abort()', $common);
    }

    public function testModuleAjaxHandlersDoNotOwnGlobalLoaderCleanup(): void
    {
        $files = [
            'public/assets/js/pharmacy/billing.js',
            'public/assets/js/pharmacy/orders.js',
            'public/assets/js/pharmacy/order_labels.js',
            'public/assets/js/admin/doctor/doctor.js',
            'public/assets/js/admin/doctor/doctor_schedule.js',
            'public/assets/js/admin/doctor/doctor_leave.js',
            'public/assets/js/admin/doctor/doctor_fee.js',
            'public/assets/js/admin/doctor/doctor_commission.js',
            'public/assets/js/admin/doctor/doctor_document.js',
        ];

        foreach ($files as $file) {
            self::assertStringNotContainsString('hide_loader()', file_get_contents(ROOTPATH . $file), $file);
        }

        self::assertStringContainsString('rootinsFetch(', file_get_contents(ROOTPATH . 'public/assets/js/admin/core-api/core-api.js'));
        self::assertStringNotContainsString('= fetch(', file_get_contents(ROOTPATH . 'public/assets/js/admin/core-api/core-api.js'));
        $shopify = file_get_contents(ROOTPATH . 'app/Modules/Admin/Views/pages/integrations/shopify.php');
        self::assertStringContainsString('rootinsFetch(', $shopify);
        self::assertStringNotContainsString('        fetch(', $shopify);
    }
}
