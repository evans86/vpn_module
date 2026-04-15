<?php

namespace App\Services\VPN;

/**
 * Профиль Clash / Clash Meta / Stash: узлы подтягиваются с ?format=raw, правила DIRECT — из БД.
 */
class SubscriptionClashProfileBuilder
{
    /**
     * @param  array<int, string>  $directDomains
     */
    public function build(string $keyActivateId, array $directDomains): string
    {
        $rawUrl = route('vpn.config.show', [
            'token' => $keyActivateId,
            'format' => 'raw',
        ], true);

        // Первая строка — валидный ключ Clash (часть клиентов иначе не распознаёт YAML);
        // комментарии ниже, не в начале файла.
        $yaml = "mixed-port: 7890\n";
        $yaml .= "# VPN subscription + split (DIRECT); узлы: ?format=raw\n";
        $yaml .= "allow-lan: false\n";
        $yaml .= "mode: rule\n";
        $yaml .= "log-level: info\n";
        $yaml .= "\n";
        $yaml .= "proxy-providers:\n";
        $yaml .= "  vpn-nodes:\n";
        $yaml .= '    type: http'."\n";
        $yaml .= '    url: '.$this->yamlScalar($rawUrl)."\n";
        $yaml .= "    interval: 3600\n";
        $yaml .= "    path: ./vpn-nodes-{$keyActivateId}.yaml\n";
        $yaml .= "    format: v2ray\n";
        $yaml .= "    health-check:\n";
        $yaml .= "      enable: true\n";
        $yaml .= "      interval: 600\n";
        $yaml .= "      url: http://www.gstatic.com/generate_204\n";
        $yaml .= "\n";
        $yaml .= "proxy-groups:\n";
        $yaml .= "  - name: PROXY\n";
        $yaml .= "    type: select\n";
        $yaml .= "    use:\n";
        $yaml .= "      - vpn-nodes\n";
        $yaml .= "\n";
        $yaml .= "rules:\n";

        foreach ($this->normalizeDirectRules($directDomains) as $line) {
            $yaml .= '  - '.$line."\n";
        }
        $yaml .= "  - MATCH,PROXY\n";

        return $yaml;
    }

    /**
     * @param  array<int, string>  $directDomains
     * @return array<int, string>
     */
    private function normalizeDirectRules(array $directDomains): array
    {
        $out = [];
        foreach ($directDomains as $d) {
            $d = trim((string) $d);
            if ($d === '') {
                continue;
            }
            if (strpos($d, '*.') === 0) {
                $suffix = substr($d, 2);
                if ($suffix !== '') {
                    $out[] = 'DOMAIN-SUFFIX,'.$suffix.',DIRECT';
                }
            } else {
                $out[] = 'DOMAIN-SUFFIX,'.$d.',DIRECT';
            }
        }

        return $out;
    }

    private function yamlScalar(string $value): string
    {
        if ($value === '') {
            return '""';
        }
        if (preg_match('/[\x00-\x1f\x7f\'\"\\\\]/', $value)) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return '"'.$value.'"';
    }
}
