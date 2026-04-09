@extends('layouts.admin')

@php
    use App\Constants\TariffTier;
@endphp

@section('title', 'Панели и распределение')
@section('page-title', 'Панели и распределение')

@section('content')
    <div class="space-y-6">
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif

        @include('module.panel-distribution.partials.snapshot-cards', ['snapshotPanels' => $snapshotPanels])

        @foreach($panelsByTier as $tier => $panels)
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap items-baseline justify-between gap-2">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ TariffTier::label($tier) }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Код: <code class="bg-gray-100 px-1 rounded">{{ $tier }}</code> · панелей: {{ $panels->count() }}</p>
                    </div>
                    @if($tier === 'free')
                        <span class="text-xs text-gray-600 max-w-xl">Бесплатные ключи — только этот пул.</span>
                    @endif
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
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-500 text-sm">
                                        Нет панелей в ротации для этого тарифа.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        @include('module.panel-distribution.partials.rotation-block')
    </div>
@endsection
