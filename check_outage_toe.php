<?php
date_default_timezone_set('Europe/Kyiv');

function logLine(string $message): void
{
    $ts = date('Y-m-d H:i:s');
    echo "[{$ts}] {$message}\n";
}

function fatal(string $message, int $exitCode = 1): void
{
    logLine($message);
    exit($exitCode);
}

$groupsConfigPath = __DIR__ . '/config/groups.local.php';
if (!file_exists($groupsConfigPath)) {
    fatal("Не знайдено конфігурацію груп: {$groupsConfigPath}");
}

$groupsConfig = require $groupsConfigPath;
$cacheFile = __DIR__ . '/cache/last_update_time_toe.txt';

require_once __DIR__ . '/src/OutageParser.php';
require_once __DIR__ . '/src/ToeOutageParser.php';

use PowerOutages\ToeOutageParser;

try {
    $allChergGroups = ToeOutageParser::fetchChergGpvGroups('329');
} catch (RuntimeException $e) {
    fatal('Не вдалося отримати групи: ' . $e->getMessage());
}

$groupTargets = array();
foreach ($allChergGroups as $chergGpv) {
    $configKey = $chergGpv;
    if (strpos($chergGpv, '#') !== false) {
        $configKey = 'GPV' . strstr($chergGpv, '#', true);
    }

    if (isset($groupsConfig[$configKey])) {
        $groupTargets[] = array('cherg' => $chergGpv, 'config' => $configKey);
    } elseif (isset($groupsConfig[$chergGpv])) {
        $groupTargets[] = array('cherg' => $chergGpv, 'config' => $chergGpv);
    }
}

if (empty($groupTargets)) {
    fatal('Немає груп для обробки за конфігурацією');
}

$nowUtc = new DateTime('now', new DateTimeZone('UTC'));
$before = (clone $nowUtc)->modify('+1 day')->setTime(0, 0, 0);
$after = (clone $nowUtc)->modify('-1 day')->setTime(12, 0, 0);
$timeParam = random_int(1, 1000000);

try {
    $response = ToeOutageParser::fetchActualGraphs(
        array_column($groupTargets, 'cherg'),
        $before,
        $after,
        $timeParam
    );
} catch (RuntimeException $e) {
    fatal('Не вдалося отримати графік: ' . $e->getMessage());
}

if (!isset($response['hydra:member']) || !is_array($response['hydra:member'])) {
    fatal('Невалідна відповідь графіка');
}

$latestDateCreate = '';
$latestDateCreateTs = 0;
$schedulesByTarget = array();

foreach ($response['hydra:member'] as $item) {
    if (!isset($item['dateGraph'], $item['dataJson'])) {
        continue;
    }

    $dateGraph = new DateTime($item['dateGraph']);
    $dayTs = $dateGraph->getTimestamp();

    if (isset($item['dateCreate'])) {
        $dateCreateTs = (new DateTime($item['dateCreate']))->getTimestamp();
        if ($dateCreateTs > $latestDateCreateTs) {
            $latestDateCreateTs = $dateCreateTs;
            $latestDateCreate = $item['dateCreate'];
        }
    }

    foreach ($groupTargets as $target) {
        $chergGpv = $target['cherg'];
        $configKey = $target['config'];

        if (!isset($item['dataJson'][$chergGpv]['times'])) {
            continue;
        }

        $intervals = ToeOutageParser::parseOutageIntervalsFromTimes($item['dataJson'][$chergGpv]['times']);
        $schedulesByTarget[$configKey][$dayTs] = $intervals;
    }
}

$lastUpdateFromFile = file_exists($cacheFile) ? trim(file_get_contents($cacheFile)) : '';
if ($latestDateCreate !== '' && $latestDateCreate === $lastUpdateFromFile) {
    logLine('Оновлень немає');
    exit;
}

$weekDays = array('Нд', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб');

foreach ($groupTargets as $target) {
    $configKey = $target['config'];
    $config = $groupsConfig[$configKey];
    $message = '';
    $hasData = false;

    if (isset($schedulesByTarget[$configKey])) {
        ksort($schedulesByTarget[$configKey]);
        foreach ($schedulesByTarget[$configKey] as $dayTs => $intervals) {
            $dateObj = new DateTime("@{$dayTs}");
            $dateObj->setTimezone(new DateTimeZone('Europe/Kyiv'));
            $dayName = $weekDays[$dateObj->format('w')];
            $dateStr = $dateObj->format('d.m');

            $message .= "<b>{$dateStr} ({$dayName}):</b>\n";
            if (empty($intervals)) {
                $message .= "🟢 Відключень не заплановано\n";
            } else {
                foreach ($intervals as $interval) {
                    $message .= "🔴 <b>{$interval}</b>\n";
                }
            }
            $message .= "\n";
            $hasData = true;
        }
    }

    if ($hasData) {
        $filePutMessage = trim(base64_encode($message));
        $cacheFileMessage = __DIR__ . "/cache/last_schedule_{$configKey}.txt";
        $lastTimeMessage = file_exists($cacheFileMessage) ? trim(file_get_contents($cacheFileMessage)) : '';

        $message .= "ℹ️ Оновлено ({$configKey})\n";
        if ($latestDateCreate !== '') {
            $message .= "ℹ️ {$latestDateCreate}";
        }

        if ($filePutMessage !== $lastTimeMessage) {
            $tgUrl = "https://api.telegram.org/bot{$config['token']}/sendMessage";
            file_get_contents($tgUrl . "?" . http_build_query([
                    'chat_id' => $config['chat_id'],
                    'text' => $message,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true
                ]));
            logLine("Надіслано для {$configKey}");
            file_put_contents($cacheFileMessage, $filePutMessage);
        } else {
            logLine("Оновлень немає для {$configKey}");
        }
    }
}

if ($latestDateCreate !== '') {
    file_put_contents($cacheFile, $latestDateCreate);
}
