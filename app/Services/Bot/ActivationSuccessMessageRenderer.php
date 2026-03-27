<?php

namespace App\Services\Bot;

use App\Helpers\UrlHelper;
use App\Models\KeyActivate\KeyActivate;
use App\Models\Salesman\Salesman;

/**
 * Шаблон сообщения в боте активации после успешной активации ключа (HTML для Telegram).
 */
final class ActivationSuccessMessageRenderer
{
    /**
     * Типовой шаблон, если в ЛК не задан свой (плейсхолдеры подставляются при отправке).
     */
    public const DEFAULT_TEMPLATE = <<<'HTML'
✅ <b>VPN успешно активирован!</b>

📅 Срок действия: до {EXPIRY_DATE}

🔗 <b>Ваша VPN-конфигурация:</b>

{CONFIG_LINKS}

📝 <b>Инструкция по настройке:</b>

1️⃣ Установите VPN-клиент на Ваше устройство
2️⃣ Скопируйте ссылку конфигурации выше
3️⃣ Следуйте инструкциям для подключения на различных устройствах

❓ Если возникли вопросы, обратитесь к администратору бота

📱 Инструкции для настройки подключения:
HTML;

    public static function defaultTemplate(): string
    {
        return self::DEFAULT_TEMPLATE;
    }

    public static function render(Salesman $salesman, KeyActivate $key): string
    {
        $raw = trim((string) ($salesman->custom_activation_success_text ?? ''));
        $template = $raw !== '' ? $raw : self::DEFAULT_TEMPLATE;

        return self::applyPlaceholders($template, $key);
    }

    private static function applyPlaceholders(string $template, KeyActivate $key): string
    {
        $keyId = (string) $key->id;
        $finishAt = $key->finish_at ? (int) $key->finish_at : null;
        $expiry = $finishAt ? date('d.m.Y', $finishAt) : '—';

        $template = str_replace('{EXPIRY_DATE}', $expiry, $template);
        $template = str_replace('{KEY_ID}', $keyId, $template);
        $template = str_replace('{CONFIG_URL}', UrlHelper::configUrl($keyId), $template);
        $template = str_replace('{CONFIG_LINKS}', UrlHelper::telegramConfigLinksHtml($keyId), $template);

        $mirrors = UrlHelper::configMirrorUrls($keyId);
        for ($n = 10; $n >= 1; $n--) {
            $url = isset($mirrors[$n - 1]) ? (string) $mirrors[$n - 1] : '';
            $template = str_replace(['{MIRROR_'.$n.'}', '{MIRRO_'.$n.'}'], $url, $template);
        }

        return $template;
    }
}
