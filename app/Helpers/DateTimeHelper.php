<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateTimeHelper
{
    /**
     * Форматирование даты и времени с учетом часового пояса приложения
     *
     * @param mixed $date Carbon объект, timestamp или строка
     * @param string $format Формат даты (по умолчанию 'd.m.Y H:i')
     * @return string
     */
    public static function format($date, string $format = 'd.m.Y H:i'): string
    {
        if (!$date) {
            return '-';
        }

        try {
            if ($date instanceof Carbon) {
                $carbon = $date->copy();
            } else {
                $carbon = Carbon::parse($date);
            }

            // Убеждаемся что используем часовой пояс приложения
            $timezone = config('app.timezone', 'Europe/Moscow');
            $carbon->setTimezone($timezone);

            return $carbon->format($format);
        } catch (\Exception $e) {
            return '-';
        }
    }

    /**
     * Форматирование даты и времени с секундами
     *
     * @param mixed $date
     * @return string
     */
    public static function formatWithSeconds($date): string
    {
        return self::format($date, 'd.m.Y H:i:s');
    }

    /**
     * Форматирование только даты
     *
     * @param mixed $date
     * @return string
     */
    public static function formatDate($date): string
    {
        return self::format($date, 'd.m.Y');
    }

    /**
     * Форматирование только времени
     *
     * @param mixed $date
     * @return string
     */
    public static function formatTime($date): string
    {
        return self::format($date, 'H:i');
    }

    /**
     * Получить текущее время в часовом поясе приложения
     *
     * @return Carbon
     */
    public static function now(): Carbon
    {
        $timezone = config('app.timezone', 'Europe/Moscow');
        return Carbon::now($timezone);
    }
}

