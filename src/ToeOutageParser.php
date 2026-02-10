<?php
namespace PowerOutages;

use DateTime;
use RuntimeException;

final class ToeOutageParser
{
    public static function fetchChergGpvGroups(string $cityId): array
    {
        $url = "https://api-poweron.toe.com.ua/api/pw-accounts/building-groups?cityId={$cityId}";
//        $data = ['buildingGroups' => [['chergGpv' => '1.2#Пд']]];
        $data = self::fetchJson($url, array('Origin: https://poweron.toe.com.ua'));
        if (!isset($data['buildingGroups']) || !is_array($data['buildingGroups'])) {
            throw new RuntimeException('Unexpected building groups response');
        }

        $groups = array();
        foreach ($data['buildingGroups'] as $group) {
            if (isset($group['chergGpv']) && $group['chergGpv'] !== '') {
                $groups[] = $group['chergGpv'];
            }
        }

        return array_values(array_unique($groups));
    }

    public static function fetchActualGraphs(array $chergGroups, DateTime $before, DateTime $after, int $timeParam): array
    {
        $query = self::buildQuery($chergGroups, $before, $after, $timeParam);
        $url = "https://api-poweron.toe.com.ua/api/a_gpv_g?{$query}";
        $headers = array(
            'Accept: application/json, text/plain, */*',
            'Accept-Language: uk-UA,uk;q=0.9',
            'Cache-Control: no-cache',
            'Origin: https://poweron.toe.com.ua',
            'Pragma: no-cache',
            'Referer: https://poweron.toe.com.ua/',
            'X-debug-key: MzI5LzMxNDcvMzM=',
        );

        return self::fetchJson($url, $headers);
    }

    public static function parseOutageIntervalsFromTimes(array $times): array
    {
        $halfHours = array_fill(0, 48, '0');
        foreach ($times as $time => $value) {
            $index = self::timeToHalfHourIndex($time);
            if ($index === null) {
                continue;
            }
            if (self::isOutageValue($value)) {
                $halfHours[$index] = '1';
                continue;
            }
            if (self::isSwitchValue($value)) {
                $halfHours[$index] = '10';
            }
        }

        return self::buildIntervalsFromHalfHours($halfHours);
    }

    private static function isOutageValue($value): bool
    {
        $value = (string)$value;
        return $value === '1';
    }

    private static function isSwitchValue($value): bool
    {
        $value = (string)$value;
        return $value === '10';
    }

    private static function buildIntervalsFromHalfHours(array $halfHours): array
    {
        $intervals = array();
        $count = count($halfHours);
        $i = 0;

        while ($i < $count) {
            if ($halfHours[$i] !== '1') {
                $i++;
                continue;
            }

            $startIndex = $i;
            while ($i < $count && $halfHours[$i] === '1') {
                $i++;
            }
            $endIndex = $i;

            $switchStartIndex = null;
            $j = $startIndex - 1;
            while ($j >= 0 && $halfHours[$j] === '10') {
                $j--;
            }
            if ($j !== $startIndex - 1) {
                $switchStartIndex = $j + 1;
            }

            $switchEndIndex = null;
            $k = $endIndex;
            while ($k < $count && $halfHours[$k] === '10') {
                $k++;
            }
            if ($k !== $endIndex) {
                $switchEndIndex = $k;
            }

            $startStr = OutageParser::formatHalfHourTime($startIndex);
            if ($switchStartIndex !== null) {
                $startStr = OutageParser::formatHalfHourTime($switchStartIndex) . '/' . $startStr;
            }

            $endStr = OutageParser::formatHalfHourTime($endIndex);
            if ($switchEndIndex !== null) {
                $endStr = $endStr . '/' . OutageParser::formatHalfHourTime($switchEndIndex);
            }

            $intervals[] = $startStr . ' - ' . $endStr;
        }

        return $intervals;
    }

    private static function timeToHalfHourIndex(string $time): ?int
    {
        if (!preg_match('/^([01]\d|2[0-3]):(00|30)$/', $time, $matches)) {
            return null;
        }

        $hours = (int)$matches[1];
        $minutes = (int)$matches[2];

        return $hours * 2 + ($minutes === 30 ? 1 : 0);
    }

    private static function buildQuery(array $chergGroups, DateTime $before, DateTime $after, int $timeParam): string
    {
        $parts = array(
            'before=' . rawurlencode($before->format('Y-m-d\\TH:i:sP')),
            'after=' . rawurlencode($after->format('Y-m-d\\TH:i:sP')),
        );

        foreach ($chergGroups as $group) {
            $parts[] = 'group[]=' . rawurlencode($group);
        }

        // The API expects a random time parameter to bypass caching.
        $parts[] = 'time='.$timeParam;

        return implode('&', $parts);
    }

    private static function fetchJson(string $url, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.2 Safari/605.1.15');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        if ($response === false) {
            $error = curl_error($ch);
            self::logRequest($url, $headers, $info, 'CURL_ERROR: ' . $error);
            throw new RuntimeException('Request failed: ' . $error);
        }

        self::logRequest($url, $headers, $info, $response);

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid JSON response');
        }

        return $data;
    }

    private static function logRequest(string $url, array $headers, array $info, string $body): void
    {
        $logPath = __DIR__ . '/../logs/cron.log';
        $lines = array(
            '--- ' . date('Y-m-d H:i:s') . ' ---',
            'URL: ' . $url,
            'Headers: ' . implode('; ', $headers),
            'HTTP: ' . (isset($info['http_code']) ? $info['http_code'] : 'n/a'),
            'Content-Type: ' . (isset($info['content_type']) ? $info['content_type'] : 'n/a'),
            'Body: ' . $body,
            '',
        );

        file_put_contents($logPath, implode("\n", $lines) . "\n", FILE_APPEND);
    }
}
