<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class ActionMenuStackingTest extends CIUnitTestCase
{
    public function testSharedActionMenuPortalsAndRestoresMenus(): void
    {
        $script = file_get_contents(ROOTPATH . 'public/assets/js/actions-menu.js');
        $pharmacyScript = file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/more_actions.js');
        $css = file_get_contents(ROOTPATH . 'public/assets/css/rootins-monochrome.css');

        foreach (['portalMenu', 'restoreMenu', 'document.body.appendChild(menu)', 'Escape', 'positionMenu(wrapper)'] as $contract) {
            self::assertStringContainsString($contract, $script);
        }
        foreach (['portalMenu', 'restoreMenu', 'rootins-pharmacy-actions-menu', 'document.body.appendChild(menu)'] as $contract) {
            self::assertStringContainsString($contract, $pharmacyScript);
        }
        foreach (['--ri-z-page', '--ri-z-surface', '--ri-z-actions', '--ri-z-modal-backdrop', '--ri-z-modal', '--ri-z-preloader', '.ri-actions-menu--in-modal', 'overflow-x'] as $contract) {
            self::assertStringContainsString($contract, $css);
        }
        self::assertStringNotContainsString('[class*="-actions"], [class*="-action-group"]', $css);
    }
}
