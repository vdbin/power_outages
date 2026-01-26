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
$test = false;

$jsonUrl = "https://raw.githubusercontent.com/yaroslav2901/OE_OUTAGE_DATA/refs/heads/main/data/Ternopiloblenerho.json";
$cacheFile = __DIR__ . "/cache/last_update_time.txt";

if ($test) {
    $jsonPath = __DIR__ . "/tests/example.json";
    $response = file_get_contents($jsonPath);
    if ($response === false) {
        fatal("Не вдалося прочитати файл: $jsonPath");
    }
} else {
    $ch = curl_init($jsonUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "PHP-Parser");
    $response = curl_exec($ch);
}

$data = json_decode($response, true);

if (!$data || !isset($data['fact']['update'])) {
    fatal("Помилка JSON");
}

$currentUpdateText = $data['fact']['update'];
$lastUpdatedFromJson = $data['lastUpdated'];
$lastUpdateFromFile = file_exists($cacheFile) ? trim(file_get_contents($cacheFile)) : "";

if (!$test) {
    if ($lastUpdatedFromJson === $lastUpdateFromFile) {
        logLine("Оновлень немає");
        exit;
    }
}

require_once __DIR__ . '/src/OutageParser.php';

use PowerOutages\OutageParser;

$weekDays = ["Нд", "Пн", "Вт", "Ср", "Чт", "Пт", "Сб"];

foreach ($groupsConfig as $targetGroup => $config) {
    $message = "";
    $hasData = false;

    foreach ($data['fact']['data'] as $dayTimestamp => $groups) {
        if (!isset($groups[$targetGroup])) continue;

        $dateObj = new DateTime("@$dayTimestamp");
        $dateObj->setTimezone(new DateTimeZone('Europe/Kyiv'));
        $dayName = $weekDays[$dateObj->format('w')];
        $dateStr = $dateObj->format('d.m');

        $message .= "<b>$dateStr ($dayName):</b>\n";

        $mergedIntervals = OutageParser::parseOutageIntervals($groups[$targetGroup]);

        if (empty($mergedIntervals)) {
            $message .= "🟢 Відключень не заплановано\n";
        } else {
            foreach ($mergedIntervals as $interval) {
                $message .= "🔴 <b>$interval</b>\n";
            }
        }
        $message .= "\n";
        $hasData = true;
    }

    if ($hasData) {
        $filePutMessage = trim(base64_encode($message));
        $cacheFileMessage = __DIR__ . "/cache/last_schedule_{$targetGroup}.txt";
        $lastTimeMessage = file_exists($cacheFileMessage) ? trim(file_get_contents($cacheFileMessage)) : "";

        if ($targetGroup != "GPV4.1") {
            $message .= "🔗 <a href='https://www.toe.com.ua/news/71'>Сайт TOE</a>\n";
        }
        $titleTargetGroup = str_replace('GPV', '', $targetGroup);
        $message .= "ℹ️ Оновлено ({$titleTargetGroup})\n";
        $message .= "ℹ️ " . $currentUpdateText;

        if ($test) {
            print_r($message."\n\n\n");
        } else {
            if ($filePutMessage !== $lastTimeMessage) {
                $tgUrl = "https://api.telegram.org/bot{$config['token']}/sendMessage";
                file_get_contents($tgUrl . "?" . http_build_query([
                        'chat_id' => $config['chat_id'],
                        'text' => $message,
                        'parse_mode' => 'HTML',
                        'disable_web_page_preview' => true
                    ]));
                logLine("Надіслано для $targetGroup");
                file_put_contents($cacheFileMessage, $filePutMessage);
            } else {
                logLine("Оновлень немає для {$targetGroup}");
            }
        }
    }
}

file_put_contents($cacheFile, $lastUpdatedFromJson);
