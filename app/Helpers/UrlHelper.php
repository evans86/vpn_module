<?php

namespace App\Helpers;

class UrlHelper
{
    /**
     * Ссылка на страницу конфигурации ключа (публичный «фронт», часто зеркало).
     * Использует config('app.config_public_url') — APP_CONFIG_PUBLIC_URL.
     */
    public static function configUrl(string $keyActivateId): string
    {
        return rtrim((string) config('app.config_public_url'), '/') . '/config/' . $keyActivateId;
    }

    /**
     * URL маршрута личного кабинета продавца (/personal/...).
     * Канонический домен — APP_CONFIG_PUBLIC_URL (как страницы конфигов), а не APP_URL.
     *
     * В HTTP-запросе по умолчанию возвращается только путь (например /personal/faq/update).
     * Тогда формы и fetch идут на тот же origin, что и страница — без абсолютных ссылок на
     * другой хост/схему и без редиректов, превращающих POST в GET (405).
     *
     * Полный URL (APP_CONFIG_PUBLIC_URL + путь) — в консоли/очередях и при $forceAbsolute = true
     * (Telegram OAuth callback и т.п.).
     *
     * @param  string  $name  Имя маршрута, например personal.dashboard
     */
    public static function personalRoute(string $name, array $parameters = [], bool $forceAbsolute = false): string
    {
        $path = route($name, $parameters, false);
        $path = '/' . ltrim((string) $path, '/');
        $base = rtrim((string) config('app.config_public_url'), '/');

        if (!$forceAbsolute && !app()->runningInConsole() && request()) {
            return $path;
        }

        return $base . $path;
    }

    /**
     * Полные URL конфигурации на каждом зеркале (без основного public_url).
     *
     * @return list<string>
     */
    public static function configMirrorUrls(string $keyActivateId): array
    {
        $mirrors = config('app.mirror_urls', []);
        if (!is_array($mirrors)) {
            return [];
        }
        $primaryHost = parse_url(rtrim((string) config('app.config_public_url'), '/'), PHP_URL_HOST);
        $out = [];
        foreach ($mirrors as $base) {
            $base = rtrim((string) $base, '/');
            if ($base === '') {
                continue;
            }
            $host = parse_url(str_contains($base, '://') ? $base : 'https://' . $base, PHP_URL_HOST);
            if ($primaryHost && $host && strcasecmp((string) $host, (string) $primaryHost) === 0) {
                continue; // не дублировать основной домен в зеркалах
            }
            $out[] = $base . '/config/' . $keyActivateId;
        }
        return $out;
    }

    /**
     * Основная ссылка + все зеркала (основная первая, без дубликатов).
     *
     * @return list<string>
     */
    public static function configUrlsAll(string $keyActivateId): array
    {
        $primary = self::configUrl($keyActivateId);
        $all = array_merge([$primary], self::configMirrorUrls($keyActivateId));
        return array_values(array_unique($all));
    }

    /**
     * Для JSON API: основная ссылка и массив зеркал отдельно.
     *
     * @return array{config_url: string, config_mirror_urls: list<string>, config_urls_all: list<string>}
     */
    public static function configUrlsPayload(string $keyActivateId): array
    {
        return [
            'config_url' => self::configUrl($keyActivateId),
            'config_mirror_urls' => self::configMirrorUrls($keyActivateId),
            'config_urls_all' => self::configUrlsAll($keyActivateId),
        ];
    }

    /**
     * Блок ссылок для Telegram (HTML): основная и опционально зеркала.
     *
     * @param bool $includeMirrors false — одна ссылка «Конфигурация» (для списков подписок в боте)
     */
    public static function telegramConfigLinksHtml(string $keyActivateId, bool $includeMirrors = true): string
    {
        $primary = self::configUrl($keyActivateId);
        $esc = static fn (string $u): string => htmlspecialchars($u, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!$includeMirrors) {
            return '🔗 <a href="' . $esc($primary) . '">Конфигурация</a>';
        }
        $lines = ['🔗 <a href="' . $esc($primary) . '">Конфигурация (основной сайт)</a>'];
        foreach (self::configMirrorUrls($keyActivateId) as $i => $url) {
            $n = $i + 1;
            $lines[] = '🔗 <a href="' . $esc($url) . '">Зеркало ' . $n . '</a>';
        }
        return implode("\n", $lines);
    }

    /**
     * Текст со всеми URL построчно (копирование, если основной домен заблокирован).
     */
    public static function telegramConfigLinksPlain(string $keyActivateId): string
    {
        $lines = ['Основной: ' . self::configUrl($keyActivateId)];
        foreach (self::configMirrorUrls($keyActivateId) as $i => $url) {
            $lines[] = 'Зеркало ' . ($i + 1) . ': ' . $url;
        }
        return implode("\n", $lines);
    }

    /**
     * Одна строка Markdown-ссылки для основного URL (как раньше).
     */
    public static function telegramConfigLinkMarkdown(string $keyActivateId): string
    {
        return '[Открыть конфигурацию](' . self::configUrl($keyActivateId) . ')';
    }

    /**
     * Строки inline_keyboard для Telegram: основной конфиг + зеркала.
     *
     * @return list<list<array{text: string, url: string}>>
     */
    public static function telegramInlineKeyboardConfigRows(string $keyActivateId): array
    {
        $rows = [
            [['text' => '🔗 Конфиг (основной)', 'url' => self::configUrl($keyActivateId)]],
        ];
        foreach (self::configMirrorUrls($keyActivateId) as $i => $url) {
            $rows[] = [['text' => 'Зеркало ' . ($i + 1), 'url' => $url]];
        }
        return $rows;
    }
}
