<?php

namespace App\Helpers;

/**
 * Флаг страны (emoji) по двухбуквенному коду ISO 3166-1 alpha-2 (как в location.code).
 */
final class CountryFlagHelper
{
    public static function emojiFromAlpha2(?string $code): string
    {
        if ($code === null || $code === '') {
            return '';
        }

        $code = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $code), 0, 2));
        if (strlen($code) !== 2) {
            return '';
        }

        $a = 0x1F1E6 + (ord($code[0]) - 0x41);
        $b = 0x1F1E6 + (ord($code[1]) - 0x41);

        return self::codePointToUtf8($a) . self::codePointToUtf8($b);
    }

    /**
     * UTF-8 флаг из location.code и сырого location.emoji («:nl:», HTML-сущности, уже emoji).
     */
    public static function resolvedEmojiFromStored(?string $countryCode, ?string $storedEmoji): string
    {
        $code = strtoupper(trim((string) ($countryCode ?? '')));
        $raw = trim((string) ($storedEmoji ?? ''));

        if ($raw !== '') {
            if (preg_match('/^:([a-z]{2}):$/i', $raw, $m)) {
                return self::emojiFromAlpha2($m[1]);
            }

            if (strpos($raw, '&') !== false && strpos($raw, ';') !== false && strpos($raw, '#') !== false) {
                $decoded = html_entity_decode($raw, ENT_HTML5 | ENT_HTML401, 'UTF-8');
                if (preg_match('/[\x{1F1E6}-\x{1F1FF}]{2}/u', $decoded, $match)) {
                    return $match[0];
                }
            }

            if (preg_match('/^[\x{1F1E6}-\x{1F1FF}]{2}$/u', $raw)) {
                return $raw;
            }
        }

        return self::emojiFromAlpha2($code);
    }

    /**
     * URL картинки флага (ISO 3166-1 alpha-2) для &lt;img&gt; рядом с селектом.
     * Внутри &lt;option&gt; браузеры не поддерживают изображения.
     */
    public static function flagCdnUrl(?string $countryCode): ?string
    {
        $c = strtolower(substr(preg_replace('/[^A-Za-z]/', '', (string) $countryCode), 0, 2));
        if (strlen($c) !== 2) {
            return null;
        }

        return 'https://flagcdn.com/w20/'.$c.'.png';
    }

    /** Двухбуквенный код (пустая строка, если не задан). */
    public static function countryCodeAlpha2(?string $countryCode): string
    {
        return strtoupper(trim((string) ($countryCode ?? '')));
    }

    /** Для текста опций выпадающего списка. */
    public static function countryCodeLabel(?string $countryCode): string
    {
        $code = self::countryCodeAlpha2($countryCode);

        return $code !== '' ? $code : '—';
    }

    /**
     * @deprecated по смыслу для подписей с флагом: в &lt;option&gt; — countryCodeLabel + flagCdnUrl.
     */
    public static function countryLabelWithFlag(?string $countryCode, ?string $storedEmoji): string
    {
        unset($storedEmoji);

        return self::countryCodeLabel($countryCode);
    }

    private static function codePointToUtf8(int $cp): string
    {
        $decoded = html_entity_decode('&#x' . dechex($cp) . ';', ENT_HTML5, 'UTF-8');
        if ($decoded !== '') {
            return $decoded;
        }

        return function_exists('mb_chr')
            ? (string) mb_chr($cp, 'UTF-8')
            : '';
    }
}
