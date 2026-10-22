<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\Common\PrintFormatter;

final class AppointmentTimeFormattingTest extends CIUnitTestCase
{
    public function testSharedFormatterUsesUppercaseTwelveHourDisplay(): void
    {
        foreach ([
            '00:00' => '12:00 AM',
            '12:00' => '12:00 PM',
            '15:00' => '03:00 PM',
            '23:45' => '11:45 PM',
        ] as $input => $expected) {
            self::assertSame($expected, rootins_format_time($input));
        }
    }

    public function testSharedParserAcceptsTwelveHourInputAndRejectsInvalidAmPm(): void
    {
        self::assertSame('15:00:00', rootins_parse_time('03:00 PM'));
        self::assertSame('00:00:00', rootins_parse_time('12:00 AM'));
        self::assertNull(rootins_parse_time('13:00 PM'));
        self::assertNull(rootins_parse_time('03:60 PM'));
        self::assertNull(rootins_parse_time('03:00 XM'));
    }

    public function testPrintFormatterAndApiBoundariesUseSharedTwelveHourDisplay(): void
    {
        self::assertSame('03:00 PM', PrintFormatter::time('15:00:00'));
        self::assertSame('22-10-2026 03:15 PM', PrintFormatter::dateTime('2026-10-22 15:15:00'));

        $coreApi = file_get_contents(ROOTPATH . 'app/Modules/CoreApi/Services/CoreApiAppointmentService.php');
        $shopify = file_get_contents(ROOTPATH . 'app/Services/Shopify/ShopifyAppointmentService.php');

        self::assertStringContainsString("'start_time' => \$this->displayTime", $coreApi);
        self::assertStringContainsString("'end_time' => \$this->displayTime", $coreApi);
        self::assertStringContainsString('rootins_format_time($time)', $shopify);
    }
}
