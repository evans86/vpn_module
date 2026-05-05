<?php

namespace App\Services\Server;

use App\Models\Panel\Panel;
use Illuminate\Support\Str;

/**
 * Цели для «глобальных» проверок на странице флота: FLEET_PROBE_EXTERNAL_APP_DOMAINS, FLEET_PROBE_SERVER_ZONE_DOMAINS, FLEET_PROBE_OUR_DOMAINS (+ панели из БД и APP_* через config).
 */
class FleetProbeTargetResolver
{
    /**
     * URL/хосты панелей (для ICMP/HTTPS с хоста Laravel).
     *
     * @return array<int, string>
     */
    public function mergedPanelHosts(): array
    {
        $cfg = config('fleet_probe', []);
        $manual = [];
        foreach ((array) ($cfg['panel_hosts'] ?? []) as $h) {
            $h = $this->normalizeTarget((string) $h);
            if ($h !== '') {
                $manual[] = $h;
            }
        }

        $fromDb = [];
        if (($cfg['merge_panels_from_db'] ?? true) === true) {
            $panels = Panel::query()
                ->select(['id', 'panel_adress', 'panel_status', 'panel'])
                ->where('panel_status', '!=', Panel::PANEL_DELETED)
                ->where('panel_status', Panel::PANEL_CONFIGURED)
                ->whereNotNull('panel_adress')
                ->where('panel_adress', '!=', '')
                ->where('panel', Panel::MARZBAN)
                ->orderBy('id')
                ->get();
            foreach ($panels as $p) {
                $t = $this->normalizeTarget((string) $p->panel_adress);
                if ($t !== '') {
                    $fromDb[] = $t;
                }
            }
        }

        return $this->uniqTargets(array_merge($manual, $fromDb));
    }

    /**
     * Наши публичные домены (+ опционально из APP_*).
     *
     * @return array<int, string>
     */
    public function mergedOurDomainHosts(): array
    {
        $cfg = config('fleet_probe', []);
        $out = [];
        foreach ((array) ($cfg['always_probe_our_domains'] ?? []) as $h) {
            $h = $this->normalizeTarget((string) $h);
            if ($h !== '') {
                $out[] = $h;
            }
        }
        foreach ((array) ($cfg['our_domains'] ?? []) as $h) {
            $h = $this->normalizeTarget((string) $h);
            if ($h !== '') {
                $out[] = $h;
            }
        }

        if (($cfg['merge_app_domain_hosts'] ?? true) === true) {
            foreach ($this->hostsFromAppConfig() as $h) {
                $h = $this->normalizeTarget($h);
                if ($h !== '') {
                    $out[] = $h;
                }
            }
        }

        return $this->uniqTargets($out);
    }

    /**
     * @return array<int, string>
     */
    private function hostsFromAppConfig(): array
    {
        $urls = array_filter([
            config('app.url'),
            config('app.config_public_url'),
        ]);
        foreach ((array) config('app.mirror_urls', []) as $u) {
            if (is_string($u) && trim($u) !== '') {
                $urls[] = $u;
            }
        }

        $hosts = [];
        foreach ($urls as $url) {
            $url = rtrim(trim((string) $url), '/');
            if ($url === '') {
                continue;
            }
            if (! str_contains($url, '://')) {
                $url = 'https://'.$url;
            }
            $host = parse_url($url, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $hosts[] = strtolower($host);
            }
        }

        return array_values(array_unique($hosts));
    }

    /**
     * @param  array<int, string>  $list
     * @return array<int, string>
     */
    private function uniqTargets(array $list): array
    {
        $seen = [];
        $uniq = [];
        foreach ($list as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            $key = Str::lower($item);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $uniq[] = $item;
        }

        return array_values($uniq);
    }

    private function normalizeTarget(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        // Уже полный URL
        if (str_contains($raw, '://')) {
            return $raw;
        }

        return 'https://'.$raw.'/';
    }
}
