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
}
