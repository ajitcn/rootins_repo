<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class ModalLayerTest extends CIUnitTestCase
{
    public function testSharedModalLayerDefinesOneStackAndCleansBackdrops(): void
    {
        $script = file_get_contents(ROOTPATH . 'public/assets/js/modal-layer.js');
        $css = file_get_contents(ROOTPATH . 'public/assets/css/rootins-monochrome.css');
        $layout = file_get_contents(ROOTPATH . 'app/Views/templates/theme1.php');

        foreach (['portal', 'restore', 'cleanupBackdrops', 'modal-backdrop', 'ajaxError.rootinsLayer', 'rootins-modal-placeholder'] as $contract) {
            self::assertStringContainsString($contract, $script);
        }
        foreach (['--ri-z-modal-backdrop', '--ri-z-modal', '--ri-z-modal-popover', '--ri-z-preloader', '.modal-backdrop', '.modal {', 'pointer-events: auto'] as $contract) {
            self::assertStringContainsString($contract, $css);
        }
        self::assertStringContainsString('assets/js/modal-layer.js', $layout);
        self::assertStringNotContainsString('z-index: var(--ri-z-page)', $css);
    }
}
