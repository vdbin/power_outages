<?php

use PHPUnit\Framework\TestCase;
use PowerOutages\OutageParser;

final class OutageParserTest extends TestCase
{
    public function testFormatHalfHourTime()
    {
        $this->assertSame('00:00', OutageParser::formatHalfHourTime(0));
        $this->assertSame('00:30', OutageParser::formatHalfHourTime(1));
        $this->assertSame('01:00', OutageParser::formatHalfHourTime(2));
        $this->assertSame('24:00', OutageParser::formatHalfHourTime(48));
    }

    public function testParseOutageIntervals_AllYes_ReturnsEmpty()
    {
        $slots = array();
        for ($h = 1; $h <= 24; $h++) {
            $slots[(string)$h] = 'yes';
        }

        $this->assertSame(array(), OutageParser::parseOutageIntervals($slots));
    }

    public function testParseOutageIntervals_SingleHourNo_ReturnsOneInterval()
    {
        $slots = array();
        for ($h = 1; $h <= 24; $h++) {
            $slots[(string)$h] = 'yes';
        }
        $slots['1'] = 'no'; // 01:00-02:00 = індекси 2..4 по півгодини

        $this->assertSame(
            array('00:00 - 01:00'),
            OutageParser::parseOutageIntervals($slots)
        );
    }

    public function testParseOutageIntervals_TwoSeparateOutages()
    {
        $slots = array();
        for ($h = 1; $h <= 24; $h++) {
            $slots[(string)$h] = 'yes';
        }
        $slots['3'] = 'no'; // 02:00 - 03:00
        $slots['10'] = 'no'; // 09:00 - 10:00

        $this->assertSame(
            array('02:00 - 03:00', '09:00 - 10:00'),
            OutageParser::parseOutageIntervals($slots)
        );
    }

    public function testParseOutageIntervals_FromJsonString_Works()
    {
        $json = '{
          "1": "no",
          "2": "msecond",
          "3": "yes",
          "4": "mfirst",
          "5": "no",
          "6": "no",
          "7": "no",
          "8": "no",
          "9": "no",
          "10": "no",
          "11": "mfirst",
          "12": "msecond",
          "13": "no",
          "14": "no",
          "15": "no",
          "16": "no",
          "17": "no",
          "18": "no",
          "19": "msecond",
          "20": "yes",
          "21": "msecond",
          "22": "no",
          "23": "no",
          "24": "no"
        }';

        $slots = json_decode($json, true);

        $this->assertTrue(is_array($slots), 'JSON має декодуватися в масив');
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'JSON невалідний: ' . json_last_error_msg());

        $this->assertSame(
            array(
                '00:00 - 02:00',
                '03:00 - 10:30',
                '11:30 - 19:00',
                '20:30 - 24:00',
            ),
            OutageParser::parseOutageIntervals($slots)
        );
    }

    public function testParseOutageIntervals_FromExampleJson_File()
    {
        //json source https://github.com/yaroslav2901/OE_OUTAGE_DATA/blob/1827cd787824f62f190017fb036ceead34f9a56c/data/Ternopiloblenerho.json
        $path = __DIR__ . '/example.json';
        $this->assertFileExists($path);

        $data = json_decode(file_get_contents($path), true);
        $this->assertTrue(is_array($data), 'JSON має декодуватися в масив');
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'JSON невалідний: ' . json_last_error_msg());

        $expected = array(
            1769119200 =>//23.01.2026
                array(
                    'GPV1.1' =>
                        array(
                            '00:00 - 02:00',
                            '03:00 - 10:30',
                            '11:30 - 19:00',
                            '20:30 - 24:00',
                        ),
                    'GPV1.2' =>
                        array(
                            '00:00 - 04:30',
                            '06:00 - 13:00',
                            '14:30 - 21:30',
                            '23:00 - 24:00',
                        ),
                    'GPV2.1' =>
                        array(
                            '00:00 - 06:00',
                            '07:00 - 14:30',
                            '15:30 - 23:00',
                        ),
                    'GPV2.2' =>
                        array(
                            '00:00 - 04:00',
                            '05:00 - 12:30',
                            '14:00 - 21:30',
                            '22:30 - 24:00',
                        ),
                    'GPV3.1' =>
                        array(
                            '00:30 - 08:00',
                            '09:00 - 16:30',
                            '18:00 - 24:00',
                        ),
                    'GPV3.2' =>
                        array(
                            '00:00 - 04:00',
                            '05:00 - 12:30',
                            '13:30 - 21:00',
                            '22:00 - 24:00',
                        ),
                    'GPV4.1' =>
                        array(
                            '00:00 - 07:00',
                            '08:00 - 15:30',
                            '16:30 - 24:00',
                        ),
                    'GPV4.2' =>
                        array(
                            '01:00 - 08:30',
                            '09:30 - 17:00',
                            '18:00 - 24:00',
                        ),
                    'GPV5.1' =>
                        array(
                            '00:00 - 01:30',
                            '03:00 - 10:00',
                            '11:30 - 19:00',
                            '20:00 - 24:00',
                        ),
                    'GPV5.2' =>
                        array(
                            '01:30 - 09:00',
                            '10:00 - 17:30',
                            '18:30 - 24:00',
                        ),
                    'GPV6.1' =>
                        array(
                            '00:00 - 06:00',
                            '07:30 - 15:00',
                            '16:00 - 23:30',
                        ),
                    'GPV6.2' =>
                        array(
                            '00:00 - 02:30',
                            '03:30 - 11:00',
                            '12:00 - 19:30',
                            '20:30 - 24:00',
                        ),
                ),
            1769205600 =>//24.01.2026
                array(
                    'GPV1.1' =>
                        array(
                            '00:00 - 04:00',
                            '06:00 - 12:00',
                            '15:00 - 21:00',
                            '23:30 - 24:00',
                        ),
                    'GPV1.2' =>
                        array(
                            '00:00 - 04:30',
                            '06:30 - 13:00',
                            '16:00 - 20:30',
                        ),
                    'GPV2.1' =>
                        array(
                            '00:00 - 07:00',
                            '08:30 - 14:30',
                            '18:30 - 22:30',
                        ),
                    'GPV2.2' =>
                        array(
                            '00:00 - 06:00',
                            '08:00 - 13:30',
                            '17:30 - 22:00',
                        ),
                    'GPV3.1' =>
                        array(
                            '00:00 - 01:00',
                            '02:30 - 09:00',
                            '12:00 - 18:00',
                            '21:00 - 24:00',
                        ),
                    'GPV3.2' =>
                        array(
                            '00:00 - 05:30',
                            '07:00 - 13:00',
                            '17:00 - 22:00',
                        ),
                    'GPV4.1' =>
                        array(
                            '01:30 - 08:00',
                            '11:00 - 16:00',
                            '18:00 - 23:00',
                        ),
                    'GPV4.2' =>
                        array(
                            '00:00 - 01:30',
                            '03:30 - 09:30',
                            '12:00 - 17:00',
                            '20:00 - 24:00',
                        ),
                    'GPV5.1' =>
                        array(
                            '00:00 - 03:30',
                            '05:00 - 10:30',
                            '13:30 - 19:00',
                            '22:00 - 24:00',
                        ),
                    'GPV5.2' =>
                        array(
                            '00:00 - 00:30',
                            '03:00 - 10:00',
                            '12:30 - 18:30',
                            '21:00 - 24:00',
                        ),
                    'GPV6.1' =>
                        array(
                            '00:30 - 07:30',
                            '09:00 - 15:00',
                            '19:30 - 23:00',
                        ),
                    'GPV6.2' =>
                        array(
                            '00:00 - 02:30',
                            '04:30 - 10:30',
                            '14:00 - 19:30',
                            '22:00 - 24:00',
                        ),
                ),
        );

        foreach ($expected as $dayTs => $groups) {
            $this->assertArrayHasKey((string)$dayTs, $data['fact']['data']);
            foreach ($groups as $groupId => $intervals) {
                $this->assertArrayHasKey($groupId, $data['fact']['data'][(string)$dayTs]);
                $slots = $data['fact']['data'][(string)$dayTs][$groupId];
                $this->assertSame($intervals, OutageParser::parseOutageIntervals($slots));
            }
        }
    }
}
