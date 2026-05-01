{{-- Общие поля bootstrap routing (DIRECT резолверы + UDP/53) для форм применения WARP / Marzban --}}
<input type="hidden" name="warp_bootstrap_ack" value="1">
@php
    $cfgDns = config('panel.warp_full_routing_dns_direct_ips', ['1.1.1.1', '8.8.8.8']);
    $cfgDns = is_array($cfgDns) ? $cfgDns : [];
    $configDnsCsv = implode(', ', array_values(array_filter(array_map('trim', $cfgDns), static fn ($s) => $s !== '')));
    if ($configDnsCsv === '') {
        $configDnsCsv = '1.1.1.1, 8.8.8.8';
    }
    $dbDns = $panel->warp_bootstrap_dns_ips;
    $defaultDnsCsv = ($dbDns !== null && trim((string) $dbDns) !== '') ? trim((string) $dbDns) : $configDnsCsv;
    $warpDnsCsv = old('warp_bootstrap_dns_ips', $defaultDnsCsv);
    $rawUdpOld = old('warp_bootstrap_udp53_direct');
    $warpUdpChecked = $rawUdpOld !== null && $rawUdpOld !== ''
        ? filter_var($rawUdpOld, FILTER_VALIDATE_BOOLEAN)
        : (bool) ($panel->warp_bootstrap_udp53_direct ?? true);
@endphp
<label class="block text-[10px] text-slate-800 mb-1">
    Резолверы в DIRECT перед WARP (<code class="text-[10px]">ip→DIRECT</code>), через запятую
</label>
<input type="text" name="warp_bootstrap_dns_ips" value="{{ $warpDnsCsv }}"
       class="w-full text-xs border border-slate-200 rounded px-2 py-1.5 mb-2"
       placeholder="{{ $configDnsCsv }}"
       autocomplete="off">
<label class="flex items-start gap-2 text-[11px] text-slate-800 cursor-pointer">
    <input type="checkbox" name="warp_bootstrap_udp53_direct" value="1"
           class="rounded mt-0.5"
           @checked($warpUdpChecked)>
    <span><code class="text-[10px]">UDP/53 → DIRECT</code> (до catch-all WARP)</span>
</label>
