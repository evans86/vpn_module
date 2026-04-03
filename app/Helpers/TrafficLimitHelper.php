<?php

namespace App\Helpers;

/**
 * Квота key_activate.traffic_limit между слотами Marzban (сумма частей = $totalBytes).
 */
final class TrafficLimitHelper
{
    /**
     * @return list<int>
     */
    public static function distributeAcrossSlots(int $totalBytes, int $slotCount): array
    {
        if ($slotCount < 1) {
            $slotCount = 1;
        }
        if ($totalBytes < 0) {
            $totalBytes = 0;
        }
        $base = intdiv($totalBytes, $slotCount);
        $remainder = $totalBytes % $slotCount;
        $out = [];
        for ($i = 0; $i < $slotCount; $i++) {
            $out[] = $base + ($i < $remainder ? 1 : 0);
        }

        return $out;
    }
}
