<?php
$groupsConfigPath = __DIR__ . '/config/groups.local.php';
if (!file_exists($groupsConfigPath)) {
    die("Не знайдено конфігурацію груп: {$groupsConfigPath}\n");
}

$groupsConfig = require $groupsConfigPath;
$test = false;

$jsonUrl = "https://raw.githubusercontent.com/yaroslav2901/OE_OUTAGE_DATA/refs/heads/main/data/Ternopiloblenerho.json";
$cacheFile = __DIR__ . "/cache/last_update_time.txt";

if ($test) {
    $jsonPath = __DIR__ . "/tests/example.json";
    $response = file_get_contents($jsonPath);
    if ($response === false) {
        die("Не вдалося прочитати файл: $jsonPath\n");
    }
} else {
    $ch = curl_init($jsonUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "PHP-Parser");
    $response = curl_exec($ch);
}

$data = json_decode($response, true);

if (!$data || !isset($data['fact']['update'])) {
    die("Помилка JSON\n");
}

$currentUpdateText = $data['fact']['update'];
$lastUpdateText = file_exists($cacheFile) ? trim(file_get_contents($cacheFile)) : "";

if (!$test) {
    if ($currentUpdateText === $lastUpdateText) {
        echo "Оновлень немає.\n";
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

        if (!$test) {
            if ($filePutMessage !== $lastTimeMessage) {
                if ($targetGroup != "GPV4.1") {
                    $message .= "🔗 <a href='https://www.toe.com.ua/news/71'>Сайт TOE</a>\n";
                }
                $titleTargetGroup = str_replace('GPV', '', $targetGroup);
                $message .= "ℹ️ Оновлено ({$titleTargetGroup})\n";
                $message .= "ℹ️ " . date("H:i d.m");

                $tgUrl = "https://api.telegram.org/bot{$config['token']}/sendMessage";
                file_get_contents($tgUrl . "?" . http_build_query([
                        'chat_id' => $config['chat_id'],
                        'text' => $message,
                        'parse_mode' => 'HTML',
                        'disable_web_page_preview' => true
                    ]));
                echo "Надіслано для $targetGroup\n";
                file_put_contents($cacheFileMessage, $filePutMessage);
            } else {
                echo "Оновлень немає для {$targetGroup}\n";
            }
        }
    }
}

file_put_contents($cacheFile, $currentUpdateText);
