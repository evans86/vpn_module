<?php

namespace App\Models\VPN;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class VpnDirectDomain extends Model
{
    public const CACHE_KEY = 'vpn_direct_domains:v2';

    protected $table = 'vpn_direct_domains';

    protected $fillable = [
        'domain',
        'sort_order',
        'is_enabled',
        'note',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(static function (): void {
            Cache::forget(self::CACHE_KEY);
        });
        static::deleted(static function (): void {
            Cache::forget(self::CACHE_KEY);
        });
    }

    /**
     * Нормализация ввода: нижний регистр, убрать схему URL, хост из ссылки.
     */
    public static function normalizeDomain(string $raw): string
    {
        $raw = trim(mb_strtolower($raw));
        if ($raw === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $raw)) {
            $host = parse_url($raw, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                return $host;
            }
        }
        $raw = preg_replace('#^//+#', '', $raw);

        $raw = rtrim($raw, '/');

        // Зона верхнего уровня: ".ru" → "ru" (для DOMAIN-SUFFIX в Clash / sing-box)
        if (preg_match('/^\.+([a-z0-9][a-z0-9.-]*)$/i', $raw, $m)) {
            return $m[1];
        }

        // *.ru → ru (одна метка зоны; иначе оставляем *.sub.domain.tld)
        if (preg_match('/^\*\.(.+)$/i', $raw, $m) && strpos($m[1], '.') === false) {
            return $m[1];
        }

        return $raw;
    }
}
