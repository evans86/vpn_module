<?php

namespace App\Services\VPN;

/**
 * Профиль Clash / Clash Meta / Stash: узлы подтягиваются с ?format=raw, правила DIRECT — из БД.
 * Для Hiddify и др. — см. buildInline (узлы в proxies:, без proxy-providers).
 */
class SubscriptionClashProfileBuilder
{
    /**
     * Clash YAML с узлами в секции proxies (без proxy-providers) — для клиентов, где вложенная подписка не поддерживается.
     *
     * @param  array<int, string>  $linkLines  Строки vless/vmess/trojan/ss как в plain-подписке
     * @param  array<int, string>  $directDomains
     */
    public function buildInline(string $keyActivateId, array $linkLines, array $directDomains): string
    {
        $proxies = [];
        $seenNames = [];
        foreach ($linkLines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $p = ClashProxyFromSubscriptionLink::parse($line);
            if ($p === null) {
                continue;
            }
            $baseName = (string) ($p['name'] ?? 'VPN');
            $name = $baseName;
            $n = 2;
            while (isset($seenNames[$name])) {
                $name = $baseName.' ('.$n.')';
                $n++;
            }
            $seenNames[$name] = true;
            $p['name'] = $name;
            $proxies[] = $p;
        }

        if ($proxies === []) {
            return $this->build($keyActivateId, $directDomains);
        }

        $yaml = "mixed-port: 7890\n";
        $yaml .= "# VPN: узлы встроены (совместимость с Hiddify и др. без proxy-providers)\n";
        $yaml .= "allow-lan: false\n";
        $yaml .= "mode: rule\n";
        $yaml .= "log-level: info\n\n";
        $yaml .= "proxies:\n";
        foreach ($proxies as $proxy) {
            $yaml .= $this->yamlEncodeMapBlock($proxy, 2);
        }
        $yaml .= "\nproxy-groups:\n";
        $yaml .= "  - name: PROXY\n";
        $yaml .= "    type: select\n";
        $yaml .= "    proxies:\n";
        foreach ($proxies as $proxy) {
            $yaml .= '      - '.$this->yamlScalar((string) $proxy['name'])."\n";
        }
        $yaml .= "\nrules:\n";
        foreach ($this->normalizeDirectRules($directDomains) as $line) {
            $yaml .= '  - '.$line."\n";
        }
        $yaml .= "  - MATCH,PROXY\n";

        return $yaml;
    }

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

    /**
     * Один элемент списка proxies: первая строка «  - name: …», остальные с отступом.
     *
     * @param  array<string, mixed>  $map
     */
    private function yamlEncodeMapBlock(array $map, int $baseIndent): string
    {
        $sp = str_repeat(' ', $baseIndent);
        $itemIndent = $baseIndent + 2;
        $is = str_repeat(' ', $itemIndent);
        $out = '';
        $first = true;
        foreach ($map as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            $rendered = $this->yamlFormatValueAfterKey($value, $itemIndent);
            if ($first) {
                $out .= $sp.'- '.$key.': '.$rendered."\n";
                $first = false;
            } else {
                $out .= $is.$key.': '.$rendered."\n";
            }
        }

        return $out;
    }

    /**
     * @param  mixed  $value
     */
    private function yamlFormatValueAfterKey($value, int $indent): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            return $this->yamlScalar($value);
        }
        if (! is_array($value)) {
            return '""';
        }
        if ($value === []) {
            return '{}';
        }
        $isList = array_keys($value) === range(0, count($value) - 1);
        $next = $indent + 2;
        if ($isList) {
            $lines = [];
            foreach ($value as $item) {
                $lines[] = str_repeat(' ', $next).'- '.$this->yamlFormatListItemValue($item, $next);
            }

            return "\n".implode("\n", $lines);
        }
        $lines = [];
        foreach ($value as $k => $v) {
            if (! is_string($k)) {
                continue;
            }
            $lines[] = str_repeat(' ', $next).$k.': '.$this->yamlFormatValueAfterKey($v, $next);
        }

        return "\n".implode("\n", $lines);
    }

    /**
     * @param  mixed  $value
     */
    private function yamlFormatListItemValue($value, int $indent): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            return $this->yamlScalar($value);
        }
        if (! is_array($value)) {
            return '""';
        }
        if ($value === []) {
            return '{}';
        }
        $isList = array_keys($value) === range(0, count($value) - 1);
        $next = $indent + 2;
        if ($isList) {
            $lines = [];
            foreach ($value as $item) {
                $lines[] = str_repeat(' ', $next).'- '.$this->yamlFormatListItemValue($item, $next);
            }

            return "\n".implode("\n", $lines);
        }
        $lines = [];
        foreach ($value as $k => $v) {
            if (! is_string($k)) {
                continue;
            }
            $lines[] = str_repeat(' ', $next).$k.': '.$this->yamlFormatValueAfterKey($v, $next);
        }

        return "\n".implode("\n", $lines);
    }
}
