<?php

namespace App\Support;

use App\Models\Server\Server;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Код провайдера (server.provider): для API — vdsina/timeweb; для ручных серверов — произвольный slug.
 */
final class ServerProviderCode
{
    /** Зарезервировано под провайдеров с API (нельзя задать вручную). */
    private const RESERVED_API = [
        Server::VDSINA,
        Server::TIMEWEB,
    ];

    public static function fromLabel(string $label): string
    {
        $slug = Str::slug(trim($label), '-', 'ru');
        $slug = strtolower($slug);
        if ($slug === '') {
            throw new InvalidArgumentException('Укажите осмысленное название провайдера.');
        }
        if (strlen($slug) > 64) {
            $slug = substr($slug, 0, 64);
            $slug = rtrim($slug, '-');
        }
        self::assertValidSlug($slug);

        return $slug;
    }

    public static function assertValidSlug(string $slug): void
    {
        $slug = strtolower(trim($slug));
        if ($slug === '' || strlen($slug) > 64) {
            throw new InvalidArgumentException('Код провайдера: 1–64 символа, латиница, цифры и дефисы.');
        }
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug)) {
            throw new InvalidArgumentException('Код провайдера: только a–z, 0–9 и дефисы между словами.');
        }
        if (in_array($slug, self::RESERVED_API, true)) {
            throw new InvalidArgumentException('Это имя зарезервировано под провайдера с API (VDSina / Timeweb).');
        }
    }
}
