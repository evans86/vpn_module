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
                return 'Отдельный список (whitelist)';
            default:
                return $tier;
        }
    }
}
