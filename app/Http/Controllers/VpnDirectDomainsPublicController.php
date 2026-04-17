<?php

namespace App\Http\Controllers;

use App\Models\VPN\VpnDirectDomain;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class VpnDirectDomainsPublicController extends Controller
{
    /**
     * Публичный список доменов для маршрутизации «без VPN» (Direct).
     * Используется клиентами и внешними инструментами, поддерживающими remote rules.
     */
    public function json(): JsonResponse
    {
        $seconds = max(30, (int) config('vpn.direct_domains_cache_seconds', 120));
        $bundle = $this->cachedBundle($seconds);

        return response()->json($bundle['manifest'])
            ->header('Cache-Control', 'public, max-age='.$seconds);
    }

    /**
     * Файл rule-set в source-формате sing-box (version 3) для remote rule_set.
     *
     * @see https://sing-box.sagernet.org/configuration/rule-set/source-format/
     */
    public function ruleSetSource(): JsonResponse
    {
        $seconds = max(30, (int) config('vpn.direct_domains_cache_seconds', 120));
        $bundle = $this->cachedBundle($seconds);

        return response()->json($bundle['rule_set_source'])
            ->header('Cache-Control', 'public, max-age='.$seconds);
    }

    /**
     * @return array{manifest: array<string, mixed>, rule_set_source: array<string, mixed>}
     */
    private function cachedBundle(int $seconds): array
    {
        return Cache::remember(VpnDirectDomain::CACHE_KEY, $seconds, static function (): array {
            $domains = VpnDirectDomain::query()
                ->where('is_enabled', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('domain')
                ->values()
                ->all();

            $lastModified = VpnDirectDomain::query()->max('updated_at');

            $manifest = [
                'version' => 1,
                'updated_at' => $lastModified !== null
                    ? \Carbon\Carbon::parse($lastModified)->toIso8601String()
                    : null,
                'domains' => $domains,
            ];

            return [
                'manifest' => $manifest,
                'rule_set_source' => self::buildSingBoxRuleSetSource($domains),
            ];
        });
    }

    /**
     * @param  array<int, string>  $domains
     * @return array{version: int, rules: array<int, array<string, mixed>>}
     */
    private static function buildSingBoxRuleSetSource(array $domains): array
    {
        $suffixes = [];
        foreach ($domains as $d) {
            $n = VpnDirectDomain::normalizeDomain(trim((string) $d));
            if ($n === '') {
                continue;
            }
            if (str_starts_with($n, '*.')) {
                $s = substr($n, 2);
                if ($s !== '') {
                    $suffixes[] = $s;
                }
            } else {
                $suffixes[] = $n;
            }
        }
        $suffixes = array_values(array_unique($suffixes));

        $dotted = [];
        foreach ($suffixes as $s) {
            $s = (string) $s;
            if ($s === '') {
                continue;
            }
            $dotted[] = str_starts_with($s, '.') ? $s : '.'.$s;
        }
        if (in_array('ru', $suffixes, true)) {
            $dotted[] = '.xn--p1ai';
        }
        $dotted = array_values(array_unique($dotted));
        usort($dotted, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $rules = [];
        if ($dotted !== []) {
            $rules[] = [
                'domain_suffix' => $dotted,
            ];
        }

        return [
            'version' => 3,
            'rules' => $rules,
        ];
    }
}
