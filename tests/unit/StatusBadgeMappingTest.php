<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class StatusBadgeMappingTest extends CIUnitTestCase
{
    public function testCanonicalStatusMappingContainsAllRequiredTones(): void
    {
        $source = file_get_contents(ROOTPATH . 'public/assets/js/status-colors.js');
        $css = file_get_contents(ROOTPATH . 'public/assets/css/rootins-monochrome.css');

        self::assertStringContainsString('window.rootinsStatusTone', $source);
        self::assertStringContainsString("if (/draft|inactive|unknown|not available", $source);
        self::assertStringContainsString("if (/(^| )active( |$)|(^| )available( |$)", $source);
        self::assertStringContainsString("warning( |$)", $source);
        self::assertStringContainsString('.status-inactive', $css);
        self::assertStringContainsString('.status-warning', $css);
        self::assertStringContainsString('--ri-status-green-text', $css);
        self::assertStringContainsString('--ri-status-gray-text', $css);
        self::assertStringContainsString('--ri-status-purple-text', $css);
    }
}
