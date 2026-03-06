<?php

namespace App\Helpers;

class UrlHelper
{
    /**
     * Ссылка на страницу конфигурации ключа (для бота, уведомлений, API).
     * Использует config('app.public_url') — задаётся через APP_PUBLIC_URL (по умолчанию APP_URL).
     */
    public static function configUrl(string $keyActivateId): string
    {
        return config('app.public_url') . '/config/' . $keyActivateId;
    }
}
