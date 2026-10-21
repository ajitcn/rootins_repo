<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class ResponsiveTablesTest extends CIUnitTestCase
{
    public function testApplicationTableViewsDeclareResponsiveWrapper(): void
    {
        $viewFiles = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(ROOTPATH . 'app/Modules', \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($viewFiles as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || ! str_contains($file->getPath(), DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if (! str_contains($contents, '<table')) {
                continue;
            }

            self::assertStringContainsString(
                'table-responsive',
                $contents,
                'Every application table view must declare a table-responsive wrapper: ' . $file->getPathname()
            );
        }
    }

    public function testNativeTableContractIsLoadedAfterModuleStyles(): void
    {
        $layout = file_get_contents(ROOTPATH . 'app/Views/templates/theme1.php');
        $styles = file_get_contents(ROOTPATH . 'public/assets/css/responsive-tables.css');

        self::assertStringContainsString('responsive-tables.css', $layout);
        self::assertStringContainsString('.table-responsive > table', $styles);
        self::assertStringContainsString('overflow-x: auto !important', $styles);
        self::assertStringContainsString('display: table !important', $styles);
        self::assertStringContainsString('display: table-row !important', $styles);
        self::assertStringContainsString('display: table-cell !important', $styles);
        self::assertStringContainsString('overflow-wrap: anywhere', $styles);
        self::assertStringContainsString('word-break: break-word', $styles);
        self::assertStringNotContainsString('display: block !important', $styles);
        self::assertStringNotContainsString('display: flex !important', $styles);
        self::assertStringNotContainsString('display: grid !important', $styles);
    }
}
