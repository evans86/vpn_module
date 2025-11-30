<?php

namespace App\Constants;

/**
 * Константы времени
 */
class TimeConstants
{
    /** Секунд в минуте */
    public const SECONDS_IN_MINUTE = 60;

    /** Секунд в часе */
    public const SECONDS_IN_HOUR = 3600;

    /** Секунд в дне */
    public const SECONDS_IN_DAY = 86400;

    /** Секунд в неделе */
    public const SECONDS_IN_WEEK = 604800;

    /** Секунд в месяце (30 дней) */
    public const SECONDS_IN_MONTH = 2592000;

    /** Время жизни токена панели (почти день) */
    public const PANEL_TOKEN_LIFETIME = 85400;
}

