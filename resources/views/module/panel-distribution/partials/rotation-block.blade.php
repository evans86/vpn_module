<div id="rotation" class="scroll-mt-24 space-y-6">
    @if(isset($comparison['error']))
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 shadow-sm">
            <h3 class="text-base font-semibold text-amber-900 mb-2">Снимок Marzban недоступен</h3>
            <p class="text-sm text-amber-800">{{ $comparison['error'] }}</p>
        </div>
    @elseif(isset($comparison) && !isset($comparison['error']))
        <div class="rounded-xl border border-slate-200 bg-gradient-to-b from-slate-50 to-white shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200/80 flex flex-wrap items-center justify-between gap-3 bg-white/80">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Нагрузка панелей</h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Данные из кэша <code class="text-[11px] bg-slate-100 px-1 rounded">panel:warm-rotation-settings</code>
                        (Marzban). Выбор панели при активации — по scope v2, не по «интеллектуальной» ротации.
                    </p>
                </div>
                <button type="button" onclick="location.reload()" class="shrink-0 inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors">
                    <i class="fas fa-sync-alt text-xs"></i>
                    Обновить страницу
                </button>
            </div>

            <div class="px-5 py-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm border-b border-slate-100 bg-slate-50/50">
                <div class="rounded-lg bg-white border border-slate-100 px-3 py-2">
                    <div class="text-xs text-slate-500">Всего панелей</div>
                    <div class="text-lg font-semibold text-slate-900 tabular-nums">{{ $comparison['summary']['total_panels'] }}</div>
                </div>
                <div class="rounded-lg bg-white border border-slate-100 px-3 py-2">
                    <div class="text-xs text-slate-500">Со статистикой</div>
                    <div class="text-lg font-semibold text-slate-900 tabular-nums">{{ $comparison['summary']['panels_with_stats'] }}</div>
                </div>
                <div class="rounded-lg bg-white border border-slate-100 px-3 py-2">
                    <div class="text-xs text-slate-500">С данными о трафике</div>
                    <div class="text-lg font-semibold text-slate-900 tabular-nums">{{ $comparison['summary']['panels_with_traffic'] }}</div>
                </div>
                <div class="rounded-lg bg-white border border-slate-100 px-3 py-2">
                    <div class="text-xs text-slate-500">Средний трафик</div>
                    <div class="text-lg font-semibold text-slate-900 tabular-nums">{{ number_format($comparison['summary']['avg_traffic'], 1) }}%</div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-100/90 sticky top-0 z-10">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Сервер</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Пользователи</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Трафик</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">CPU</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Память</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach($comparison['panels'] as $panel)
                            <tr class="transition-colors hover:bg-indigo-50/40 {{ ($panel['excluded_from_rotation'] ?? false) ? 'bg-amber-50/60' : '' }}">
                                <td class="px-4 py-3 whitespace-nowrap font-medium text-slate-900">
                                    <span class="inline-flex items-center gap-2">
                                        {{ $panel['id'] }}
                                        @if($panel['excluded_from_rotation'] ?? false)
                                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-200 text-amber-900 text-xs font-bold" title="Исключена из ротации">!</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-slate-700">{{ $panel['server_name'] }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-slate-600">
                                    <div class="tabular-nums">{{ $panel['total_users'] }} всего</div>
                                    <div class="text-xs text-slate-500 tabular-nums">{{ $panel['active_users'] }} активных</div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap min-w-[140px]">
                                    @if($panel['traffic_used_percent'] !== null)
                                        <div class="flex items-center gap-2">
                                            <div class="flex-1 min-w-[72px]">
                                                <div class="h-2 w-full bg-slate-200 rounded-full overflow-hidden">
                                                    <div class="h-full rounded-full transition-all duration-300 {{ $panel['traffic_used_percent'] > 80 ? 'bg-rose-500' : ($panel['traffic_used_percent'] > 60 ? 'bg-amber-400' : 'bg-emerald-500') }}"
                                                         style="width: {{ min(100, $panel['traffic_used_percent']) }}%"></div>
                                                </div>
                                                <div class="text-xs text-slate-600 mt-1 tabular-nums">
                                                    {{ number_format($panel['traffic_used_percent'], 1) }}%
                                                    @if($panel['traffic_used_gb'])
                                                        <span class="text-slate-400">({{ number_format($panel['traffic_used_gb'], 0) }} GB)</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-slate-400">Нет данных</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($panel['cpu_usage'] > 0)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold tabular-nums
                                            {{ $panel['cpu_usage'] > 80 ? 'bg-rose-100 text-rose-800' : ($panel['cpu_usage'] > 60 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800') }}">
                                            {{ $panel['cpu_usage'] }}%
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($panel['memory_usage'] > 0)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold tabular-nums
                                            {{ $panel['memory_usage'] > 80 ? 'bg-rose-100 text-rose-800' : ($panel['memory_usage'] > 60 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800') }}">
                                            {{ $panel['memory_usage'] }}%
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-3 border-t border-slate-100 bg-slate-50/50 text-xs text-slate-500">
                Обновлено: {{ $comparison['timestamp'] }}
            </div>
        </div>
    @endif

    @if($panelsWithErrors->isNotEmpty())
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <span class="text-red-500 mr-2">⚠️</span>
                Панели с ошибками (исключены из ротации)
            </h3>
            <p class="text-sm text-gray-600 mb-6">
                Эти панели автоматически исключены из ротации из-за ошибок при создании пользователей.
                После устранения проблемы снимите пометку об ошибке, чтобы вернуть панель в ротацию.
            </p>

            <div class="space-y-4">
                @foreach($panelsWithErrors as $panel)
                    <div class="border border-red-200 rounded-lg p-4 bg-red-50">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <span class="font-semibold text-gray-900">ID-{{ $panel->id }}</span>
                                    <span class="ml-2 px-2 py-1 text-xs font-semibold bg-red-500 text-white rounded">Ошибка</span>
                                </div>
                                <div class="text-sm text-gray-600 mb-2">
                                    <div><strong>Адрес:</strong> {{ $panel->panel_adress }}</div>
                                    @if($panel->server)
                                        <div><strong>Сервер:</strong> {{ $panel->server->name }}</div>
                                    @endif
                                    @if($panel->error_at)
                                        <div><strong>Дата ошибки:</strong> {{ $panel->error_at->format('d.m.Y H:i') }}</div>
                                    @endif
                                </div>
                                <div class="mt-3 p-3 bg-white rounded border border-red-200">
                                    <div class="text-sm font-medium text-gray-700 mb-1">Сообщение об ошибке:</div>
                                    <div class="text-sm text-gray-800 whitespace-pre-wrap">{{ $panel->error_message }}</div>
                                </div>

                                @if(isset($errorHistory[$panel->id]) && $errorHistory[$panel->id]->isNotEmpty())
                                    <div class="mt-3">
                                        <div class="text-sm font-medium text-gray-700 mb-2">История ошибок:</div>
                                        <div class="space-y-2">
                                            @foreach($errorHistory[$panel->id] as $history)
                                                <div class="text-xs p-2 bg-gray-50 rounded border">
                                                    <div class="flex justify-between items-start mb-1">
                                                        <span class="font-medium text-gray-700">{{ $history->error_occurred_at->format('d.m.Y H:i') }}</span>
                                                        @if($history->resolved_at)
                                                            <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">{{ $history->resolution_type === 'automatic' ? 'Автоматически' : 'Вручную' }}</span>
                                                        @else
                                                            <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded">Активна</span>
                                                        @endif
                                                    </div>
                                                    <div class="text-gray-600 mb-1">{{ $history->error_message }}</div>
                                                    @if($history->resolved_at)
                                                        <div class="text-gray-500 text-xs">
                                                            Решено: {{ $history->resolved_at->format('d.m.Y H:i') }}
                                                            @if($history->resolution_note) — {{ $history->resolution_note }} @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <div class="ml-4">
                                <form action="{{ route('admin.module.panel-settings.clear-error') }}" method="POST"
                                      onsubmit="return confirm('Вы уверены, что проблема решена и панель можно вернуть в ротацию?');">
                                    @csrf
                                    <input type="hidden" name="panel_id" value="{{ $panel->id }}">
                                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors">Проблема решена</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <span class="text-green-500 mr-2">✅</span>
                Статус панелей
            </h3>
            <p class="text-sm text-gray-600">Все панели работают нормально. Ошибок не обнаружено.</p>
        </div>
    @endif

    @if($excludedPanels->isNotEmpty())
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <span class="text-yellow-500 mr-2">🚫</span>
                Панели, исключенные из ротации
            </h3>
            <p class="text-sm text-gray-600 mb-6">
                Вручную исключены из ротации (тесты / обслуживание). Новые пользователи на этих панелях не создаются.
            </p>

            <div class="space-y-4">
                @foreach($excludedPanels as $panel)
                    <div class="border border-yellow-200 rounded-lg p-4 bg-yellow-50">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <span class="font-semibold text-gray-900">ID-{{ $panel->id }}</span>
                                    <span class="ml-2 px-2 py-1 text-xs font-semibold bg-yellow-500 text-white rounded">Исключена</span>
                                </div>
                                <div class="text-sm text-gray-600">
                                    <div><strong>Адрес:</strong> {{ $panel->panel_adress }}</div>
                                    @if($panel->server)
                                        <div><strong>Сервер:</strong> {{ $panel->server->name }}</div>
                                    @endif
                                    @if($panel->config_type)
                                        <div><strong>Тип конфига:</strong>
                                            @if($panel->config_type === 'reality_stable')
                                                <span class="px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">REALITY (только)</span>
                                            @elseif($panel->config_type === 'reality')
                                                <span class="px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">REALITY</span>
                                            @elseif($panel->config_type === 'mixed')
                                                <span class="px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">Смешанный</span>
                                            @else
                                                <span class="px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">Стабильный</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="ml-4">
                                <a href="{{ route('admin.module.panel.index') }}" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors inline-block">
                                    <i class="fas fa-arrow-right mr-2"></i>Управление панелями
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
