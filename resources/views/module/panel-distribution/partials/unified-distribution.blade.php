@php
    use App\Constants\TariffTier;
    use Illuminate\Support\Str;
    $hasMarzban = isset($comparison) && !isset($comparison['error']) && !empty($comparison['panels']);
    $tierHeaderClass = [
        'free' => 'border-l-4 border-amber-400 bg-amber-50/95 text-amber-950',
        'full' => 'border-l-4 border-indigo-500 bg-indigo-50/95 text-indigo-950',
        'whitelist' => 'border-l-4 border-violet-500 bg-violet-50/95 text-violet-950',
    ];
@endphp

<div class="rounded-2xl border border-slate-200/90 bg-white shadow-lg shadow-slate-200/50 overflow-hidden" id="panel-distribution-unified">
    <div class="px-4 sm:px-6 py-5 border-b border-slate-100 bg-gradient-to-r from-slate-50 via-white to-indigo-50/30">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div class="min-w-0">
                <h2 class="text-xl font-bold text-slate-900 tracking-tight">Сводная таблица панелей</h2>
                <p class="text-sm text-slate-600 mt-1.5 max-w-3xl leading-relaxed">
                    Одна строка — одна панель в ротации. Колонки слева направо:
                    <strong>идентификация</strong> → <strong>scope v2</strong> (ранжирование выдачи) →
                    <strong>учёт трафика за месяц</strong> (как в биллинге) → <strong>снимок Marzban</strong> (кэш нагрузки).
                </p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 shrink-0 w-full lg:w-auto">
                <label class="relative flex-1 min-w-[200px]">
                    <span class="sr-only">Поиск</span>
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none"></i>
                    <input type="search" id="panel-distribution-filter" autocomplete="off" placeholder="Поиск: ID, сервер, провайдер…"
                           class="w-full pl-9 pr-3 py-2.5 text-sm border border-slate-200 rounded-xl bg-white shadow-sm focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-400 outline-none transition-shadow" />
                </label>
                <button type="button" onclick="location.reload()" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-indigo-800 bg-indigo-100/80 border border-indigo-200 rounded-xl hover:bg-indigo-100 transition-colors whitespace-nowrap">
                    <i class="fas fa-sync-alt text-xs"></i>
                    Обновить
                </button>
            </div>
        </div>

        @if(isset($comparison['error']))
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <strong class="font-semibold">Marzban:</strong> {{ $comparison['error'] }}
            </div>
        @elseif($hasMarzban)
            <div class="mt-5 grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="rounded-xl border border-slate-100 bg-white/80 px-4 py-3 shadow-sm">
                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">В кэше панелей</div>
                    <div class="text-2xl font-bold tabular-nums text-slate-900 mt-0.5">{{ $comparison['summary']['total_panels'] ?? '—' }}</div>
                </div>
                <div class="rounded-xl border border-slate-100 bg-white/80 px-4 py-3 shadow-sm">
                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Со статистикой</div>
                    <div class="text-2xl font-bold tabular-nums text-slate-900 mt-0.5">{{ $comparison['summary']['panels_with_stats'] ?? '—' }}</div>
                </div>
                <div class="rounded-xl border border-slate-100 bg-white/80 px-4 py-3 shadow-sm">
                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">С данными о трафике</div>
                    <div class="text-2xl font-bold tabular-nums text-slate-900 mt-0.5">{{ $comparison['summary']['panels_with_traffic'] ?? '—' }}</div>
                </div>
                <div class="rounded-xl border border-slate-100 bg-white/80 px-4 py-3 shadow-sm">
                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Средний трафик</div>
                    <div class="text-2xl font-bold tabular-nums text-slate-900 mt-0.5">{{ isset($comparison['summary']['avg_traffic']) ? number_format((float) $comparison['summary']['avg_traffic'], 1) : '—' }}%</div>
                </div>
            </div>
        @endif
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-[1100px] w-full text-sm border-collapse">
            <thead>
                <tr class="bg-slate-100/95 text-slate-700">
                    <th colspan="4" class="px-3 py-2 text-left text-xs font-bold uppercase tracking-wider border-b border-slate-200">Панель</th>
                    <th colspan="4" class="px-3 py-2 text-left text-xs font-bold uppercase tracking-wider border-b border-slate-200 border-l border-slate-200/80 bg-indigo-50/50">Scope v2 — ранжирование</th>
                    <th colspan="2" class="px-3 py-2 text-left text-xs font-bold uppercase tracking-wider border-b border-slate-200 border-l border-slate-200/80 bg-emerald-50/50">Учёт (месяц)</th>
                    <th colspan="4" class="px-3 py-2 text-left text-xs font-bold uppercase tracking-wider border-b border-slate-200 border-l border-slate-200/80 bg-sky-50/50">Marzban (кэш)</th>
                </tr>
                <tr class="bg-slate-50/90 text-slate-600 text-xs font-semibold uppercase tracking-wide">
                    <th class="px-3 py-2.5 text-left sticky left-0 z-20 bg-slate-50 border-b border-slate-200 shadow-[2px_0_6px_-2px_rgba(0,0,0,0.06)]">Тариф</th>
                    <th class="px-3 py-2.5 text-left border-b border-slate-200">ID</th>
                    <th class="px-3 py-2.5 text-left border-b border-slate-200 min-w-[140px]">Сервер</th>
                    <th class="px-3 py-2.5 text-left border-b border-slate-200">Пров.</th>
                    <th class="px-3 py-2.5 text-right border-b border-slate-200 border-l border-slate-200/80 bg-indigo-50/30">Очки</th>
                    <th class="px-3 py-2.5 text-left border-b border-slate-200 min-w-[120px]">Прогноз / лимит</th>
                    <th class="px-3 py-2.5 text-center border-b border-slate-200" title="CPU из метаданных scope (формула выбора)">CPU†</th>
                    <th class="px-3 py-2.5 text-left border-b border-slate-200 whitespace-nowrap">τ scope</th>
                    <th class="px-3 py-2.5 text-left border-b border-slate-200 border-l border-slate-200/80 bg-emerald-50/30 min-w-[130px]">ТиБ</th>
                    <th class="px-3 py-2.5 text-center border-b border-slate-200">%</th>
                    <th class="px-3 py-2.5 text-left border-b border-slate-200 border-l border-slate-200/80 bg-sky-50/30 whitespace-nowrap">Польз.</th>
                    <th class="px-3 py-2.5 text-left border-b border-slate-200 min-w-[100px]">Трафик</th>
                    <th class="px-3 py-2.5 text-center border-b border-slate-200">CPU</th>
                    <th class="px-3 py-2.5 text-center border-b border-slate-200">RAM</th>
                </tr>
            </thead>
            @foreach($distributionTiers as $block)
                @php
                    $tier = $block['tier'];
                    $thClass = $tierHeaderClass[$tier] ?? 'border-l-4 border-slate-400 bg-slate-50';
                @endphp
                <tbody class="distribution-tier-group" data-tier="{{ $tier }}">
                    <tr class="{{ $thClass }}">
                        <td colspan="14" class="px-4 py-2.5 font-semibold text-sm">
                            <span class="inline-flex items-center gap-2 flex-wrap">
                                <span>{{ $block['label'] }}</span>
                                <span class="text-xs font-normal opacity-80"><code class="bg-black/5 px-1.5 py-0.5 rounded">{{ $tier }}</code></span>
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-white/60 border border-black/5">{{ count($block['rows']) }} пан.</span>
                                @if($tier === 'free')
                                    <span class="text-xs font-normal opacity-90">— только бесплатные ключи</span>
                                @endif
                            </span>
                        </td>
                    </tr>
                    @forelse($block['rows'] as $row)
                        @php
                            $panel = $row['panel'];
                            $snap = $row['snapshot'];
                            $mb = $row['marzban'];
                            $meta = $panel->selection_scope_meta ?? [];
                            $server = $panel->server;
                            $sName = $server->name ?? '—';
                            $prov = $server->provider ?? '—';
                            $searchBlob = Str::lower($block['label'].' '.$tier.' '.$panel->id.' '.$sName.' '.$prov);
                        @endphp
                        <tr class="group border-b border-slate-100 hover:bg-indigo-50/40 transition-colors distribution-data-row"
                            data-panel-row
                            data-search="{{ $searchBlob }}">
                            <td class="px-3 py-2.5 text-xs whitespace-nowrap sticky left-0 z-10 bg-white group-hover:bg-indigo-50/40 border-r border-slate-100 shadow-[2px_0_6px_-2px_rgba(0,0,0,0.04)]">
                                <span class="inline-flex items-center justify-center min-w-[2.25rem] px-1.5 py-0.5 rounded font-bold uppercase text-[10px] tracking-tight
                                    {{ $tier === 'free' ? 'bg-amber-100 text-amber-900' : ($tier === 'full' ? 'bg-indigo-100 text-indigo-900' : 'bg-violet-100 text-violet-900') }}"
                                      title="{{ TariffTier::label($tier) }}">{{ $tier === 'free' ? 'free' : ($tier === 'full' ? 'full' : 'wl') }}</span>
                            </td>
                            <td class="px-3 py-2.5 font-mono font-semibold text-slate-900 tabular-nums">{{ $panel->id }}</td>
                            <td class="px-3 py-2.5 text-slate-800 max-w-[200px]">
                                <div class="truncate font-medium" title="{{ $sName }}">{{ $sName }}</div>
                            </td>
                            <td class="px-3 py-2.5 text-slate-600 text-xs truncate max-w-[90px]" title="{{ $prov }}">{{ $prov }}</td>
                            <td class="px-3 py-2.5 text-right font-mono font-semibold text-indigo-900 tabular-nums border-l border-slate-100 bg-indigo-50/20">
                                {{ number_format((float) $panel->selection_scope_score, 2, '.', ' ') }}
                            </td>
                            <td class="px-3 py-2.5 text-xs text-slate-700 leading-snug">
                                @if(!empty($meta))
                                    <span class="tabular-nums">{{ $meta['forecast_tb'] ?? '?' }}</span>
                                    <span class="text-slate-400">/</span>
                                    <span class="tabular-nums">{{ $meta['limit_tb'] ?? '?' }}</span>
                                    <span class="text-slate-500">ТиБ</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                @if(isset($meta['cpu_percent']))
                                    <span class="inline-flex min-w-[2.5rem] justify-center px-1.5 py-0.5 rounded text-xs font-bold tabular-nums
                                        {{ (float) $meta['cpu_percent'] > 80 ? 'bg-rose-100 text-rose-800' : ((float) $meta['cpu_percent'] > 60 ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900') }}">
                                        {{ $meta['cpu_percent'] }}%
                                    </span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-xs text-slate-500 whitespace-nowrap">
                                {{ optional($panel->selection_scope_computed_at)->format('d.m H:i') ?? '—' }}
                            </td>
                            <td class="px-3 py-2.5 border-l border-slate-100 bg-emerald-50/15">
                                @if($snap)
                                    <div class="text-xs tabular-nums text-slate-800">
                                        <span class="font-semibold">{{ $snap['used_tb'] !== null ? number_format((float) $snap['used_tb'], 2, '.', ' ') : '—' }}</span>
                                        <span class="text-slate-400">/</span>
                                        {{ $snap['limit_tb'] !== null ? number_format((float) $snap['limit_tb'], 2, '.', ' ') : '—' }}
                                        <span class="text-slate-500">ТиБ</span>
                                    </div>
                                    <div class="text-[10px] text-slate-500 mt-0.5 truncate">{{ $snap['period_label'] }}</div>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-center bg-emerald-50/15">
                                @if($snap && $snap['used_percent'] !== null)
                                    <span class="text-xs font-semibold tabular-nums
                                        {{ (float) $snap['used_percent'] > 80 ? 'text-rose-700' : ((float) $snap['used_percent'] > 60 ? 'text-amber-700' : 'text-emerald-700') }}">
                                        {{ number_format((float) $snap['used_percent'], 1) }}%
                                    </span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 border-l border-slate-100 bg-sky-50/20">
                                @if($mb)
                                    <div class="tabular-nums text-slate-800">{{ $mb['active_users'] ?? 0 }}<span class="text-slate-400">/</span>{{ $mb['total_users'] ?? 0 }}</div>
                                    <div class="text-[10px] text-slate-500">акт. / всего</div>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 bg-sky-50/20 min-w-[104px]">
                                @if($mb && $mb['traffic_used_percent'] !== null)
                                    <div class="h-1.5 w-full bg-slate-200 rounded-full overflow-hidden mb-1">
                                        <div class="h-full rounded-full {{ $mb['traffic_used_percent'] > 80 ? 'bg-rose-500' : ($mb['traffic_used_percent'] > 60 ? 'bg-amber-400' : 'bg-emerald-500') }}"
                                             style="width: {{ min(100, (float) $mb['traffic_used_percent']) }}%"></div>
                                    </div>
                                    <div class="text-xs tabular-nums text-slate-700">{{ number_format((float) $mb['traffic_used_percent'], 1) }}%</div>
                                @else
                                    <span class="text-slate-400 text-xs">нет</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-center bg-sky-50/20">
                                @if($mb && ($mb['cpu_usage'] ?? 0) > 0)
                                    <span class="inline-flex min-w-[2.25rem] justify-center px-1.5 py-0.5 rounded text-xs font-bold tabular-nums
                                        {{ $mb['cpu_usage'] > 80 ? 'bg-rose-100 text-rose-800' : ($mb['cpu_usage'] > 60 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800') }}">
                                        {{ $mb['cpu_usage'] }}%
                                    </span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-center bg-sky-50/20">
                                @if($mb && ($mb['memory_usage'] ?? 0) > 0)
                                    <span class="inline-flex min-w-[2.25rem] justify-center px-1.5 py-0.5 rounded text-xs font-bold tabular-nums
                                        {{ $mb['memory_usage'] > 80 ? 'bg-rose-100 text-rose-800' : ($mb['memory_usage'] > 60 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800') }}">
                                        {{ $mb['memory_usage'] }}%
                                    </span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" class="px-4 py-8 text-center text-slate-500 text-sm">Нет панелей в ротации для этого тарифа.</td>
                        </tr>
                    @endforelse
                </tbody>
            @endforeach
        </table>
    </div>

    <div class="px-4 sm:px-6 py-3 border-t border-slate-100 bg-slate-50/80 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
        <div>
            <span class="font-medium text-slate-600">Подсказка:</span>
            CPU† — доля из метаданных scope (как в формуле ранжирования). Колонки Marzban — живой снимок из кэша.
        </div>
        @if($hasMarzban && isset($comparison['timestamp']))
            <div class="tabular-nums">Кэш Marzban: {{ $comparison['timestamp'] }}</div>
        @endif
    </div>
</div>

@push('scripts')
<script>
(function () {
    const input = document.getElementById('panel-distribution-filter');
    if (!input) return;
    function apply() {
        const q = input.value.trim().toLowerCase();
        document.querySelectorAll('#panel-distribution-unified [data-panel-row]').forEach(function (tr) {
            const hay = tr.getAttribute('data-search') || '';
            tr.hidden = q.length > 0 && !hay.includes(q);
        });
        document.querySelectorAll('#panel-distribution-unified .distribution-tier-group').forEach(function (tbody) {
            const dataRows = Array.prototype.slice.call(tbody.querySelectorAll('[data-panel-row]'));
            const headerRow = tbody.querySelector('tr:first-child');
            if (!headerRow || dataRows.length === 0) return;
            const anyVisible = dataRows.some(function (tr) { return !tr.hidden; });
            headerRow.hidden = q.length > 0 && !anyVisible;
        });
    }
    input.addEventListener('input', apply);
    input.addEventListener('search', apply);
})();
</script>
@endpush
