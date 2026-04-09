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

    private static function codePointToUtf8(int $cp): string
    {
        return html_entity_decode('&#x' . dechex($cp) . ';', ENT_HTML5, 'UTF-8');
    }
}
