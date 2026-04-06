<?php

namespace App\Services\Key;

use App\Constants\TariffTier;
use App\Models\KeyActivate\KeyActivate;

/**
 * Определяет server.tariff_tier для выбора панелей при активации.
 *
 * Бесплатные ключи (без pack/module sales) пока идут в тот же пул, что и платные (full),
 * пока нет отдельной инфраструктуры под TariffTier::FREE.
 */
class ActivationTariffResolver
{
    public function resolve(KeyActivate $key): string
    {
        $override = $key->activation_tariff_tier ?? null;
        if (is_string($override) && $override !== '') {
            $normalized = strtolower(trim($override));
            if (in_array($normalized, TariffTier::all(), true)) {
                return $normalized;
            }
        }

        if ($key->isFreeIssuedKey()) {
            return TariffTier::FULL;
        }

        $key->loadMissing(['packSalesman.pack']);

        $packTier = $key->packSalesman && $key->packSalesman->pack
            ? ($key->packSalesman->pack->activation_tariff_tier ?? null)
            : null;

        if (is_string($packTier) && $packTier !== '') {
            $normalized = strtolower(trim($packTier));
            if (in_array($normalized, TariffTier::all(), true)) {
                return $normalized;
            }
        }

        $fallback = strtolower(trim((string) config('panel.activation_tariff_tier', TariffTier::FULL)));

        return in_array($fallback, TariffTier::all(), true) ? $fallback : TariffTier::FULL;
    }
}
