<?php
namespace PowerOutages;

final class OutageParser
{
    public static function formatHalfHourTime($halfHourIndex)
    {
        $minutes = $halfHourIndex * 30;
        if ($minutes === 1440) {
            return "24:00";
        }
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        return sprintf("%02d:%02d", $hours, $mins);
    }

    public static function parseOutageIntervals(array $slots)
    {
        $halfHours = array();

        for ($h = 1; $h <= 24; $h++) {
            $status = $slots[(string)$h];
            $hPlus = $h + 1;
            $hMinus = $h - 1;
//            print_r("\n$h - 1 = $hMinus");

            switch ($status) {
                case "no":
                    $halfHours[] = "no";
                    $halfHours[] = "no";
                    break;

                case "mfirst":
                    $halfHours[] = "no";

                    if ($hPlus <= 24) {
                        $next = $slots[(string)$hPlus];
                        if ($next === "yes" || $next === "msecond" || $next === "mfirst") {
                            $halfHours[] = "yes";
                        } else {
                            $halfHours[] = "no";
                        }
                    } else {
                        if ($hMinus >= 0) {
                            $prev = $slots[(string)$hMinus];

                            if ($prev === "no") {
                                $halfHours[] = "yes";
                            } else {
                                $halfHours[] = "no";
                            }
                        } else {
                            $halfHours[] = "no";
                        }
                    }
                    break;

                case "msecond":
                    if ($hMinus > 0) {
                        $prev = $slots[(string)$hMinus];

                        if ($prev === "no") {
                            $halfHours[] = "no";
                        } elseif ($prev === "mfirst" || $prev === "msecond") {
                            $halfHours[] = "yes";
                        } else {
                            $halfHours[] = "yes";
                        }
                    } else {
                        if ($hPlus <= 24) {
                            $next = $slots[(string)$hPlus];
                            if ($next === "yes") {
                                $halfHours[] = "no";
                            } else {
                                $halfHours[] = "yes";
                            }
                        } else {
                            echo 'here3';
                            $halfHours[] = "yes";
                        }
                    }

                    $halfHours[] = "no";
                    break;

                default:
                    $halfHours[] = "yes";
                    $halfHours[] = "yes";
                    break;
            }
        }

        $intervals = array();
        $inOutage = false;
        $startIndex = 0;
        $count = count($halfHours);

        for ($i = 0; $i <= $count; $i++) {
            $status = ($i < $count) ? $halfHours[$i] : "yes";

            if (!$inOutage && $status === "no") {
                $inOutage = true;
                $startIndex = $i;
            }

            if ($inOutage && $status !== "no") {
                $endIndex = $i;
                $intervals[] =
                    self::formatHalfHourTime($startIndex) . " - " . self::formatHalfHourTime($endIndex);
                $inOutage = false;
            }
        }

        return $intervals;
    }
}