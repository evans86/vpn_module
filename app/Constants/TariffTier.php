<?php

namespace App\Constants;

/**
 * Тарифы для привязки серверов к выдаче ключей (активация / будущие пакеты).
 */
final class TariffTier
{
    public const FREE = 'free';

    public const FULL = 'full';

    public const WHITELIST = 'whitelist';

    /** @return string[] */
    public static function all(): array
    {
        return [self::FREE, self::FULL, self::WHITELIST];
    }

    /**
     * Нормализация значения из БД: null, пустая строка и неизвестный код → full (как в миграции по умолчанию).
     * Нужна в т.ч. для селекта в админке: иначе при '' ни один option не selected, браузер показывает первый (free).
     */
    public static function normalize(?string $tier): string
    {
        if ($tier === null || $tier === '') {
            return self::FULL;
        }

        $t = strtolower(trim($tier));

        return in_array($t, self::all(), true) ? $t : self::FULL;
    }

    /**
     * Подпись для админки (в БД и конфиге по-прежнему латинские значения).
     */
    public static function label(?string $tier): string
    {
        if ($tier === null || $tier === '') {
            return '—';
        }

        $t = strtolower(trim($tier));
        switch ($t) {
            case self::FREE:
                return 'Бесплатный пул';
            case self::FULL:
                return 'Основная выдача';
            case self::WHITELIST:
                return 'Белый список';
            default:
                return $tier;
        }
    }
}
