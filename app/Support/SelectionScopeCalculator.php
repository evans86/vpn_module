<?php

namespace App\Support;

/**
 * Жёсткий scope: 100 × S_traffic × S_cpu (оба плохо → итог близок к 0).
 */
final class SelectionScopeCalculator
{
    public static function clamp01(float $value): float
    {
        if ($value < 0.0) {
            return 0.0;
        }
        if ($value > 1.0) {
            return 1.0;
        }

        return $value;
    }

    /**
     * @param float $trafficLimitTb лимит трафика за месяц, ТиБ
     * @param float $trafficUsedTb израсходовано с начала месяца, ТиБ
     * @param int $currentDay день месяца (1..31)
     * @param int $daysInMonth число дней в месяце
     * @param float $cpuUsagePercent загрузка CPU 0..100
     */
    public static function hardByCpuPercent(
        float $trafficLimitTb,
        float $trafficUsedTb,
        int $currentDay,
        int $daysInMonth,
        float $cpuUsagePercent
    ): float {
        if ($trafficLimitTb <= 0 || $currentDay <= 0 || $daysInMonth <= 0) {
            return 0.0;
        }

        $forecastTb = $trafficUsedTb * ($daysInMonth / $currentDay);
        $sTraffic = max(0.0, 1.0 - ($forecastTb / $trafficLimitTb));
        $sCpu = max(0.0, 1.0 - ($cpuUsagePercent / 100.0));
        $sCpu = self::clamp01($sCpu);

        return round(100.0 * $sTraffic * $sCpu, 2);
    }

    /**
     * Альтернатива: load average за 10 мин / число ядер.
     */
    public static function hardByLoadAverage(
        float $trafficLimitTb,
        float $trafficUsedTb,
        int $currentDay,
        int $daysInMonth,
        float $load10m,
        int $cpuCores
    ): float {
        if ($trafficLimitTb <= 0 || $currentDay <= 0 || $daysInMonth <= 0 || $cpuCores <= 0) {
            return 0.0;
        }

        $forecastTb = $trafficUsedTb * ($daysInMonth / $currentDay);
        $sTraffic = max(0.0, 1.0 - ($forecastTb / $trafficLimitTb));
        $cpuNorm = $load10m / $cpuCores;
        $sCpu = max(0.0, 1.0 - $cpuNorm);
        $sCpu = self::clamp01($sCpu);

        return round(100.0 * $sTraffic * $sCpu, 2);
    }
}
