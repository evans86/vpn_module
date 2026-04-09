@php
    use Illuminate\Support\Str;
    $hasMarzban = isset($comparison) && !isset($comparison['error']) && !empty($comparison['panels']);
    $refLimitTb = round((int) config('panel.server_traffic_limit', 32 * 1024 * 1024 * 1024 * 1024) / (1024 ** 4));
    $tierAccent = [
        'free' => ['bar' => 'from-amber-400 to-orange-300', 'ring' => 'ring-amber-400/30', 'badge' => 'bg-amber-500/15 text-amber-950 border-amber-400/40'],
        'full' => ['bar' => 'from-indigo-500 to-violet-400', 'ring' => 'ring-indigo-400/35', 'badge' => 'bg-indigo-500/15 text-indigo-950 border-indigo-400/40'],
        'whitelist' => ['bar' => 'from-violet-500 to-fuchsia-400', 'ring' => 'ring-violet-400/35', 'badge' => 'bg-violet-500/15 text-violet-950 border-violet-400/40'],
    ];
@endphp

<div class="rounded-2xl border border-slate-200/90 bg-slate-50/40 shadow-xl shadow-slate-300/20 overflow-hidden" id="panel-distribution-unified">
    <div class="px-4 sm:px-6 py-4 border-b border-slate-200/80 bg-white/90 backdrop-blur-sm">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-slate-900 tracking-tight">Панели в ротации</h2>
                <p class="text-xs text-slate-500 mt-1">Группировка по <code class="text-[11px] bg-slate-100 px-1 rounded">server.tariff_tier</code>. Scope — очередность выдачи (0–100).</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 w-full lg:w-auto">
                <label class="relative flex-1 min-w-0 sm:min-w-[220px]">
                    <span class="sr-only">Поиск</span>
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none"></i>
                    <input type="search" id="panel-distribution-filter" autocomplete="off" placeholder="ID, сервер, провайдер…"
                           class="w-full pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg bg-white focus:ring-2 focus:ring-indigo-500/25 focus:border-indigo-400 outline-none" />
                </label>
                <button type="button" onclick="location.reload()" class="inline-flex items-center justify-center gap-2 px-3 py-2 text-sm font-medium text-slate-800 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors shrink-0">
                    <i class="fas fa-sync-alt text-xs text-slate-500"></i>
                    Обновить
                </button>
            </div>
        </div>

        @if(isset($comparison['error']))
            <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                <span class="font-medium">Снимок Marzban недоступен.</span> {{ $comparison['error'] }}
            </div>
        @elseif($hasMarzban)
            <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3">
                <div class="rounded-lg border border-slate-100 bg-white px-3 py-2 shadow-sm" title="Все панели в кэше команды panel:warm-rotation-settings">
                    <div class="text-[10px] font-semibold text-slate-500 leading-tight">Панелей в снимке</div>
                    <div class="text-xl font-bold tabular-nums text-slate-900">{{ $comparison['summary']['total_panels'] ?? '—' }}</div>
                </div>
                <div class="rounded-lg border border-slate-100 bg-white px-3 py-2 shadow-sm" title="Есть свежие данные Marzban (CPU/RAM/пользователи)">
                    <div class="text-[10px] font-semibold text-slate-500 leading-tight">Со статистикой Marzban</div>
                    <div class="text-xl font-bold tabular-nums text-slate-900">{{ $comparison['summary']['panels_with_stats'] ?? '—' }}</div>
                </div>
                <div class="rounded-lg border border-slate-100 bg-white px-3 py-2 shadow-sm" title="Панели, для которых в кэше есть данные API хостинга о трафике">
                    <div class="text-[10px] font-semibold text-slate-500 leading-tight">Трафик из API хостинга</div>
                    <div class="text-xl font-bold tabular-nums text-slate-900">{{ $comparison['summary']['panels_with_traffic'] ?? '—' }}</div>
                </div>
                <div class="rounded-lg border border-slate-100 bg-white px-3 py-2 shadow-sm" title="Средний процент использования лимита по API (только панели с данными API)">
                    <div class="text-[10px] font-semibold text-slate-500 leading-tight">Средний % лимита (API)</div>
                    <div class="text-xl font-bold tabular-nums text-slate-900">{{ isset($comparison['summary']['avg_traffic']) ? number_format((float) $comparison['summary']['avg_traffic'], 1) : '—' }}</div>
                </div>
            </div>
        @endif
    </div>

    <div class="p-4 sm:p-6 space-y-8">
        @foreach($distributionTiers as $block)
            @php
                $tier = $block['tier'];
                $acc = $tierAccent[$tier] ?? $tierAccent['full'];
            @endphp
            <section class="distribution-tier-block" data-tier="{{ $tier }}">
                <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-8 w-1 rounded-full bg-gradient-to-b {{ $acc['bar'] }}"></span>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">{{ $block['label'] }}</h3>
                            <p class="text-xs text-slate-500"><code class="text-[11px] bg-slate-200/60 px-1 rounded">{{ $tier }}</code> · {{ count($block['rows']) }} панелей</p>
                        </div>
                    </div>
                </div>

                @if(empty($block['rows']))
                    <div class="rounded-xl border border-dashed border-slate-200 bg-white/60 px-4 py-8 text-center text-sm text-slate-500">
                        Нет панелей в ротации для этого тарифа.
                    </div>
                @else
                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
                        @foreach($block['rows'] as $row)
                            @php
                                $panel = $row['panel'];
                                $snap = $row['snapshot'];
                                $mb = $row['marzban'];
                                $keysBytes = (int) ($row['traffic_keys_sum_bytes'] ?? 0);
                                $keysTb = $keysBytes > 0 ? $keysBytes / (1024 ** 4) : 0;
                                $refLimitBytes = (int) config('panel.server_traffic_limit', 32 * 1024 * 1024 * 1024 * 1024);
                                $keysVsRefPct = $refLimitBytes > 0 && $keysBytes > 0
                                    ? min(100, round(($keysBytes / $refLimitBytes) * 100, 1))
                                    : null;
                                $meta = $panel->selection_scope_meta ?? [];
                                $server = $panel->server;
                                $sName = $server->name ?? '—';
                                $prov = $server->provider ?? '—';
                                $score = (float) $panel->selection_scope_score;
                                $searchBlob = Str::lower($block['label'].' '.$tier.' '.$panel->id.' '.$sName.' '.$prov);
                            @endphp
                            <article class="panel-dist-card group relative flex flex-col rounded-2xl border border-slate-200/90 bg-white shadow-md shadow-slate-200/40 ring-1 {{ $acc['ring'] }} transition-all hover:shadow-lg hover:border-slate-300/90"
                                     data-panel-card
                                     data-search="{{ $searchBlob }}">
                                <div class="flex items-start justify-between gap-3 p-4 pb-3 border-b border-slate-100/90">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2 mb-1">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wide border {{ $acc['badge'] }}">{{ $tier }}</span>
                                            <span class="font-mono text-sm font-bold text-slate-900">#{{ $panel->id }}</span>
                                        </div>
                                        <p class="font-semibold text-slate-900 truncate" title="{{ $sName }}">{{ $sName }}</p>
                                        <p class="text-xs text-slate-500 truncate" title="{{ $prov }}">{{ $prov }}</p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <div class="text-[10px] font-semibold text-slate-500 leading-none mb-0.5">Scope v2</div>
                                        <div class="text-2xl font-black tabular-nums leading-none text-transparent bg-clip-text bg-gradient-to-br {{ $acc['bar'] }}">{{ number_format($score, 1, '.', '') }}</div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">выше — приоритетнее</div>
                                    </div>
                                </div>

                                <div class="px-4 py-3 space-y-3 flex-1 flex flex-col">
                                    <div class="rounded-lg bg-slate-50 border border-slate-100 px-3 py-2">
                                        <div class="text-[10px] font-bold uppercase tracking-wide text-slate-600 mb-2">Данные для ранжирования</div>
                                        <dl class="grid grid-cols-1 gap-y-2 text-xs">
                                            <div class="flex justify-between gap-2">
                                                <dt class="text-slate-500 shrink-0">Прогноз / лимит</dt>
                                                <dd class="font-mono tabular-nums text-slate-900 text-right">
                                                    @if(!empty($meta))
                                                        {{ $meta['forecast_tb'] ?? '?' }} / {{ $meta['limit_tb'] ?? '?' }} ТиБ
                                                    @else
                                                        <span class="text-slate-400">нет метаданных</span>
                                                    @endif
                                                </dd>
                                            </div>
                                            <div class="flex justify-between gap-2">
                                                <dt class="text-slate-500 shrink-0" title="Участвует в формуле scope">CPU панели</dt>
                                                <dd class="tabular-nums text-slate-900 text-right font-semibold">
                                                    {{ isset($meta['cpu_percent']) ? $meta['cpu_percent'].'%' : '—' }}
                                                </dd>
                                            </div>
                                            <div class="flex justify-between gap-2">
                                                <dt class="text-slate-500 shrink-0">Пересчёт scope</dt>
                                                <dd class="text-slate-600 text-right">{{ optional($panel->selection_scope_computed_at)->format('d.m.Y H:i') ?? '—' }}</dd>
                                            </div>
                                        </dl>
                                    </div>

                                    <div class="rounded-xl border border-emerald-200/70 bg-gradient-to-br from-emerald-50/90 to-white px-3 py-2.5 flex-1">
                                        <div class="text-[10px] font-bold uppercase tracking-wide text-emerald-900/90 mb-2">Трафик</div>
                                        <ol class="space-y-2.5 text-xs list-decimal list-inside text-slate-700 marker:text-emerald-600 marker:font-semibold">
                                            <li class="pl-0.5">
                                                <span class="inline font-medium text-slate-800">По ключам (БД)</span>
                                                @if($keysBytes > 0)
                                                    <span class="block mt-1 ml-4 font-mono tabular-nums text-slate-900">
                                                        {{ number_format($keysTb, 2, '.', ' ') }} ТиБ
                                                        @if($keysVsRefPct !== null)
                                                            <span class="text-slate-500 font-sans text-[11px] font-normal" title="Для сравнения панелей между собой; не лимит хостинга">
                                                                — {{ $keysVsRefPct }}% от условных {{ $refLimitTb }} ТиБ
                                                            </span>
                                                        @endif
                                                    </span>
                                                @else
                                                    <span class="block mt-1 ml-4 text-slate-400">нет суммы по ключам</span>
                                                @endif
                                            </li>
                                            @if($snap && ($snap['used_tb'] !== null || $snap['limit_tb'] !== null))
                                                <li class="pl-0.5">
                                                    <span class="inline font-medium text-slate-800">Лимит у хостинга (API)</span>
                                                    <span class="block mt-1 ml-4 font-mono tabular-nums text-slate-900">
                                                        {{ $snap['used_tb'] !== null ? number_format((float) $snap['used_tb'], 2, '.', ' ') : '—' }}
                                                        / {{ $snap['limit_tb'] !== null ? number_format((float) $snap['limit_tb'], 2, '.', ' ') : '—' }} ТиБ
                                                        @if($snap['used_percent'] !== null)
                                                            <span class="text-emerald-800 font-semibold"> ({{ number_format((float) $snap['used_percent'], 1) }}%)</span>
                                                        @endif
                                                    </span>
                                                    @if(!empty($snap['period_label']))
                                                        <span class="block ml-4 text-[10px] text-slate-500 mt-0.5">{{ $snap['period_label'] }}</span>
                                                    @endif
                                                </li>
                                            @endif
                                            @if($mb && $mb['traffic_used_percent'] !== null)
                                                <li class="pl-0.5">
                                                    <span class="inline font-medium text-slate-800">То же в кэше прогрева</span>
                                                    <span class="block mt-1 ml-4 flex items-center gap-2 flex-wrap">
                                                        <span class="h-1.5 w-20 rounded-full bg-slate-200 overflow-hidden shrink-0">
                                                            <span class="block h-full rounded-full {{ $mb['traffic_used_percent'] > 80 ? 'bg-rose-500' : ($mb['traffic_used_percent'] > 60 ? 'bg-amber-400' : 'bg-emerald-500') }}" style="width: {{ min(100, (float) $mb['traffic_used_percent']) }}%"></span>
                                                        </span>
                                                        <span class="font-mono tabular-nums font-semibold">{{ number_format((float) $mb['traffic_used_percent'], 1) }}%</span>
                                                        <span class="text-[10px] text-slate-500">доля от лимита API в кэше</span>
                                                    </span>
                                                </li>
                                            @endif
                                        </ol>
                                    </div>

                                    <div class="rounded-lg bg-sky-50/80 border border-sky-100 px-3 py-2">
                                        <div class="text-[10px] font-bold uppercase tracking-wide text-sky-900/80 mb-2">Нагрузка (Marzban)</div>
                                        @if($mb)
                                            <div class="flex flex-wrap gap-2">
                                                <span class="inline-flex items-center gap-1.5 rounded-md bg-white border border-sky-100 px-2 py-1 text-[11px] font-medium text-sky-950">
                                                    <i class="fas fa-users text-sky-500"></i>
                                                    актив. {{ $mb['active_users'] ?? 0 }} · всего {{ $mb['total_users'] ?? 0 }}
                                                </span>
                                                @if(($mb['cpu_usage'] ?? 0) > 0)
                                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-[11px] font-bold tabular-nums {{ $mb['cpu_usage'] > 80 ? 'bg-rose-100 text-rose-800' : ($mb['cpu_usage'] > 60 ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900') }}">
                                                        CPU {{ $mb['cpu_usage'] }}%
                                                    </span>
                                                @endif
                                                @if(($mb['memory_usage'] ?? 0) > 0)
                                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-[11px] font-bold tabular-nums {{ $mb['memory_usage'] > 80 ? 'bg-rose-100 text-rose-800' : ($mb['memory_usage'] > 60 ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900') }}">
                                                        RAM {{ $mb['memory_usage'] }}%
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <p class="text-[11px] text-slate-500">Нет строки в кэше — выполните <code class="text-[10px] bg-white px-1 rounded">panel:warm-rotation-settings</code></p>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    </div>

    @if($hasMarzban && isset($comparison['timestamp']))
        <div class="px-4 sm:px-6 py-2 border-t border-slate-200/80 bg-slate-50/90 text-right text-[11px] text-slate-400 tabular-nums">
            Снимок кэша: {{ $comparison['timestamp'] }}
        </div>
    @endif
</div>

@push('scripts')
<script>
(function () {
    const input = document.getElementById('panel-distribution-filter');
    if (!input) return;
    function apply() {
        const q = input.value.trim().toLowerCase();
        document.querySelectorAll('#panel-distribution-unified [data-panel-card]').forEach(function (el) {
            const hay = el.getAttribute('data-search') || '';
            el.hidden = q.length > 0 && !hay.includes(q);
        });
        document.querySelectorAll('#panel-distribution-unified .distribution-tier-block').forEach(function (section) {
            const cards = section.querySelectorAll('[data-panel-card]');
            if (!cards.length) return;
            const any = Array.prototype.some.call(cards, function (c) { return !c.hidden; });
            section.hidden = q.length > 0 && !any;
        });
    }
    input.addEventListener('input', apply);
    input.addEventListener('search', apply);
})();
</script>
@endpush
