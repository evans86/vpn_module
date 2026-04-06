@extends('layouts.admin')

@php
    use App\Constants\TariffTier;
@endphp

@section('title', 'Распределение (scope v2)')
@section('page-title', 'Распределение панелей — scope v2')

@section('content')
    <div class="space-y-6">
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Новая логика выбора панели</h3>
            <p class="text-sm text-gray-600 mb-3">
                <strong>scope</strong> хранится в <code class="text-xs bg-gray-100 px-1 rounded">panel.selection_scope_score</code>,
                пересчёт: <code class="text-xs bg-gray-100 px-1 rounded">php artisan panel:recalculate-selection-scope</code>
                и cron (см. <code class="text-xs">PANEL_SCOPE_RECALC_ENABLED</code>).
                Формула: жёсткое произведение
                <code class="text-xs">100 × max(0, 1 − T_forecast/T_limit) × max(0, 1 − CPU%)</code>
                (прогноз трафика на конец месяца по текущему дню, CPU из последнего <code class="text-xs">server_monitoring</code>).
            </p>
            <p class="text-sm">
                @if($v2Enabled)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">PANEL_SELECTION_V2 включён</span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-800">PANEL_SELECTION_V2 выключен</span>
                    <span class="text-gray-600 text-sm ml-2">— активация использует simple/intelligent (как на странице «Настройки распределения»).</span>
                @endif
            </p>
            <p class="text-xs text-gray-500 mt-2">
                Фильтр тарифа для кандидатов: <code>PANEL_ACTIVATION_TARIFF_TIER={{ $tariffTier }}</code>
                (колонка <code>server.tariff_tier</code>). Кэш выбора при v2: <code>PANEL_SELECTION_V2_CACHE_TTL={{ $v2CacheTtl }}</code>
                (0 = без кэша).
            </p>
        </div>

        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Панели в ротации (по убыванию scope)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Сервер</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Провайдер</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Тариф</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Scope</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Обновлено</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Детали</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($panels as $panel)
                            @php
                                $meta = $panel->selection_scope_meta ?? [];
                                $server = $panel->server;
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-sm text-gray-900">{{ $panel->id }}</td>
                                <td class="px-4 py-2 text-sm text-gray-800">{{ $server->name ?? '—' }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ $server->provider ?? '—' }}</td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ TariffTier::label($server->tariff_tier) }}</td>
                                <td class="px-4 py-2 text-sm text-right font-mono font-medium">
                                    {{ number_format((float) $panel->selection_scope_score, 2, '.', ' ') }}
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-500 whitespace-nowrap">
                                    {{ optional($panel->selection_scope_computed_at)->format('Y-m-d H:i') ?? '—' }}
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-600 max-w-md truncate" title="{{ json_encode($meta, JSON_UNESCAPED_UNICODE) }}">
                                    @if(!empty($meta))
                                        CPU {{ $meta['cpu_percent'] ?? '?' }}% · прогноз {{ $meta['forecast_tb'] ?? '?' }} ТиБ / лимит {{ $meta['limit_tb'] ?? '?' }} ТиБ
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500 text-sm">
                                    Нет панелей или не выполнена миграция / пересчёт scope.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
