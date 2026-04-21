@extends('layouts.admin')

@section('title', 'Мониторинг серверов')
@section('page-title', 'Статистика нагрузки серверов')

@section('content')
    <div class="space-y-4">
        <p class="text-xs text-slate-600 leading-snug">
            Снимки <code class="text-[11px] bg-slate-100 px-1 rounded">server_monitoring</code>.
            <a href="{{ route('admin.module.panel-distribution.index') }}" class="text-indigo-600 hover:underline">Панели и распределение</a>
        </p>

        <x-admin.card>
            <x-slot name="title">
                <span class="text-sm font-semibold">Фильтры</span>
            </x-slot>
            <form action="{{ route('admin.module.server-monitoring.index') }}" method="GET" class="flex flex-wrap items-end gap-3 text-sm">
                <div class="min-w-[120px]">
                    <label class="block text-xs font-medium text-gray-600 mb-0.5">Период</label>
                    <select name="days" class="block w-full rounded border-gray-300 text-sm py-1.5 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach([1 => '1 дн.', 2 => '2 дн.', 3 => '3 дн.', 7 => '7 дн.'] as $d => $label)
                            <option value="{{ $d }}" {{ (int) $days === $d ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[180px] flex-1">
                    <label class="block text-xs font-medium text-gray-600 mb-0.5">Панель</label>
                    <select name="panel_id" class="block w-full rounded border-gray-300 text-sm py-1.5 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Все панели</option>
                        @foreach($allPanels as $panel)
                            <option value="{{ $panel->id }}" {{ (string) $panelId === (string) $panel->id ? 'selected' : '' }}>
                                #{{ $panel->id }} — {{ Str::limit(parse_url($panel->panel_adress, PHP_URL_HOST) ?: $panel->panel_adress, 42) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-[110px]">
                    <label class="block text-xs font-medium text-gray-600 mb-0.5">Точек</label>
                    <select name="limit" class="block w-full rounded border-gray-300 text-sm py-1.5 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach([200 => '200', 500 => '500', 1000 => '1000'] as $l => $label)
                            <option value="{{ $l }}" {{ (int) $limit === $l ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-2 pb-0.5">
                    <button type="submit" class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        <i class="fas fa-filter mr-1.5 text-xs"></i> OK
                    </button>
                    <a href="{{ route('admin.module.server-monitoring.index') }}" class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                        Сброс
                    </a>
                </div>
            </form>
            @if(isset($statistics) && count($statistics) > 0)
                <p class="mt-2 text-xs text-gray-500">
                    @foreach($statistics as $pId => $pData)
                        #{{ $pId }}: {{ $pData['data']->count() }}/{{ $pData['total_records'] ?? 0 }}@if(!$loop->last) · @endif
                    @endforeach
                </p>
            @endif
        </x-admin.card>

        @if(empty($statistics))
            <x-admin.empty-state
                icon="fa-chart-line"
                title="Данные не найдены"
                description="Выберите другой период или панель" />
        @else
            <div class="grid grid-cols-1 lg:grid-cols-2 2xl:grid-cols-3 gap-4">
                @foreach($statistics as $panelId => $panelData)
                    @php
                        $lastStats = $panelData['data']->last()['statistics'] ?? [];
                        $cpuUsage = $lastStats['cpu_usage'] ?? 0;
                        $memoryUsage = ($lastStats['mem_used'] ?? 0) / max($lastStats['mem_total'] ?? 1, 1) * 100;
                        $onlineUsers = $lastStats['online_users'] ?? 0;
                        $totalUsers = $lastStats['total_user'] ?? 0;
                        $userLoad = 0;
                        if ($totalUsers > 0) {
                            $userPercentage = ($onlineUsers / $totalUsers) * 100;
                            if ($userPercentage > 80) {
                                $userLoad = min(100, 60 + (($userPercentage - 80) * 2));
                            } elseif ($userPercentage > 60) {
                                $userLoad = 40 + (($userPercentage - 60) * 1);
                            } else {
                                $userLoad = ($userPercentage / 60) * 40;
                            }
                        }
                        $load = ($cpuUsage * 0.4) + ($memoryUsage * 0.4) + ($userLoad * 0.2);
                        if ($load < 30) {
                            $loadLevel = 'Низк.';
                            $loadColor = 'green';
                            $loadIcon = 'fa-check-circle';
                        } elseif ($load < 60) {
                            $loadLevel = 'Средн.';
                            $loadColor = 'yellow';
                            $loadIcon = 'fa-exclamation-triangle';
                        } elseif ($load < 80) {
                            $loadLevel = 'Высок.';
                            $loadColor = 'orange';
                            $loadIcon = 'fa-exclamation-circle';
                        } else {
                            $loadLevel = 'Крит.';
                            $loadColor = 'red';
                            $loadIcon = 'fa-times-circle';
                        }
                        $hostLabel = parse_url($panelData['panel']->panel_adress, PHP_URL_HOST) ?: $panelData['panel']->panel_adress;
                    @endphp

                    <div class="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden flex flex-col">
                        <div class="px-3 py-2 border-b border-slate-100 bg-slate-50/80 flex flex-wrap items-center justify-between gap-2">
                            <div class="min-w-0">
                                <a href="{{ $panelData['panel']->panel_adress }}" target="_blank" rel="noopener"
                                   class="text-sm font-semibold text-indigo-700 hover:text-indigo-900 truncate block max-w-[240px] sm:max-w-none">
                                    #{{ $panelData['panel']->id }} · {{ Str::limit($hostLabel, 36) }}
                                    <i class="fas fa-external-link-alt text-[10px] ml-1 opacity-70"></i>
                                </a>
                                <span class="inline-flex items-center mt-1 px-2 py-0.5 rounded text-[11px] font-medium
                                    {{ $loadColor === 'green' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $loadColor === 'yellow' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $loadColor === 'orange' ? 'bg-orange-100 text-orange-800' : '' }}
                                    {{ $loadColor === 'red' ? 'bg-red-100 text-red-800' : '' }}">
                                    <i class="fas {{ $loadIcon }} mr-1 text-[10px]"></i>{{ $loadLevel }} · {{ number_format($load, 0) }}%
                                </span>
                            </div>
                            <div class="text-right text-[11px] text-slate-600 tabular-nums leading-tight">
                                <div><span class="text-slate-400">CPU</span> <strong class="text-slate-800">{{ number_format($cpuUsage, 0) }}%</strong></div>
                                <div><span class="text-slate-400">RAM</span> <strong class="text-slate-800">{{ number_format($memoryUsage, 0) }}%</strong></div>
                                <div><span class="text-slate-400">Онл.</span> <strong class="text-slate-800">{{ $onlineUsers }}</strong>@if($totalUsers)/{{ $totalUsers }}@endif</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-px bg-slate-100 divide-x divide-slate-100">
                            <div class="bg-white p-1.5">
                                <div class="text-[10px] font-medium text-slate-500 uppercase tracking-wide mb-0.5">CPU %</div>
                                <div class="chart-mini" id="chart-cpu-{{ $panelId }}"></div>
                            </div>
                            <div class="bg-white p-1.5">
                                <div class="text-[10px] font-medium text-slate-500 uppercase tracking-wide mb-0.5">RAM ГБ</div>
                                <div class="chart-mini" id="chart-memory-{{ $panelId }}"></div>
                            </div>
                            <div class="bg-white p-1.5">
                                <div class="text-[10px] font-medium text-slate-500 uppercase tracking-wide mb-0.5">Онлайн</div>
                                <div class="chart-mini" id="chart-users-{{ $panelId }}"></div>
                            </div>
                        </div>

                        <details class="border-t border-slate-100 group">
                            <summary class="px-3 py-2 text-xs text-indigo-600 hover:bg-slate-50 cursor-pointer list-none flex items-center gap-1">
                                <i class="fas fa-chevron-right text-[10px] transition-transform group-open:rotate-90"></i>
                                Подробнее: пользователи, трафик, память
                            </summary>
                            <div class="px-3 pb-3 pt-1 text-xs text-slate-700 space-y-2 border-t border-slate-50 bg-slate-50/50">
                                <div class="grid grid-cols-2 gap-x-3 gap-y-1">
                                    <span class="text-slate-500">Активн./всего</span>
                                    <span><strong>{{ $lastStats['users_active'] ?? 0 }}</strong> / {{ $lastStats['total_user'] ?? 0 }}</span>
                                    <span class="text-slate-500">Истекли</span>
                                    <span>{{ $lastStats['users_expired'] ?? 0 }}</span>
                                    <span class="text-slate-500">Лимит</span>
                                    <span>{{ $lastStats['users_limited'] ?? 0 }}</span>
                                    <span class="text-slate-500">Hold / off</span>
                                    <span>{{ $lastStats['users_on_hold'] ?? 0 }} / {{ $lastStats['users_disabled'] ?? 0 }}</span>
                                </div>
                                <div class="flex flex-wrap gap-3 pt-1 border-t border-slate-200/80">
                                    <span>Вх. <strong class="text-indigo-700">{{ number_format(($lastStats['incoming_bandwidth'] ?? 0) / (1024 ** 3), 1) }}</strong> ГБ</span>
                                    <span>Исх. <strong class="text-purple-700">{{ number_format(($lastStats['outgoing_bandwidth'] ?? 0) / (1024 ** 3), 1) }}</strong> ГБ</span>
                                    <span>RAM <strong>{{ number_format(($lastStats['mem_used'] ?? 0) / (1024 ** 3), 1) }}</strong> / {{ number_format(($lastStats['mem_total'] ?? 0) / (1024 ** 3), 1) }} ГБ</span>
                                </div>
                            </div>
                        </details>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection

@push('css')
    <style>
        .chart-mini {
            height: 110px;
            width: 100%;
            position: relative;
        }
        .chart-mini .js-plotly-plot {
            width: 100% !important;
            height: 100% !important;
        }
    </style>
@endpush

@push('js')
    <script src="https://cdn.plot.ly/plotly-2.24.1.min.js"></script>
    <script>
        (function () {
            const miniLayout = (yTitle, yRange) => ({
                margin: { l: 28, r: 4, t: 2, b: 18 },
                paper_bgcolor: 'rgba(0,0,0,0)',
                plot_bgcolor: 'rgba(248,250,252,0.6)',
                font: { size: 9, family: 'system-ui, sans-serif' },
                xaxis: {
                    type: 'date',
                    showgrid: false,
                    tickfont: { size: 8 },
                    nticks: 3,
                    tickangle: 0,
                },
                yaxis: {
                    title: { text: yTitle, font: { size: 8 } },
                    showgrid: true,
                    gridcolor: 'rgba(0,0,0,0.06)',
                    tickfont: { size: 8 },
                    ...(yRange ? { range: yRange } : {}),
                },
                showlegend: false,
                hovermode: 'x',
                autosize: true,
            });

            const miniConfig = {
                responsive: true,
                displayModeBar: false,
                staticPlot: false,
            };

            @foreach($statistics as $panelId => $panelData)
            (function () {
                const chartData = {!! json_encode($panelData['data']->map(function ($item) {
                    return [
                        'time' => $item['created_at'],
                        'cpu' => $item['statistics']['cpu_usage'] ?? 0,
                        'memory' => $item['statistics']['mem_used_gb'] ?? 0,
                        'users' => $item['statistics']['online_users'] ?? 0,
                    ];
                })) !!};

                const labels = chartData.map(item => item.time);
                const cpuData = chartData.map(item => item.cpu);
                const memoryData = chartData.map(item => item.memory);
                const usersData = chartData.map(item => item.users);

                const emptyHtml = '<div class="flex items-center justify-center h-full text-[10px] text-slate-400">Нет данных</div>';

                if (labels.length === 0) {
                    document.getElementById('chart-cpu-{{ $panelId }}').innerHTML = emptyHtml;
                    document.getElementById('chart-memory-{{ $panelId }}').innerHTML = emptyHtml;
                    document.getElementById('chart-users-{{ $panelId }}').innerHTML = emptyHtml;
                    return;
                }

                Plotly.newPlot('chart-cpu-{{ $panelId }}', [{
                    x: labels,
                    y: cpuData,
                    type: 'scatter',
                    mode: 'lines',
                    line: { color: 'rgb(13,148,136)', width: 1.5 },
                    fill: 'tozeroy',
                    fillcolor: 'rgba(13,148,136,0.12)',
                    hovertemplate: 'CPU %{y:.1f}%<br>%{x}<extra></extra>',
                }], miniLayout('', [0, 100]), miniConfig);

                Plotly.newPlot('chart-memory-{{ $panelId }}', [{
                    x: labels,
                    y: memoryData,
                    type: 'scatter',
                    mode: 'lines',
                    line: { color: 'rgb(109,40,217)', width: 1.5 },
                    fill: 'tozeroy',
                    fillcolor: 'rgba(109,40,217,0.1)',
                    hovertemplate: 'RAM %{y:.2f} ГБ<br>%{x}<extra></extra>',
                }], miniLayout('', null), miniConfig);

                Plotly.newPlot('chart-users-{{ $panelId }}', [{
                    x: labels,
                    y: usersData,
                    type: 'scatter',
                    mode: 'lines',
                    line: { color: 'rgb(217,119,6)', width: 1.5 },
                    fill: 'tozeroy',
                    fillcolor: 'rgba(217,119,6,0.12)',
                    hovertemplate: 'Онлайн %{y}<br>%{x}<extra></extra>',
                }], miniLayout('', null), miniConfig);
            })();
            @endforeach

            let resizeT;
            window.addEventListener('resize', function () {
                clearTimeout(resizeT);
                resizeT = setTimeout(function () {
                    @foreach($statistics as $panelId => $panelData)
                    try {
                        Plotly.Plots.resize('chart-cpu-{{ $panelId }}');
                        Plotly.Plots.resize('chart-memory-{{ $panelId }}');
                        Plotly.Plots.resize('chart-users-{{ $panelId }}');
                    } catch (e) {}
                    @endforeach
                }, 150);
            });
        })();
    </script>
@endpush
